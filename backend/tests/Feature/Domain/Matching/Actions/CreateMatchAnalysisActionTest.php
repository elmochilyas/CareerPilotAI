<?php

use App\Domain\Matching\Actions\CreateMatchAnalysisAction;
use App\Domain\Matching\Enums\MatchAnalysisStatus;
use App\Domain\Skills\Enums\ProficiencyLevel;
use App\Domain\Skills\Enums\SkillState;
use App\Exceptions\Api\ConflictException;
use App\Exceptions\Api\UnprocessableEntityException;
use App\Jobs\ProcessMatchAnalysisJob;
use App\Models\CandidateProfile;
use App\Models\CandidateSkill;
use App\Models\JobOpportunity;
use App\Models\JobOpportunityIngestion;
use App\Models\JobOpportunitySkill;
use App\Models\JobRequirement;
use App\Models\MatchAnalysis;
use App\Models\Skill;
use App\Models\User;
use App\Support\RequestIdContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class)->group('matching', 'actions');

if (! function_exists('matchVerifiedSkill')) {
    function matchVerifiedSkill(CandidateProfile $profile, string $name = 'PHP'): CandidateSkill
    {
        $skill = Skill::firstOrCreate(
            ['normalized_name' => strtolower($name)],
            ['name' => $name, 'category' => 'language', 'is_active' => true],
        );

        return CandidateSkill::create([
            'candidate_profile_id' => $profile->id,
            'skill_id' => $skill->id,
            'state' => SkillState::Verified,
            'proficiency_level' => ProficiencyLevel::Expert,
            'years_experience' => 5.0,
            'evidence' => [['type' => 'url', 'value' => 'https://example.com/cert', 'label' => 'Certification']],
        ]);
    }
}

if (! function_exists('matchConfirmedOpportunity')) {
    function matchConfirmedOpportunity(CandidateProfile $profile, User $user, ?CandidateSkill $candidateSkill = null): JobOpportunity
    {
        $ingestion = JobOpportunityIngestion::factory()->confirmed()->create(['user_id' => $user->id]);

        $opportunity = JobOpportunity::factory()->create([
            'candidate_profile_id' => $profile->id,
            'ingestion_id' => $ingestion->id,
        ]);

        JobRequirement::create([
            'job_opportunity_id' => $opportunity->id,
            'category' => 'responsibility',
            'content' => 'Build and maintain REST APIs.',
            'classification' => 'required',
            'display_order' => 0,
        ]);

        if ($candidateSkill !== null) {
            JobOpportunitySkill::create([
                'job_opportunity_id' => $opportunity->id,
                'skill_id' => $candidateSkill->skill->id,
                'original_label' => $candidateSkill->skill->name,
                'classification' => 'required',
                'display_order' => 1,
            ]);
        }

        return $opportunity;
    }
}

beforeEach(function () {
    Queue::fake();
    $this->user = User::factory()->create();
    $this->profile = CandidateProfile::factory()->complete()->create(['user_id' => $this->user->id]);
    $this->phpSkill = matchVerifiedSkill($this->profile, 'PHP');
    $this->action = app(CreateMatchAnalysisAction::class);
});

it('creates a queued analysis and dispatches the match job', function () {
    $opportunity = matchConfirmedOpportunity($this->profile, $this->user, $this->phpSkill);

    $analysis = $this->action->execute($this->profile, $opportunity, 'client-key-1');

    expect($analysis->status)->toBe(MatchAnalysisStatus::Queued);
    expect($analysis->operation_key)->toBe(
        hash('sha256', $this->user->id.'|'.$opportunity->id.'|client-key-1'),
    );
    expect(strlen($analysis->profile_fingerprint))->toBe(64);
    expect(strlen($analysis->opportunity_fingerprint))->toBe(64);
    expect($analysis->queued_at)->not->toBeNull();

    Queue::assertPushedOn('matching', ProcessMatchAnalysisJob::class);
    Queue::assertPushed(ProcessMatchAnalysisJob::class, fn ($job): bool => $job->analysisId === $analysis->id);
});

it('is idempotent for the same profile, opportunity, and client key', function () {
    $opportunity = matchConfirmedOpportunity($this->profile, $this->user, $this->phpSkill);

    $first = $this->action->execute($this->profile, $opportunity, 'client-key-1');
    $second = $this->action->execute($this->profile, $opportunity, 'client-key-1');

    expect($second->id)->toBe($first->id);
    expect(MatchAnalysis::count())->toBe(1);
    Queue::assertPushed(ProcessMatchAnalysisJob::class, 1);
});

it('creates a separate analysis for a different client key', function () {
    $opportunity = matchConfirmedOpportunity($this->profile, $this->user, $this->phpSkill);

    $this->action->execute($this->profile, $opportunity, 'client-key-1');
    $this->action->execute($this->profile, $opportunity, 'client-key-2');

    expect(MatchAnalysis::count())->toBe(2);
    Queue::assertPushed(ProcessMatchAnalysisJob::class, 2);
});

it('rejects an unconfirmed opportunity', function () {
    $ingestion = JobOpportunityIngestion::factory()->reviewReady()->create(['user_id' => $this->user->id]);

    $opportunity = JobOpportunity::factory()->create([
        'candidate_profile_id' => $this->profile->id,
        'ingestion_id' => $ingestion->id,
    ]);

    $this->action->execute($this->profile, $opportunity, 'client-key-1');
})->throws(ConflictException::class);

it('rejects a profile below the minimum completion', function () {
    $user = User::factory()->create();
    $profile = CandidateProfile::factory()->minimal()->create(['user_id' => $user->id]);
    $opportunity = matchConfirmedOpportunity($profile, $user, $this->phpSkill);

    $this->action->execute($profile, $opportunity, 'client-key-1');
})->throws(UnprocessableEntityException::class);

it('rejects a profile without trusted skills', function () {
    $user = User::factory()->create();
    $profile = CandidateProfile::factory()->complete()->create(['user_id' => $user->id]);
    $opportunity = matchConfirmedOpportunity($profile, $user, $this->phpSkill);

    $this->action->execute($profile, $opportunity, 'client-key-1');
})->throws(UnprocessableEntityException::class);

it('stores the request id from the request context', function () {
    RequestIdContext::set('req_test_123');
    $opportunity = matchConfirmedOpportunity($this->profile, $this->user, $this->phpSkill);

    $analysis = $this->action->execute($this->profile, $opportunity, 'client-key-1');

    expect($analysis->request_id)->toBe('req_test_123');
});
