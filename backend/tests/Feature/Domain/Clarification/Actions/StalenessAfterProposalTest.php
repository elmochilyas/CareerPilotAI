<?php

use App\Domain\Clarification\Actions\ApplyProposalAction;
use App\Domain\Clarification\Enums\ClarificationAnswerStatus;
use App\Domain\Clarification\Enums\ClarificationAnswerType;
use App\Domain\Clarification\Enums\ClarificationProposalField;
use App\Domain\Clarification\Enums\ClarificationProposalStatus;
use App\Domain\Clarification\Enums\ClarificationTargetType;
use App\Domain\Matching\Enums\MatchAnalysisStatus;
use App\Domain\Matching\Enums\MatchCategory;
use App\Domain\Matching\Enums\RequirementSourceType;
use App\Domain\Matching\Services\FingerprintService;
use App\Domain\Matching\Services\OpportunitySnapshot;
use App\Domain\Matching\Services\ProfileSnapshot;
use App\Domain\Matching\Services\StalenessService;
use App\Domain\Skills\Enums\SkillState;
use App\Exceptions\Api\ConflictException;
use App\Jobs\ObserveClarificationStalenessJob;
use App\Models\CandidateProfile;
use App\Models\CandidateSkill;
use App\Models\ClarificationAnswer;
use App\Models\ClarificationProposal;
use App\Models\ClarificationQuestion;
use App\Models\JobOpportunity;
use App\Models\MatchAnalysis;
use App\Models\MatchFinding;
use App\Models\MatchScore;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class)->group('clarifications', 'staleness');

beforeEach(function () {
    Queue::fake();

    $this->user = User::factory()->create();
    $this->profile = CandidateProfile::factory()->create(['user_id' => $this->user->id]);
    $this->opportunity = JobOpportunity::factory()->create();
    $this->skill = Skill::factory()->create();
    $this->candidateSkill = CandidateSkill::factory()->claimed()->create([
        'candidate_profile_id' => $this->profile->id,
        'skill_id' => $this->skill->id,
    ]);
    $this->analysis = MatchAnalysis::factory()->completed()->create([
        'candidate_profile_id' => $this->profile->id,
        'job_opportunity_id' => $this->opportunity->id,
    ]);

    $this->action = app(ApplyProposalAction::class);
    $this->staleness = app(StalenessService::class);
    $this->fingerprints = app(FingerprintService::class);
});

function stalenessMarkFresh(MatchAnalysis $analysis, CandidateProfile $profile, JobOpportunity $opportunity, FingerprintService $fingerprints): void
{
    $profile->timestamps = false;
    $profile->updated_at = now()->subMinutes(5);
    $profile->save();

    $analysis->forceFill([
        'profile_fingerprint' => $fingerprints->profile(ProfileSnapshot::fromCandidateProfile($profile)),
        'opportunity_fingerprint' => $fingerprints->opportunity(OpportunitySnapshot::fromJobOpportunity($opportunity)),
        'profile_updated_at' => $profile->updated_at,
        'opportunity_updated_at' => $opportunity->updated_at,
    ])->save();

    $analysis->refresh();
    $profile->refresh();
}

function stalenessFinding(MatchAnalysis $analysis, CandidateSkill $candidateSkill): MatchFinding
{
    return MatchFinding::factory()->partial()->create([
        'match_analysis_id' => $analysis->id,
        'category' => MatchCategory::RequiredSkills->value,
        'source_type' => RequirementSourceType::JobOpportunitySkill,
        'source_id' => 777777,
        'matched_candidate_skill_id' => $candidateSkill->id,
    ]);
}

function stalenessQuestion(MatchAnalysis $analysis, MatchFinding $finding): ClarificationQuestion
{
    return ClarificationQuestion::factory()->yesNoWithDetails()->create([
        'match_analysis_id' => $analysis->id,
        'match_finding_id' => $finding->id,
        'question_no' => 1,
    ]);
}

function stalenessPendingAnswer(User $user, ClarificationQuestion $question): ClarificationAnswer
{
    return ClarificationAnswer::factory()->create([
        'user_id' => $user->id,
        'question_id' => $question->id,
        'answer_type' => ClarificationAnswerType::Yes,
        'value' => 'yes',
        'acknowledged_no_evidence' => false,
        'status' => ClarificationAnswerStatus::Pending,
    ]);
}

function stalenessVerifiedProposal(ClarificationAnswer $answer, CandidateSkill $candidateSkill): ClarificationProposal
{
    $proposal = ClarificationProposal::factory()->create([
        'answer_id' => $answer->id,
        'target_type' => ClarificationTargetType::CandidateSkill,
        'target_id' => $candidateSkill->id,
        'field' => ClarificationProposalField::STATE,
        'before_value' => ['state' => SkillState::Claimed->value],
        'after_value' => [
            'state' => SkillState::Verified->value,
            'evidence' => [['type' => 'url', 'value' => 'https://example.com/cert']],
        ],
        'status' => ClarificationProposalStatus::Proposed,
    ]);

    $answer->update(['proposal_id' => $proposal->id]);

    return $proposal;
}

it('reports a fresh analysis as not stale before any proposal is applied', function () {
    stalenessMarkFresh($this->analysis, $this->profile, $this->opportunity, $this->fingerprints);

    expect($this->staleness->isStale($this->analysis, $this->profile, $this->opportunity))->toBeFalse()
        ->and($this->staleness->differingVersions($this->analysis, $this->profile, $this->opportunity))->toBe([
            'profile' => false,
            'opportunity' => false,
        ]);
});

it('flags the analysis stale after an accepted proposal without recomputing the score in the mutation', function () {
    stalenessMarkFresh($this->analysis, $this->profile, $this->opportunity, $this->fingerprints);

    MatchScore::factory()->create(['match_analysis_id' => $this->analysis->id, 'score' => 88]);
    MatchScore::factory()->create([
        'match_analysis_id' => $this->analysis->id,
        'category' => MatchCategory::Evidence,
        'score' => 12,
    ]);
    $scoreCount = MatchScore::count();
    $scoreValues = MatchScore::orderBy('category')->pluck('score')->all();
    $profileUpdatedAt = $this->profile->updated_at;

    $finding = stalenessFinding($this->analysis, $this->candidateSkill);
    $question = stalenessQuestion($this->analysis, $finding);
    $answer = stalenessPendingAnswer($this->user, $question);
    $proposal = stalenessVerifiedProposal($answer, $this->candidateSkill);

    $result = $this->action->execute($proposal);

    expect($result->status)->toBe(ClarificationProposalStatus::Accepted);

    $analysis = $this->analysis->fresh();
    $profile = $this->profile->fresh();

    expect($analysis->status)->toBe(MatchAnalysisStatus::Completed)
        ->and($analysis->overall_score)->toBe(78);

    expect(MatchScore::count())->toBe($scoreCount)
        ->and(MatchScore::orderBy('category')->pluck('score')->all())->toBe($scoreValues)
        ->and(MatchScore::where('match_analysis_id', $this->analysis->id)->where('category', MatchCategory::Evidence)->value('score'))->toBe(12);

    expect($profile->updated_at->notEqualTo($profileUpdatedAt))->toBeTrue();

    expect($this->staleness->isStale($analysis, $profile, $this->opportunity))->toBeTrue()
        ->and($this->staleness->differingVersions($analysis, $profile, $this->opportunity))->toBe([
            'profile' => true,
            'opportunity' => false,
        ]);

    Queue::assertPushed(ObserveClarificationStalenessJob::class, fn ($job) => $job->analysisId === $this->analysis->id);
});

it('leaves the analysis fresh when a proposal cannot be applied', function () {
    stalenessMarkFresh($this->analysis, $this->profile, $this->opportunity, $this->fingerprints);

    $finding = stalenessFinding($this->analysis, $this->candidateSkill);
    $question = stalenessQuestion($this->analysis, $finding);
    $answer = stalenessPendingAnswer($this->user, $question);
    $proposal = ClarificationProposal::factory()->rejected()->create([
        'answer_id' => $answer->id,
        'target_type' => ClarificationTargetType::CandidateSkill,
        'target_id' => $this->candidateSkill->id,
        'field' => ClarificationProposalField::STATE,
        'after_value' => ['state' => SkillState::Rejected->value],
    ]);

    $profileUpdatedAt = $this->profile->updated_at;

    expect(fn () => $this->action->execute($proposal))->toThrow(ConflictException::class);

    $profile = $this->profile->fresh();

    expect($profile->updated_at->equalTo($profileUpdatedAt))->toBeTrue()
        ->and($this->staleness->isStale($this->analysis->fresh(), $profile, $this->opportunity))->toBeFalse();
});
