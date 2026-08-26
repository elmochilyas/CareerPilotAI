<?php

use App\Domain\Clarification\Actions\ApplyProposalAction;
use App\Domain\Clarification\Actions\BuildProposalAction;
use App\Domain\Clarification\Actions\BuildQuestionSessionAction;
use App\Domain\Clarification\Actions\CreateAnswerAction;
use App\Domain\Clarification\Actions\ReviewAnswerAction;
use App\Domain\Clarification\Actions\SkipQuestionAction;
use App\Domain\Clarification\Enums\ClarificationAnswerStatus;
use App\Domain\Clarification\Enums\ClarificationAnswerType;
use App\Domain\Clarification\Enums\ClarificationProposalField;
use App\Domain\Clarification\Enums\ClarificationProposalStatus;
use App\Domain\Clarification\Enums\ClarificationTargetType;
use App\Domain\Clarification\Services\ClarificationAuditWriter;
use App\Domain\Matching\Enums\MatchCategory;
use App\Domain\Matching\Enums\RequirementSourceType;
use App\Exceptions\Api\UnprocessableEntityException;
use App\Models\CandidateProfile;
use App\Models\CandidateSkill;
use App\Models\ClarificationAnswer;
use App\Models\ClarificationAuditEvent;
use App\Models\ClarificationProposal;
use App\Models\ClarificationQuestion;
use App\Models\JobOpportunity;
use App\Models\JobOpportunitySkill;
use App\Models\MatchAnalysis;
use App\Models\MatchFinding;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class)->group('clarifications', 'audit-target');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->profile = CandidateProfile::factory()->create(['user_id' => $this->user->id]);
    $this->analysis = MatchAnalysis::factory()->completed()->create([
        'candidate_profile_id' => $this->profile->id,
    ]);
    $this->skill = Skill::factory()->create(['normalized_name' => 'php']);
});

function auditSkillFinding(MatchAnalysis $analysis): MatchFinding
{
    $jobOpp = JobOpportunity::factory()->create(['candidate_profile_id' => $analysis->candidate_profile_id]);
    $skill = Skill::factory()->create(['normalized_name' => 'php-'.uniqid()]);
    $oppSkill = JobOpportunitySkill::create([
        'job_opportunity_id' => $jobOpp->id,
        'skill_id' => $skill->id,
        'classification' => 'required',
        'original_label' => $skill->name,
        'display_order' => 0,
    ]);

    return MatchFinding::factory()->partial()->create([
        'match_analysis_id' => $analysis->id,
        'category' => MatchCategory::RequiredSkills->value,
        'source_type' => RequirementSourceType::JobOpportunitySkill,
        'source_id' => $oppSkill->id,
        'requirement_text' => 'PHP required',
    ]);
}

it('rejects unsupported profile_item target with 422 proposal_not_supported, mutates nothing, and audits apply failure', function () {
    $finding = auditSkillFinding($this->analysis);
    $question = ClarificationQuestion::factory()->yesNoWithDetails()->create([
        'match_analysis_id' => $this->analysis->id,
        'match_finding_id' => $finding->id,
    ]);
    $answer = ClarificationAnswer::factory()->create([
        'user_id' => $this->user->id,
        'question_id' => $question->id,
        'answer_type' => ClarificationAnswerType::Yes,
        'value' => 'yes',
        'acknowledged_no_evidence' => true,
        'status' => ClarificationAnswerStatus::Pending,
    ]);
    // Manually craft a proposal that pretends to target a profile_item
    $proposal = ClarificationProposal::factory()->create([
        'answer_id' => $answer->id,
        'target_type' => ClarificationTargetType::ProfileItem,
        'target_id' => 999,
        'field' => ClarificationProposalField::STATE,
        'before_value' => null,
        'after_value' => ['state' => 'claimed'],
        'status' => ClarificationProposalStatus::Proposed,
    ]);
    $answer->update(['proposal_id' => $proposal->id]);

    $beforeCount = CandidateSkill::where('candidate_profile_id', $this->profile->id)->count();
    $beforeSkills = CandidateSkill::where('candidate_profile_id', $this->profile->id)->pluck('state')->all();

    $action = app(ApplyProposalAction::class);

    try {
        $action->execute($proposal);
        $this->fail('Expected UnprocessableEntityException');
    } catch (UnprocessableEntityException $e) {
        expect($e->getErrorCode())->toBe('proposal_not_supported');
        expect($e->getMessage())->toContain('does not support');
    }

    expect(CandidateSkill::where('candidate_profile_id', $this->profile->id)->count())->toBe($beforeCount);
    expect(CandidateSkill::where('candidate_profile_id', $this->profile->id)->pluck('state')->all())->toEqual($beforeSkills);

    // Apply failure must be audited append-only
    expect(ClarificationAuditEvent::where('event', 'proposal_apply_failed')->where('proposal_id', $proposal->id)->exists())->toBeTrue();
    $audit = ClarificationAuditEvent::where('event', 'proposal_apply_failed')->where('proposal_id', $proposal->id)->first();
    expect($audit->metadata['code'] ?? null)->toBe('proposal_not_supported');
});

it('records append-only audit events for question created, answer submitted, proposal generated, accepted, rejected, skipped', function () {
    // Question created
    $finding = auditSkillFinding($this->analysis);
    $buildSession = app(BuildQuestionSessionAction::class);
    $createdQuestions = $buildSession->execute($this->analysis);
    expect($createdQuestions)->not->toBeEmpty();
    $question = $createdQuestions[0];
    expect(ClarificationAuditEvent::where('event', 'question_created')->where('target_id', $question->id)->exists())->toBeTrue();

    // Answer submitted
    $answerAction = app(CreateAnswerAction::class);
    $answer = $answerAction->execute($this->user, $question, ClarificationAnswerType::Yes, 'https://example.com/evidence');
    expect(ClarificationAuditEvent::where('event', 'answer_submitted')->where('answer_id', $answer->id)->exists())->toBeTrue();

    // Proposal generated
    $buildProposal = app(BuildProposalAction::class);
    $proposal = $buildProposal->execute($answer);
    expect(ClarificationAuditEvent::where('event', 'proposal_generated')->where('proposal_id', $proposal->id)->exists())->toBeTrue();

    // Proposal accepted (via ReviewAnswerAction accept -> ApplyProposalAction -> event)
    $review = app(ReviewAnswerAction::class);
    $result = $review->execute($this->user, $question, 'accept');
    expect($result['proposal']->status)->toBe(ClarificationProposalStatus::Accepted);
    expect(ClarificationAuditEvent::where('event', 'proposal_accepted')->where('proposal_id', $proposal->id)->exists())->toBeTrue();

    // New question for reject path
    $finding2 = auditSkillFinding($this->analysis);
    $q2 = ClarificationQuestion::factory()->yesNoWithDetails()->create([
        'match_analysis_id' => $this->analysis->id,
        'match_finding_id' => $finding2->id,
    ]);
    $a2 = $answerAction->execute($this->user, $q2, ClarificationAnswerType::Yes, 'https://example.com/evidence2');
    $p2 = $buildProposal->execute($a2);
    $review->execute($this->user, $q2, 'reject');
    expect(ClarificationAuditEvent::where('event', 'proposal_rejected')->where('proposal_id', $p2->id)->exists())->toBeTrue();

    // Question skipped (direct SkipQuestionAction)
    $finding3 = auditSkillFinding($this->analysis);
    $q3 = ClarificationQuestion::factory()->yesNoWithDetails()->create([
        'match_analysis_id' => $this->analysis->id,
        'match_finding_id' => $finding3->id,
    ]);
    $skip = app(SkipQuestionAction::class);
    $skip->execute($q3);
    expect(ClarificationAuditEvent::where('event', 'question_skipped')->exists())->toBeTrue();
});

it('does not roll back trusted mutation when audit writer fails (best-effort)', function () {
    // Force audit writer to fail by mocking it to throw, but mutation must still commit
    $finding = auditSkillFinding($this->analysis);
    $question = ClarificationQuestion::factory()->yesNoWithDetails()->create([
        'match_analysis_id' => $this->analysis->id,
        'match_finding_id' => $finding->id,
    ]);
    $candidateSkill = CandidateSkill::factory()->claimed()->create([
        'candidate_profile_id' => $this->profile->id,
        'skill_id' => $this->skill->id,
    ]);
    $finding->update(['matched_candidate_skill_id' => $candidateSkill->id]);

    $answer = ClarificationAnswer::factory()->create([
        'user_id' => $this->user->id,
        'question_id' => $question->id,
        'answer_type' => ClarificationAnswerType::Yes,
        'value' => 'https://example.com/evidence',
        'acknowledged_no_evidence' => false,
        'status' => ClarificationAnswerStatus::Pending,
    ]);
    $buildProposal = app(BuildProposalAction::class);
    $proposal = $buildProposal->execute($answer);

    // Mock writer to throw inside the listener path
    $originalWriter = app(ClarificationAuditWriter::class);
    $throwingWriter = new class extends ClarificationAuditWriter
    {
        public function write(string $event, array $attributes): void
        {
            throw new RuntimeException('audit storage failure');
        }
    };
    app()->instance(ClarificationAuditWriter::class, $throwingWriter);

    $apply = app(ApplyProposalAction::class);
    $result = $apply->execute($proposal);

    expect($result->status)->toBe(ClarificationProposalStatus::Accepted);
    expect(CandidateSkill::find($candidateSkill->id)->state->value)->toBe('verified');

    // Restore writer
    app()->instance(ClarificationAuditWriter::class, $originalWriter);
});

it('audit records never contain raw provider output or secrets', function () {
    $finding = auditSkillFinding($this->analysis);
    $question = ClarificationQuestion::factory()->yesNoWithDetails()->create([
        'match_analysis_id' => $this->analysis->id,
        'match_finding_id' => $finding->id,
    ]);
    $answer = app(CreateAnswerAction::class)->execute($this->user, $question, ClarificationAnswerType::Yes, 'https://example.com/evidence');
    $proposal = app(BuildProposalAction::class)->execute($answer);

    $audit = ClarificationAuditEvent::where('event', 'proposal_generated')->where('proposal_id', $proposal->id)->first();
    expect($audit)->not->toBeNull();
    $payload = json_encode($audit->toArray());
    expect($payload)->not->toContain('sk-');
    expect($payload)->not->toContain('provider_raw');
});
