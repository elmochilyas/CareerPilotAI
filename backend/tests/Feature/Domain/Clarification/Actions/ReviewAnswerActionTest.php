<?php

use App\Domain\Clarification\Actions\ReviewAnswerAction;
use App\Domain\Clarification\Enums\ClarificationAnswerStatus;
use App\Domain\Clarification\Enums\ClarificationAnswerType;
use App\Domain\Clarification\Enums\ClarificationProposalField;
use App\Domain\Clarification\Enums\ClarificationProposalStatus;
use App\Domain\Clarification\Enums\ClarificationTargetType;
use App\Domain\Matching\Enums\MatchCategory;
use App\Domain\Matching\Enums\RequirementSourceType;
use App\Domain\Skills\Enums\SkillState;
use App\Exceptions\Api\ConflictException;
use App\Exceptions\Api\NotFoundException;
use App\Exceptions\Api\UnprocessableEntityException;
use App\Jobs\ObserveClarificationStalenessJob;
use App\Models\CandidateProfile;
use App\Models\CandidateSkill;
use App\Models\ClarificationAnswer;
use App\Models\ClarificationAuditEvent;
use App\Models\ClarificationProposal;
use App\Models\ClarificationQuestion;
use App\Models\MatchAnalysis;
use App\Models\MatchFinding;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class)->group('clarifications', 'actions');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->profile = CandidateProfile::factory()->create(['user_id' => $this->user->id]);
    $this->analysis = MatchAnalysis::factory()->completed()->create(['candidate_profile_id' => $this->profile->id]);
    $this->skill = Skill::factory()->create();
    $this->candidateSkill = CandidateSkill::factory()->claimed()->create([
        'candidate_profile_id' => $this->profile->id,
        'skill_id' => $this->skill->id,
    ]);
    $this->action = app(ReviewAnswerAction::class);
});

function reviewFinding(MatchAnalysis $analysis, CandidateSkill $candidateSkill): MatchFinding
{
    return MatchFinding::factory()->partial()->create([
        'match_analysis_id' => $analysis->id,
        'category' => MatchCategory::RequiredSkills->value,
        'source_type' => RequirementSourceType::JobOpportunitySkill,
        'source_id' => 999999,
        'matched_candidate_skill_id' => $candidateSkill->id,
    ]);
}

function reviewQuestion(MatchAnalysis $analysis, MatchFinding $finding): ClarificationQuestion
{
    return ClarificationQuestion::factory()->yesNoWithDetails()->create([
        'match_analysis_id' => $analysis->id,
        'match_finding_id' => $finding->id,
        'question_no' => 1,
    ]);
}

function reviewPendingAnswer(User $user, ClarificationQuestion $question, ?ClarificationAnswerType $answerType = null): ClarificationAnswer
{
    return ClarificationAnswer::factory()->create([
        'user_id' => $user->id,
        'question_id' => $question->id,
        'answer_type' => $answerType ?? ClarificationAnswerType::Yes,
        'value' => $answerType === ClarificationAnswerType::Number ? '3' : 'https://example.com/cert',
        'acknowledged_no_evidence' => false,
        'status' => ClarificationAnswerStatus::Pending,
    ]);
}

it('previews a built proposal without mutating the profile', function () {
    $finding = reviewFinding($this->analysis, $this->candidateSkill);
    $question = reviewQuestion($this->analysis, $finding);
    $answer = reviewPendingAnswer($this->user, $question);

    $result = $this->action->execute($this->user, $question, null);

    expect($result['proposal']->status)->toBe(ClarificationProposalStatus::Proposed)
        ->and($result['proposal']->after_value['state'])->toBe(SkillState::Verified->value)
        ->and($result['answer']->status)->toBe(ClarificationAnswerStatus::Pending)
        ->and($answer->fresh()->proposal_id)->toBe($result['proposal']->id);

    expect($this->candidateSkill->fresh()->state)->toBe(SkillState::Claimed)
        ->and(CandidateSkill::count())->toBe(1);
});

it('accepts a verified proposal, dispatches the staleness job, and writes the audit event', function () {
    Queue::fake();

    $finding = reviewFinding($this->analysis, $this->candidateSkill);
    $question = reviewQuestion($this->analysis, $finding);
    $answer = reviewPendingAnswer($this->user, $question);

    $result = $this->action->execute($this->user, $question, 'accept');

    expect($result['proposal']->status)->toBe(ClarificationProposalStatus::Accepted)
        ->and($result['answer']->status)->toBe(ClarificationAnswerStatus::Accepted);

    $skill = $this->candidateSkill->fresh();
    expect($skill->state)->toBe(SkillState::Verified)
        ->and($skill->evidence)->toHaveCount(1)
        ->and($skill->evidence[0]['value'])->toBe('https://example.com/cert')
        ->and($skill->evidence[0]['origin_answer_id'])->toBe($answer->id);

    Queue::assertPushed(ObserveClarificationStalenessJob::class, fn ($job) => $job->analysisId === $this->analysis->id);

    $audit = ClarificationAuditEvent::where('proposal_id', $result['proposal']->id)->where('event', 'proposal_accepted')->first();
    expect($audit)->not->toBeNull()
        ->and($audit->target_type)->toBe(ClarificationTargetType::CandidateSkill)
        ->and($audit->target_id)->toBe($this->candidateSkill->id)
        ->and($audit->field)->toBe(ClarificationProposalField::STATE)
        ->and($audit->after_value['state'])->toBe(SkillState::Verified->value);
});

it('edits the years of experience and applies the edited value', function () {
    $this->candidateSkill->update(['years_experience' => 1.0]);
    $finding = reviewFinding($this->analysis, $this->candidateSkill);
    $question = ClarificationQuestion::factory()->number()->create([
        'match_analysis_id' => $this->analysis->id,
        'match_finding_id' => $finding->id,
        'question_no' => 1,
    ]);
    $answer = reviewPendingAnswer($this->user, $question, ClarificationAnswerType::Number);

    $result = $this->action->execute($this->user, $question, 'edit', '5');

    expect($result['proposal']->after_value['years_experience'])->toEqual(5.0)
        ->and($result['proposal']->status)->toBe(ClarificationProposalStatus::Accepted)
        ->and($answer->fresh()->status)->toBe(ClarificationAnswerStatus::Accepted);

    expect($this->candidateSkill->fresh()->years_experience)->toEqual(5.0);
});

it('edits the evidence URL of a verified proposal and applies the edited evidence', function () {
    $finding = reviewFinding($this->analysis, $this->candidateSkill);
    $question = reviewQuestion($this->analysis, $finding);
    $answer = reviewPendingAnswer($this->user, $question);

    $result = $this->action->execute($this->user, $question, 'edit', 'https://example.com/new-cert');

    expect($result['proposal']->status)->toBe(ClarificationProposalStatus::Accepted)
        ->and($result['proposal']->after_value['state'])->toBe(SkillState::Verified->value)
        ->and($result['proposal']->after_value['evidence'][0]['value'])->toBe('https://example.com/new-cert')
        ->and($answer->fresh()->status)->toBe(ClarificationAnswerStatus::Accepted);

    $skill = $this->candidateSkill->fresh();
    expect($skill->state)->toBe(SkillState::Verified)
        ->and($skill->evidence[0]['value'])->toBe('https://example.com/new-cert');
});

it('rejects the proposal and records the rejection without a profile change', function () {
    $finding = reviewFinding($this->analysis, $this->candidateSkill);
    $question = reviewQuestion($this->analysis, $finding);
    $answer = reviewPendingAnswer($this->user, $question);

    $result = $this->action->execute($this->user, $question, 'reject');

    expect($result['proposal']->status)->toBe(ClarificationProposalStatus::Rejected)
        ->and($result['answer']->status)->toBe(ClarificationAnswerStatus::Rejected)
        ->and($answer->fresh()->status)->toBe(ClarificationAnswerStatus::Rejected);

    expect($this->candidateSkill->fresh()->state)->toBe(SkillState::Claimed)
        ->and(CandidateSkill::count())->toBe(1);
});

it('skips the proposal and records the skip without a profile change', function () {
    $finding = reviewFinding($this->analysis, $this->candidateSkill);
    $question = reviewQuestion($this->analysis, $finding);
    $answer = reviewPendingAnswer($this->user, $question);

    $result = $this->action->execute($this->user, $question, 'skip');

    expect($result['proposal']->status)->toBe(ClarificationProposalStatus::Skipped)
        ->and($result['answer']->status)->toBe(ClarificationAnswerStatus::Skipped)
        ->and($answer->fresh()->status)->toBe(ClarificationAnswerStatus::Skipped);

    expect($this->candidateSkill->fresh()->state)->toBe(SkillState::Claimed);
});

it('throws a not-found exception when no answer exists for the question', function () {
    $finding = reviewFinding($this->analysis, $this->candidateSkill);
    $question = reviewQuestion($this->analysis, $finding);

    $this->action->execute($this->user, $question, 'accept');
})->throws(NotFoundException::class, 'No answer exists for this clarification question.');

it('throws for an unknown review decision', function () {
    $finding = reviewFinding($this->analysis, $this->candidateSkill);
    $question = reviewQuestion($this->analysis, $finding);
    reviewPendingAnswer($this->user, $question);

    $this->action->execute($this->user, $question, 'approve');
})->throws(UnprocessableEntityException::class, 'Unknown review decision.');

it('throws when editing without an edited value', function () {
    $finding = reviewFinding($this->analysis, $this->candidateSkill);
    $question = reviewQuestion($this->analysis, $finding);
    reviewPendingAnswer($this->user, $question);

    $this->action->execute($this->user, $question, 'edit', null);
})->throws(UnprocessableEntityException::class, 'An edited value is required to review with an edit.');

it('throws when editing years with a non-numeric value', function () {
    $finding = reviewFinding($this->analysis, $this->candidateSkill);
    $question = ClarificationQuestion::factory()->number()->create([
        'match_analysis_id' => $this->analysis->id,
        'match_finding_id' => $finding->id,
        'question_no' => 1,
    ]);
    reviewPendingAnswer($this->user, $question, ClarificationAnswerType::Number);

    $this->action->execute($this->user, $question, 'edit', 'abc');
})->throws(UnprocessableEntityException::class, 'The years of experience must be a number.');

it('throws when editing the evidence with an invalid URL', function () {
    $finding = reviewFinding($this->analysis, $this->candidateSkill);
    $question = reviewQuestion($this->analysis, $finding);
    reviewPendingAnswer($this->user, $question);

    $this->action->execute($this->user, $question, 'edit', 'not-a-url');
})->throws(UnprocessableEntityException::class, 'The edited evidence must be a valid URL.');

it('throws when the proposal can no longer be reviewed', function () {
    $finding = reviewFinding($this->analysis, $this->candidateSkill);
    $question = reviewQuestion($this->analysis, $finding);
    $answer = reviewPendingAnswer($this->user, $question);
    $proposal = ClarificationProposal::factory()->accepted()->create([
        'answer_id' => $answer->id,
        'target_type' => ClarificationTargetType::CandidateSkill,
        'target_id' => $this->candidateSkill->id,
        'field' => ClarificationProposalField::STATE,
        'after_value' => ['state' => SkillState::Verified->value],
    ]);
    $answer->update(['proposal_id' => $proposal->id]);

    $this->action->execute($this->user, $question, 'reject');
})->throws(ConflictException::class, 'This proposal can no longer be reviewed.');
