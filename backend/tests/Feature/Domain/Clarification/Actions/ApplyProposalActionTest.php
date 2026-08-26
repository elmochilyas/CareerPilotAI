<?php

use App\Domain\Clarification\Actions\ApplyProposalAction;
use App\Domain\Clarification\Enums\ClarificationAnswerStatus;
use App\Domain\Clarification\Enums\ClarificationAnswerType;
use App\Domain\Clarification\Enums\ClarificationProposalField;
use App\Domain\Clarification\Enums\ClarificationProposalStatus;
use App\Domain\Clarification\Enums\ClarificationTargetType;
use App\Domain\Matching\Enums\MatchCategory;
use App\Domain\Matching\Enums\RequirementSourceType;
use App\Domain\Skills\Enums\SkillState;
use App\Exceptions\Api\ConflictException;
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
    $this->action = app(ApplyProposalAction::class);
});

function applyProposalFinding(MatchAnalysis $analysis, CandidateSkill $candidateSkill): MatchFinding
{
    return MatchFinding::factory()->partial()->create([
        'match_analysis_id' => $analysis->id,
        'category' => MatchCategory::RequiredSkills->value,
        'source_type' => RequirementSourceType::JobOpportunitySkill,
        'source_id' => 999999,
        'matched_candidate_skill_id' => $candidateSkill->id,
    ]);
}

function applyProposalQuestion(MatchAnalysis $analysis, MatchFinding $finding): ClarificationQuestion
{
    return ClarificationQuestion::factory()->yesNoWithDetails()->create([
        'match_analysis_id' => $analysis->id,
        'match_finding_id' => $finding->id,
        'question_no' => 1,
    ]);
}

function applyPendingAnswer(User $user, ClarificationQuestion $question, ?ClarificationAnswerType $answerType = null): ClarificationAnswer
{
    return ClarificationAnswer::factory()->create([
        'user_id' => $user->id,
        'question_id' => $question->id,
        'answer_type' => $answerType ?? ClarificationAnswerType::Yes,
        'value' => $answerType === ClarificationAnswerType::No ? 'no' : 'yes',
        'acknowledged_no_evidence' => $answerType === ClarificationAnswerType::NoWithAck,
        'status' => ClarificationAnswerStatus::Pending,
    ]);
}

function applyProposedSkillChange(ClarificationAnswer $answer, array $afterValue, ?int $targetId = null, ?ClarificationTargetType $targetType = null): ClarificationProposal
{
    $proposal = ClarificationProposal::factory()->create([
        'answer_id' => $answer->id,
        'target_type' => $targetType ?? ClarificationTargetType::CandidateSkill,
        'target_id' => $targetId,
        'field' => ClarificationProposalField::STATE,
        'before_value' => $targetId === null ? null : ['state' => SkillState::Claimed->value],
        'after_value' => $afterValue,
        'status' => ClarificationProposalStatus::Proposed,
    ]);

    $answer->update(['proposal_id' => $proposal->id]);

    return $proposal;
}

it('accepts a verified proposal for an existing skill, adds evidence, and marks the answer accepted', function () {
    $finding = applyProposalFinding($this->analysis, $this->candidateSkill);
    $question = applyProposalQuestion($this->analysis, $finding);
    $answer = applyPendingAnswer($this->user, $question);
    $proposal = applyProposedSkillChange($answer, [
        'state' => SkillState::Verified->value,
        'evidence' => [['type' => 'url', 'value' => 'https://example.com/cert']],
    ], $this->candidateSkill->id);

    $result = $this->action->execute($proposal);

    expect($result->status)->toBe(ClarificationProposalStatus::Accepted)
        ->and($answer->fresh()->status)->toBe(ClarificationAnswerStatus::Accepted)
        ->and($answer->fresh()->proposal_id)->toBe($proposal->id);

    $skill = $this->candidateSkill->fresh();
    expect($skill->state)->toBe(SkillState::Verified)
        ->and($skill->evidence)->toHaveCount(1)
        ->and($skill->evidence[0]['type'])->toBe('url')
        ->and($skill->evidence[0]['origin_answer_id'])->toBe($answer->id)
        ->and($skill->evidence[0]['origin_question_id'])->toBe($question->id);
});

it('applying an already accepted proposal is idempotent', function () {
    $finding = applyProposalFinding($this->analysis, $this->candidateSkill);
    $question = applyProposalQuestion($this->analysis, $finding);
    $answer = ClarificationAnswer::factory()->accepted()->create([
        'user_id' => $this->user->id,
        'question_id' => $question->id,
        'answer_type' => ClarificationAnswerType::Yes,
        'value' => 'yes',
    ]);
    $proposal = ClarificationProposal::factory()->accepted()->create([
        'answer_id' => $answer->id,
        'target_type' => ClarificationTargetType::CandidateSkill,
        'target_id' => $this->candidateSkill->id,
        'field' => ClarificationProposalField::STATE,
        'before_value' => ['state' => SkillState::Claimed->value],
        'after_value' => ['state' => SkillState::Verified->value],
    ]);
    $answer->update(['proposal_id' => $proposal->id]);

    $result = $this->action->execute($proposal->fresh());

    expect($result->status)->toBe(ClarificationProposalStatus::Accepted)
        ->and($this->candidateSkill->fresh()->state)->toBe(SkillState::Claimed)
        ->and(CandidateSkill::count())->toBe(1);
});

it('rejects a proposal that is no longer in proposed status', function () {
    $finding = applyProposalFinding($this->analysis, $this->candidateSkill);
    $question = applyProposalQuestion($this->analysis, $finding);
    $answer = applyPendingAnswer($this->user, $question);
    $proposal = ClarificationProposal::factory()->rejected()->create([
        'answer_id' => $answer->id,
        'target_type' => ClarificationTargetType::CandidateSkill,
        'target_id' => $this->candidateSkill->id,
        'field' => ClarificationProposalField::STATE,
        'after_value' => ['state' => SkillState::Rejected->value],
    ]);

    $this->action->execute($proposal);
})->throws(ConflictException::class, 'This proposal can no longer be reviewed.');

it('rejects a proposal whose answer is no longer open', function () {
    $finding = applyProposalFinding($this->analysis, $this->candidateSkill);
    $question = applyProposalQuestion($this->analysis, $finding);
    $answer = ClarificationAnswer::factory()->accepted()->create([
        'user_id' => $this->user->id,
        'question_id' => $question->id,
        'answer_type' => ClarificationAnswerType::Yes,
        'value' => 'yes',
    ]);
    $proposal = applyProposedSkillChange($answer, [
        'state' => SkillState::Verified->value,
        'evidence' => [['type' => 'url', 'value' => 'https://example.com/cert']],
    ], $this->candidateSkill->id);

    $this->action->execute($proposal);
})->throws(ConflictException::class, 'This answer is no longer reviewable.');

it('updates years experience on an existing skill', function () {
    $this->candidateSkill->update(['years_experience' => 1.0]);
    $finding = applyProposalFinding($this->analysis, $this->candidateSkill);
    $question = applyProposalQuestion($this->analysis, $finding);
    $answer = applyPendingAnswer($this->user, $question, ClarificationAnswerType::Number);
    $proposal = applyProposedSkillChange($answer, [
        'state' => SkillState::Claimed->value,
        'years_experience' => 5.0,
    ], $this->candidateSkill->id);

    $this->action->execute($proposal);

    expect($this->candidateSkill->fresh()->years_experience)->toEqual(5.0);
});

it('creates a claimed skill from a gap finding when no skill exists', function () {
    $newSkill = Skill::factory()->create();
    $finding = MatchFinding::factory()->gap()->create([
        'match_analysis_id' => $this->analysis->id,
        'category' => MatchCategory::RequiredSkills->value,
        'source_type' => RequirementSourceType::JobOpportunitySkill,
        'source_id' => 424242,
        'matched_candidate_skill_id' => null,
    ]);
    $question = applyProposalQuestion($this->analysis, $finding);
    $answer = applyPendingAnswer($this->user, $question);
    $proposal = applyProposedSkillChange($answer, [
        'state' => SkillState::Claimed->value,
        'skill_id' => $newSkill->id,
        'years_experience' => 2.0,
    ]);

    $this->action->execute($proposal);

    $created = CandidateSkill::where('candidate_profile_id', $this->profile->id)
        ->where('skill_id', $newSkill->id)
        ->first();
    expect($created)->not->toBeNull()
        ->and($created->state)->toBe(SkillState::Claimed)
        ->and($created->years_experience)->toEqual(2.0)
        ->and($created->proficiency_level)->toBeNull();
});

it('creates a verified skill from a gap finding with evidence', function () {
    $newSkill = Skill::factory()->create();
    $finding = MatchFinding::factory()->gap()->create([
        'match_analysis_id' => $this->analysis->id,
        'category' => MatchCategory::RequiredSkills->value,
        'source_type' => RequirementSourceType::JobOpportunitySkill,
        'source_id' => 424243,
        'matched_candidate_skill_id' => null,
    ]);
    $question = applyProposalQuestion($this->analysis, $finding);
    $answer = applyPendingAnswer($this->user, $question);
    $proposal = applyProposedSkillChange($answer, [
        'state' => SkillState::Verified->value,
        'skill_id' => $newSkill->id,
        'evidence' => [['type' => 'url', 'value' => 'https://example.com/portfolio']],
    ]);

    $this->action->execute($proposal);

    $created = CandidateSkill::where('candidate_profile_id', $this->profile->id)
        ->where('skill_id', $newSkill->id)
        ->first();
    expect($created)->not->toBeNull()
        ->and($created->state)->toBe(SkillState::Verified)
        ->and($created->evidence)->toHaveCount(1)
        ->and($created->evidence[0]['origin_answer_id'])->toBe($answer->id);
});

it('rejects a verified proposal without evidence', function () {
    $finding = applyProposalFinding($this->analysis, $this->candidateSkill);
    $question = applyProposalQuestion($this->analysis, $finding);
    $answer = applyPendingAnswer($this->user, $question);
    $proposal = applyProposedSkillChange($answer, [
        'state' => SkillState::Verified->value,
    ], $this->candidateSkill->id);

    $this->action->execute($proposal);
})->throws(UnprocessableEntityException::class, 'A verified skill requires evidence.');

it('rejects a proposal targeting a skill that does not belong to the profile', function () {
    $otherProfile = CandidateProfile::factory()->create();
    $foreignSkill = CandidateSkill::factory()->claimed()->create(['candidate_profile_id' => $otherProfile->id]);
    $finding = applyProposalFinding($this->analysis, $this->candidateSkill);
    $question = applyProposalQuestion($this->analysis, $finding);
    $answer = applyPendingAnswer($this->user, $question);
    $proposal = applyProposedSkillChange($answer, [
        'state' => SkillState::Verified->value,
        'evidence' => [['type' => 'url', 'value' => 'https://example.com/cert']],
    ], $foreignSkill->id);

    $this->action->execute($proposal);
})->throws(ConflictException::class, 'The target skill no longer exists for this profile.');

it('rejects unsupported proposal targets', function () {
    $finding = applyProposalFinding($this->analysis, $this->candidateSkill);
    $question = applyProposalQuestion($this->analysis, $finding);
    $answer = applyPendingAnswer($this->user, $question);
    $proposal = applyProposedSkillChange($answer, [
        'state' => SkillState::Verified->value,
    ], null, ClarificationTargetType::ProfileItem);

    $this->action->execute($proposal);
})->throws(UnprocessableEntityException::class, 'This proposal does not support a trusted profile change.');

it('dispatches the staleness observation job and writes the audit event after commit', function () {
    Queue::fake();

    $finding = applyProposalFinding($this->analysis, $this->candidateSkill);
    $question = applyProposalQuestion($this->analysis, $finding);
    $answer = applyPendingAnswer($this->user, $question);
    $proposal = applyProposedSkillChange($answer, [
        'state' => SkillState::Verified->value,
        'evidence' => [['type' => 'url', 'value' => 'https://example.com/cert']],
    ], $this->candidateSkill->id);

    $this->action->execute($proposal);

    Queue::assertPushed(ObserveClarificationStalenessJob::class, fn ($job) => $job->analysisId === $this->analysis->id);

    $audit = ClarificationAuditEvent::where('proposal_id', $proposal->id)->where('event', 'proposal_accepted')->first();
    expect($audit)->not->toBeNull()
        ->and($audit->answer_id)->toBe($answer->id)
        ->and($audit->user_id)->toBe($this->user->id)
        ->and($audit->match_analysis_id)->toBe($this->analysis->id)
        ->and($audit->target_type)->toBe(ClarificationTargetType::CandidateSkill)
        ->and($audit->target_id)->toBe($this->candidateSkill->id)
        ->and($audit->field)->toBe(ClarificationProposalField::STATE)
        ->and($audit->before_value)->toBe(['state' => SkillState::Claimed->value])
        ->and($audit->after_value['state'])->toBe(SkillState::Verified->value)
        ->and($audit->metadata)->toBe([
            'answer_type' => ClarificationAnswerType::Yes->value,
            'acknowledged_no_evidence' => false,
        ]);
});
