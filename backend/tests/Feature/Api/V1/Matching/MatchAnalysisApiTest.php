<?php

use App\Domain\Matching\Enums\MatchAnalysisStatus;
use App\Domain\Matching\Services\Contracts\RequirementClassifier;
use App\Domain\Matching\Services\DeterministicRequirementEvaluator;
use App\Domain\Matching\Services\FingerprintService;
use App\Domain\Matching\Services\MatchScoreCalculator;
use App\Domain\Matching\Services\RequirementCollector;
use App\Domain\Matching\Services\RequirementSemanticClassifier;
use App\Domain\Skills\Enums\ProficiencyLevel;
use App\Domain\Skills\Enums\SkillState;
use App\Jobs\ProcessMatchAnalysisJob;
use App\Models\CandidateProfile;
use App\Models\CandidateSkill;
use App\Models\JobOpportunity;
use App\Models\JobOpportunityIngestion;
use App\Models\JobOpportunitySkill;
use App\Models\JobRequirement;
use App\Models\MatchAnalysis;
use App\Models\MatchScore;
use App\Models\ProfileItem;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Support\FakeRequirementClassifier;

uses(RefreshDatabase::class)->group('api', 'matching', 'matches');

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

if (! function_exists('matchRunAnalysisJob')) {
    function matchRunAnalysisJob(int $analysisId): void
    {
        $job = new ProcessMatchAnalysisJob($analysisId);

        $job->handle(
            app(RequirementCollector::class),
            app(DeterministicRequirementEvaluator::class),
            app(RequirementSemanticClassifier::class),
            app(MatchScoreCalculator::class),
            app(FingerprintService::class),
        );
    }
}

beforeEach(function () {
    Queue::fake();
    $this->app->instance(RequirementClassifier::class, new FakeRequirementClassifier);
    $this->user = User::factory()->create();
    $this->profile = CandidateProfile::factory()->complete()->create(['user_id' => $this->user->id]);
    $this->phpSkill = matchVerifiedSkill($this->profile, 'PHP');
    $this->opportunity = matchConfirmedOpportunity($this->profile, $this->user, $this->phpSkill);
});

it('creates a match analysis asynchronously with 202 and a polling location', function () {
    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/opportunities/{$this->opportunity->id}/matches");

    $response->assertStatus(202);
    $response->assertHeader('Location');
    $response->assertJsonPath('data.status', 'queued');
    $response->assertJsonPath('data.job_opportunity_id', $this->opportunity->id);

    $analysisId = $response->json('data.id');
    expect(MatchAnalysis::find($analysisId))->not->toBeNull();
    Queue::assertPushedOn('matching', ProcessMatchAnalysisJob::class);
});

it('returns the existing operation for a repeated idempotency key', function () {
    $this->actingAs($this->user)
        ->postJson("/api/v1/opportunities/{$this->opportunity->id}/matches", [], ['Idempotency-Key' => 'client-key-123'])
        ->assertStatus(202);

    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/opportunities/{$this->opportunity->id}/matches", [], ['Idempotency-Key' => 'client-key-123']);

    $response->assertStatus(202);
    expect(MatchAnalysis::count())->toBe(1);
    Queue::assertPushed(ProcessMatchAnalysisJob::class, 1);
});

it('returns a validation problem detail for an invalid idempotency key', function () {
    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/opportunities/{$this->opportunity->id}/matches", [], ['Idempotency-Key' => 'x']);

    $response->assertStatus(422);
    $response->assertJsonPath('code', 'validation_error');
    $response->assertJsonStructure([
        'type', 'title', 'status', 'detail', 'instance', 'code', 'errors', 'request_id',
    ]);
    expect($response->json('errors.idempotency_key'))->not->toBeNull();
    expect($response->getContent())->not->toContain('SQLSTATE');
});

it('rejects an unconfirmed opportunity with 409', function () {
    $ingestion = JobOpportunityIngestion::factory()->reviewReady()->create(['user_id' => $this->user->id]);
    $opportunity = JobOpportunity::factory()->create([
        'candidate_profile_id' => $this->profile->id,
        'ingestion_id' => $ingestion->id,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/opportunities/{$opportunity->id}/matches");

    $response->assertStatus(409);
    $response->assertJsonPath('code', 'opportunity_not_confirmed');
    expect(MatchAnalysis::count())->toBe(0);
});

it('returns 422 when the profile is below the minimum completion', function () {
    $user = User::factory()->create();
    $profile = CandidateProfile::factory()->minimal()->create(['user_id' => $user->id]);
    $opportunity = matchConfirmedOpportunity($profile, $user);

    $response = $this->actingAs($user)
        ->postJson("/api/v1/opportunities/{$opportunity->id}/matches");

    $response->assertStatus(422);
    $response->assertJsonPath('code', 'insufficient_profile');
    expect(MatchAnalysis::count())->toBe(0);
});

it('returns 422 when the profile has no trusted skills', function () {
    $user = User::factory()->create();
    $profile = CandidateProfile::factory()->complete()->create(['user_id' => $user->id]);
    $opportunity = matchConfirmedOpportunity($profile, $user);

    $response = $this->actingAs($user)
        ->postJson("/api/v1/opportunities/{$opportunity->id}/matches");

    $response->assertStatus(422);
    $response->assertJsonPath('code', 'insufficient_profile');
    expect(MatchAnalysis::count())->toBe(0);
});

it('returns 401 for unauthenticated requests', function () {
    $this->postJson("/api/v1/opportunities/{$this->opportunity->id}/matches")
        ->assertStatus(401)
        ->assertJsonPath('code', 'unauthenticated');

    $this->getJson("/api/v1/opportunities/{$this->opportunity->id}/matches")->assertStatus(401);
    $this->getJson('/api/v1/matches/1')->assertStatus(401);
    $this->postJson('/api/v1/matches/1/recalculate')->assertStatus(401);
});

it('returns 404 for another candidate without leaking existence', function () {
    $otherUser = User::factory()->create();
    $otherProfile = CandidateProfile::factory()->complete()->create(['user_id' => $otherUser->id]);
    $otherSkill = matchVerifiedSkill($otherProfile, 'Vue.js');
    $otherOpportunity = matchConfirmedOpportunity($otherProfile, $otherUser, $otherSkill);
    $otherAnalysis = MatchAnalysis::factory()->completed()->create([
        'candidate_profile_id' => $otherProfile->id,
        'job_opportunity_id' => $otherOpportunity->id,
    ]);

    $this->actingAs($this->user)
        ->postJson("/api/v1/opportunities/{$otherOpportunity->id}/matches")
        ->assertStatus(404)
        ->assertJsonPath('code', 'not_found');

    $this->actingAs($this->user)
        ->getJson("/api/v1/opportunities/{$otherOpportunity->id}/matches")
        ->assertStatus(404);

    $this->actingAs($this->user)
        ->getJson("/api/v1/matches/{$otherAnalysis->id}")
        ->assertStatus(404);

    $this->actingAs($this->user)
        ->postJson("/api/v1/matches/{$otherAnalysis->id}/recalculate")
        ->assertStatus(404);
});

it('returns 404 for a non-existent opportunity', function () {
    $this->actingAs($this->user)
        ->postJson('/api/v1/opportunities/999999/matches')
        ->assertStatus(404);
});

it('polls an operation from queued to completed with a full analysis', function () {
    ProfileItem::factory()->experience()->create(['candidate_profile_id' => $this->profile->id]);

    $created = $this->actingAs($this->user)
        ->postJson("/api/v1/opportunities/{$this->opportunity->id}/matches");

    $created->assertStatus(202);
    $analysisId = $created->json('data.id');

    $this->actingAs($this->user)->getJson("/api/v1/matches/{$analysisId}")
        ->assertOk()
        ->assertJsonPath('data.status', 'queued');

    matchRunAnalysisJob($analysisId);

    $response = $this->actingAs($this->user)->getJson("/api/v1/matches/{$analysisId}");

    $response->assertOk();
    $response->assertJsonPath('data.status', 'completed');
    $response->assertJsonPath('data.latest', true);
    $response->assertJsonStructure([
        'data' => [
            'id', 'candidate_profile_id', 'job_opportunity_id', 'status',
            'overall_score', 'evidence_coverage_score',
            'counts' => ['required', 'preferred', 'matched', 'partial', 'gap', 'unknown'],
            'versions' => ['algorithm', 'scoring', 'classifier_schema'],
            'fingerprints' => ['profile', 'opportunity'],
            'stale', 'latest', 'warnings',
            'failure' => ['code', 'reason'],
            'classifier' => ['provider', 'model', 'prompt_version', 'latency_ms', 'tokens_prompt', 'tokens_completion', 'response_id', 'status'],
            'request_id',
            'score_components' => ['*' => ['category', 'weight', 'score', 'achieved_points', 'total_points', 'has_candidate_data']],
            'findings' => ['*' => ['source_type', 'requirement_text', 'importance', 'category', 'match_state', 'factor', 'evidence_refs']],
            'timestamps',
        ],
    ]);
    expect($response->json('data.score_components'))->not->toBeEmpty();
    expect($response->json('data.findings'))->not->toBeEmpty();
});

it('lists analyses newest first with the latest analysis flagged', function () {
    $older = MatchAnalysis::factory()->completed()->create([
        'candidate_profile_id' => $this->profile->id,
        'job_opportunity_id' => $this->opportunity->id,
    ]);
    $newer = MatchAnalysis::factory()->completed()->create([
        'candidate_profile_id' => $this->profile->id,
        'job_opportunity_id' => $this->opportunity->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/v1/opportunities/{$this->opportunity->id}/matches");

    $response->assertOk();
    expect($response->json('data.0.id'))->toBe($newer->id);
    expect($response->json('data.1.id'))->toBe($older->id);
    expect($response->json('data.0.latest'))->toBeTrue();
    expect($response->json('data.1.latest'))->toBeFalse();
    $response->assertJsonStructure(['meta' => ['path', 'per_page', 'next_cursor', 'prev_cursor']]);
});

it('returns an empty paginated list when no analyses exist', function () {
    $response = $this->actingAs($this->user)
        ->getJson("/api/v1/opportunities/{$this->opportunity->id}/matches");

    $response->assertOk();
    expect($response->json('data'))->toBe([]);
    expect($response->json('meta.next_cursor'))->toBeNull();
});

it('rejects an unknown query parameter on the list endpoint', function () {
    $response = $this->actingAs($this->user)
        ->getJson("/api/v1/opportunities/{$this->opportunity->id}/matches?status=completed");

    $response->assertStatus(422);
    $response->assertJsonPath('code', 'validation_error');
    $response->assertJsonPath('errors.status', ['Unknown filter or sort parameter.']);
});

it('recalculates into a new snapshot and keeps the previous analysis immutable', function () {
    ProfileItem::factory()->experience()->create(['candidate_profile_id' => $this->profile->id]);

    $created = $this->actingAs($this->user)
        ->postJson("/api/v1/opportunities/{$this->opportunity->id}/matches");

    $analysisId = $created->json('data.id');
    matchRunAnalysisJob($analysisId);

    $old = MatchAnalysis::findOrFail($analysisId);
    $oldScore = $old->overall_score;
    $oldCompletedAt = $old->completed_at;
    $oldFingerprint = $old->profile_fingerprint;
    $scoreCount = MatchScore::where('match_analysis_id', $old->id)->count();

    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/matches/{$analysisId}/recalculate");

    $response->assertStatus(202);
    $newId = $response->json('data.id');
    expect($newId)->not->toBe($analysisId);
    expect(MatchAnalysis::count())->toBe(2);

    $old->refresh();
    expect($old->status)->toBe(MatchAnalysisStatus::Completed);
    expect($old->overall_score)->toBe($oldScore);
    expect($old->completed_at->equalTo($oldCompletedAt))->toBeTrue();
    expect($old->profile_fingerprint)->toBe($oldFingerprint);
    expect(MatchScore::where('match_analysis_id', $old->id)->count())->toBe($scoreCount);

    $this->actingAs($this->user)->getJson("/api/v1/matches/{$analysisId}")->assertOk();
});

it('rejects recalculation while another analysis is active', function () {
    $completed = MatchAnalysis::factory()->completed()->create([
        'candidate_profile_id' => $this->profile->id,
        'job_opportunity_id' => $this->opportunity->id,
    ]);
    $active = MatchAnalysis::factory()->queued()->create([
        'candidate_profile_id' => $this->profile->id,
        'job_opportunity_id' => $this->opportunity->id,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/matches/{$completed->id}/recalculate");

    $response->assertStatus(409);
    $response->assertJsonPath('code', 'active_match_analysis');
    $response->assertJsonPath('errors.active_match_analysis_id', $active->id);
    expect(MatchAnalysis::count())->toBe(2);
});

it('rate limits match creation', function () {
    config()->set('matching.create_rate_limit', '2,1');

    $this->actingAs($this->user)->postJson("/api/v1/opportunities/{$this->opportunity->id}/matches")->assertStatus(202);
    $this->actingAs($this->user)->postJson("/api/v1/opportunities/{$this->opportunity->id}/matches")->assertStatus(202);

    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/opportunities/{$this->opportunity->id}/matches");

    $response->assertStatus(429);
    $response->assertJsonPath('code', 'too_many_requests');
});

it('echoes the client request id and stores it on the analysis', function () {
    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/opportunities/{$this->opportunity->id}/matches", [], ['X-Request-ID' => 'req_api_test_1']);

    $response->assertStatus(202);
    $response->assertHeader('X-Request-ID', 'req_api_test_1');
    expect($response->json('request_id'))->toBe('req_api_test_1');
    expect($response->json('data.request_id'))->toBe('req_api_test_1');
});

it('marks the analysis stale when the profile changes', function () {
    ProfileItem::factory()->experience()->create(['candidate_profile_id' => $this->profile->id]);

    $created = $this->actingAs($this->user)
        ->postJson("/api/v1/opportunities/{$this->opportunity->id}/matches");

    $analysisId = $created->json('data.id');
    matchRunAnalysisJob($analysisId);

    $this->actingAs($this->user)->getJson("/api/v1/matches/{$analysisId}")
        ->assertOk()
        ->assertJsonPath('data.stale', false);

    $this->profile->forceFill([
        'headline' => 'Senior Laravel Backend Engineer',
        'updated_at' => now()->addSeconds(2),
    ])->save();

    $this->actingAs($this->user)->getJson("/api/v1/matches/{$analysisId}")
        ->assertOk()
        ->assertJsonPath('data.stale', true);
});

it('marks the analysis stale when the opportunity changes', function () {
    ProfileItem::factory()->experience()->create(['candidate_profile_id' => $this->profile->id]);

    $created = $this->actingAs($this->user)
        ->postJson("/api/v1/opportunities/{$this->opportunity->id}/matches");

    $analysisId = $created->json('data.id');
    matchRunAnalysisJob($analysisId);

    $this->actingAs($this->user)->getJson("/api/v1/matches/{$analysisId}")
        ->assertOk()
        ->assertJsonPath('data.stale', false);

    $this->opportunity->forceFill([
        'title' => 'Senior Laravel Engineer',
        'updated_at' => now()->addSeconds(2),
    ])->save();

    $this->actingAs($this->user)->getJson("/api/v1/matches/{$analysisId}")
        ->assertOk()
        ->assertJsonPath('data.stale', true);
});

it('produces identical scores for identical inputs', function () {
    ProfileItem::factory()->experience()->create(['candidate_profile_id' => $this->profile->id]);

    $first = MatchAnalysis::factory()->queued()->create([
        'candidate_profile_id' => $this->profile->id,
        'job_opportunity_id' => $this->opportunity->id,
    ]);
    $second = MatchAnalysis::factory()->queued()->create([
        'candidate_profile_id' => $this->profile->id,
        'job_opportunity_id' => $this->opportunity->id,
    ]);

    matchRunAnalysisJob($first->id);
    matchRunAnalysisJob($second->id);

    $first->refresh();
    $second->refresh();

    expect($first->status)->toBe(MatchAnalysisStatus::Completed);
    expect($second->status)->toBe(MatchAnalysisStatus::Completed);
    expect($first->overall_score)->toBe($second->overall_score);
    expect($first->evidence_coverage_score)->toBe($second->evidence_coverage_score);
});

it('weights required skills higher than preferred skills', function () {
    ProfileItem::factory()->experience()->create(['candidate_profile_id' => $this->profile->id]);

    JobOpportunitySkill::create([
        'job_opportunity_id' => $this->opportunity->id,
        'skill_id' => $this->phpSkill->skill->id,
        'original_label' => 'PHP',
        'classification' => 'preferred',
        'display_order' => 2,
    ]);

    $analysis = MatchAnalysis::factory()->queued()->create([
        'candidate_profile_id' => $this->profile->id,
        'job_opportunity_id' => $this->opportunity->id,
    ]);

    matchRunAnalysisJob($analysis->id);

    $required = MatchScore::where('match_analysis_id', $analysis->id)
        ->where('category', 'required_skills')
        ->firstOrFail();
    $preferred = MatchScore::where('match_analysis_id', $analysis->id)
        ->where('category', 'preferred_skills')
        ->firstOrFail();

    expect((float) $required->weight)->toBeGreaterThan((float) $preferred->weight);
    expect((float) $required->weight)->toBe((float) config('matching.weights.required_skills'));
    expect((float) $preferred->weight)->toBe((float) config('matching.weights.preferred_skills'));
});
