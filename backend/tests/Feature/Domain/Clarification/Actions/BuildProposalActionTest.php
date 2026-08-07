<?php

use App\Domain\Clarification\Actions\BuildProposalAction;
use App\Domain\Clarification\Enums\ClarificationAnswerType;
use App\Domain\Clarification\Enums\ClarificationProposalField;
use App\Domain\Clarification\Enums\ClarificationProposalStatus;
use App\Domain\Clarification\Enums\ClarificationTargetType;
use App\Domain\Matching\Enums\MatchCategory;
use App\Domain\Matching\Enums\RequirementSourceType;
use App\Domain\Skills\Enums\SkillState;
use App\Exceptions\Api\UnprocessableEntityException;
use App\Models\CandidateProfile;
use App\Models\CandidateSkill;
use App\Models\ClarificationAnswer;
use App\Models\ClarificationProposal;
use App\Models\ClarificationQuestion;
use App\Models\JobOpportunitySkill;
use App\Models\MatchAnalysis;
use App\Models\MatchFinding;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
    $this->action = app(BuildProposalAction::class);
});

function skillFinding(MatchAnalysis $analysis, CandidateSkill $candidateSkill): MatchFinding
{
    return MatchFinding::factory()->partial()->create([
        'match_analysis_id' => $analysis->id,
        'category' => MatchCategory::RequiredSkills->value,
        'source_type' => RequirementSourceType::JobOpportunitySkill,
        'source_id' => 999999,
        'matched_candidate_skill_id' => $candidateSkill->id,
    ]);
}

function questionFor(MatchAnalysis $analysis, MatchFinding $finding): ClarificationQuestion
{
    return ClarificationQuestion::factory()->yesNoWithDetails()->create([
        'match_analysis_id' => $analysis->id,
        'match_finding_id' => $finding->id,
        'question_no' => 1,
    ]);
}

it('builds a verified proposal from a yes answer with evidence', function () {
    $finding = skillFinding($this->analysis, $this->candidateSkill);
    $question = questionFor($this->analysis, $finding);
    $answer = ClarificationAnswer::factory()->create([
        'user_id' => $this->user->id,
        'question_id' => $question->id,
        'answer_type' => ClarificationAnswerType::Yes,
        'value' => 'https://example.com/cert',
        'acknowledged_no_evidence' => false,
    ]);

    $proposal = $this->action->execute($answer);

    expect($proposal->answer_id)->toBe($answer->id)
        ->and($proposal->target_type)->toBe(ClarificationTargetType::CandidateSkill)
        ->and($proposal->target_id)->toBe($this->candidateSkill->id)
        ->and($proposal->field)->toBe(ClarificationProposalField::STATE)
        ->and($proposal->before_value)->toBe(['state' => SkillState::Claimed->value])
        ->and($proposal->after_value['state'])->toBe(SkillState::Verified->value)
        ->and($proposal->after_value['evidence'])->toBe([['type' => 'url', 'value' => 'https://example.com/cert']])
        ->and($proposal->status)->toBe(ClarificationProposalStatus::Proposed);
});

it('builds a claimed proposal for a no-evidence acknowledgement, never verified', function () {
    $finding = skillFinding($this->analysis, $this->candidateSkill);
    $question = questionFor($this->analysis, $finding);
    $answer = ClarificationAnswer::factory()->create([
        'user_id' => $this->user->id,
        'question_id' => $question->id,
        'answer_type' => ClarificationAnswerType::Yes,
        'value' => 'yes',
        'acknowledged_no_evidence' => true,
    ]);

    $proposal = $this->action->execute($answer);

    expect($proposal->after_value['state'])->toBe(SkillState::Claimed->value)
        ->and($proposal->after_value['acknowledged_no_evidence'])->toBeTrue()
        ->and($proposal->after_value)->not->toHaveKey('evidence');
});

it('rejects a plain yes without evidence and without acknowledgement', function () {
    $finding = skillFinding($this->analysis, $this->candidateSkill);
    $question = questionFor($this->analysis, $finding);
    $answer = ClarificationAnswer::factory()->create([
        'user_id' => $this->user->id,
        'question_id' => $question->id,
        'answer_type' => ClarificationAnswerType::Yes,
        'value' => 'yes',
        'acknowledged_no_evidence' => false,
    ]);

    $this->action->execute($answer);
})->throws(UnprocessableEntityException::class, 'A verified skill requires evidence or an explicit no-evidence acknowledgement.');

it('builds a rejected proposal from a no answer', function () {
    $finding = skillFinding($this->analysis, $this->candidateSkill);
    $question = questionFor($this->analysis, $finding);
    $answer = ClarificationAnswer::factory()->no()->create([
        'user_id' => $this->user->id,
        'question_id' => $question->id,
    ]);

    $proposal = $this->action->execute($answer);

    expect($proposal->after_value['state'])->toBe(SkillState::Rejected->value);
});

it('builds a rejected proposal from a no-with-ack answer', function () {
    $finding = skillFinding($this->analysis, $this->candidateSkill);
    $question = questionFor($this->analysis, $finding);
    $answer = ClarificationAnswer::factory()->withAck()->create([
        'user_id' => $this->user->id,
        'question_id' => $question->id,
    ]);

    $proposal = $this->action->execute($answer);

    expect($proposal->after_value['state'])->toBe(SkillState::Rejected->value)
        ->and($proposal->after_value['acknowledged_no_evidence'])->toBeTrue();
});

it('builds a years-experience proposal from a number answer', function () {
    $finding = skillFinding($this->analysis, $this->candidateSkill);
    $question = ClarificationQuestion::factory()->number()->create([
        'match_analysis_id' => $this->analysis->id,
        'match_finding_id' => $finding->id,
        'question_no' => 1,
    ]);
    $answer = ClarificationAnswer::factory()->create([
        'user_id' => $this->user->id,
        'question_id' => $question->id,
        'answer_type' => ClarificationAnswerType::Number,
        'value' => '3',
    ]);

    $proposal = $this->action->execute($answer);

    expect($proposal->field)->toBe(ClarificationProposalField::STATE)
        ->and((float) $proposal->after_value['years_experience'])->toBe(3.0);
});

it('targets a new skill and carries the skill reference when none exists', function () {
    $opportunitySkill = JobOpportunitySkill::create([
        'job_opportunity_id' => $this->analysis->job_opportunity_id,
        'skill_id' => $this->skill->id,
        'original_label' => $this->skill->name,
        'classification' => 'required',
        'proficiency' => null,
        'years_experience' => null,
        'source_evidence' => null,
        'display_order' => 0,
    ]);

    $finding = MatchFinding::factory()->gap()->create([
        'match_analysis_id' => $this->analysis->id,
        'category' => MatchCategory::RequiredSkills->value,
        'source_type' => RequirementSourceType::JobOpportunitySkill,
        'source_id' => $opportunitySkill->id,
        'matched_candidate_skill_id' => null,
    ]);
    $question = questionFor($this->analysis, $finding);
    $answer = ClarificationAnswer::factory()->create([
        'user_id' => $this->user->id,
        'question_id' => $question->id,
        'answer_type' => ClarificationAnswerType::Yes,
        'value' => 'https://example.com/portfolio',
        'acknowledged_no_evidence' => false,
    ]);

    $proposal = $this->action->execute($answer);

    expect($proposal->target_type)->toBe(ClarificationTargetType::CandidateSkill)
        ->and($proposal->target_id)->toBeNull()
        ->and($proposal->before_value)->toBeNull()
        ->and($proposal->after_value['state'])->toBe(SkillState::Verified->value)
        ->and($proposal->after_value['skill_id'])->toBe($this->skill->id);
});

it('fails safely when a requirement has no resolvable skill', function () {
    $finding = MatchFinding::factory()->gap()->create([
        'match_analysis_id' => $this->analysis->id,
        'category' => MatchCategory::RequiredSkills->value,
        'source_type' => RequirementSourceType::JobRequirement,
        'source_id' => 424242,
        'matched_candidate_skill_id' => null,
    ]);
    $question = questionFor($this->analysis, $finding);
    $answer = ClarificationAnswer::factory()->create([
        'user_id' => $this->user->id,
        'question_id' => $question->id,
        'answer_type' => ClarificationAnswerType::Yes,
        'value' => 'https://example.com/cert',
        'acknowledged_no_evidence' => false,
    ]);

    $this->action->execute($answer);
})->throws(UnprocessableEntityException::class, 'This clarification question does not support a profile change.');

it('fails safely for non-skill findings', function () {
    $finding = MatchFinding::factory()->gap()->create([
        'match_analysis_id' => $this->analysis->id,
        'category' => MatchCategory::ExperienceEducation->value,
        'source_type' => RequirementSourceType::JobRequirement,
        'source_id' => 424243,
        'matched_candidate_skill_id' => null,
    ]);
    $question = questionFor($this->analysis, $finding);
    $answer = ClarificationAnswer::factory()->no()->create([
        'user_id' => $this->user->id,
        'question_id' => $question->id,
    ]);

    $this->action->execute($answer);
})->throws(UnprocessableEntityException::class, 'This clarification question does not support a profile change.');

it('returns the existing proposal for the answer', function () {
    $finding = skillFinding($this->analysis, $this->candidateSkill);
    $question = questionFor($this->analysis, $finding);
    $answer = ClarificationAnswer::factory()->yes()->create([
        'user_id' => $this->user->id,
        'question_id' => $question->id,
    ]);
    $existing = ClarificationProposal::factory()->create([
        'answer_id' => $answer->id,
    ]);
    $answer->update(['proposal_id' => $existing->id]);

    $proposal = $this->action->execute($answer->fresh());

    expect($proposal->id)->toBe($existing->id);
    expect(ClarificationProposal::count())->toBe(1);
});

it('rejects building a proposal for a non-pending answer', function () {
    $finding = skillFinding($this->analysis, $this->candidateSkill);
    $question = questionFor($this->analysis, $finding);
    $answer = ClarificationAnswer::factory()->accepted()->create([
        'user_id' => $this->user->id,
        'question_id' => $question->id,
    ]);

    $this->action->execute($answer);
})->throws(UnprocessableEntityException::class, 'This answer can no longer produce a proposal.');
