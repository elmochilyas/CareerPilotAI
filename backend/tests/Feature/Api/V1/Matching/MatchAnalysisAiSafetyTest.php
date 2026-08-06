<?php

use App\Domain\Matching\Enums\MatchAnalysisStatus;
use App\Domain\Matching\Enums\MatchState;
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
use App\Models\MatchFinding;
use App\Models\MatchScore;
use App\Models\ProfileItem;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeRequirementClassifier;

uses(RefreshDatabase::class)->group('api', 'matching', 'ai-safety');

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
    $this->user = User::factory()->create();
    $this->profile = CandidateProfile::factory()->complete()->create(['user_id' => $this->user->id]);
    $this->phpSkill = matchVerifiedSkill($this->profile, 'PHP');
    $this->opportunity = matchConfirmedOpportunity($this->profile, $this->user, $this->phpSkill);
});

it('completes with unknown semantic requirements when the classifier returns malformed output', function () {
    $this->app->instance(
        RequirementClassifier::class,
        new FakeRequirementClassifier(FakeRequirementClassifier::MODE_MALFORMED),
    );
    ProfileItem::factory()->experience()->create(['candidate_profile_id' => $this->profile->id]);

    $analysis = MatchAnalysis::factory()->queued()->create([
        'candidate_profile_id' => $this->profile->id,
        'job_opportunity_id' => $this->opportunity->id,
    ]);

    matchRunAnalysisJob($analysis->id);

    $analysis->refresh();
    expect($analysis->status)->toBe(MatchAnalysisStatus::Completed);
    expect($analysis->failure_code)->toBeNull();
    expect($analysis->classifier_status)->toBe('unavailable');
    expect($analysis->classifier_provider)->toBeNull();
    expect($analysis->overall_score)->toBeInt();
    expect(MatchScore::where('match_analysis_id', $analysis->id)->count())->toBeGreaterThan(0);

    $responsibility = MatchFinding::where('match_analysis_id', $analysis->id)
        ->where('category', 'evidence')
        ->firstOrFail();

    expect($responsibility->match_state)->toBe(MatchState::Unknown);
    expect($responsibility->factor)->toBe('0.00');
    expect($responsibility->classifier_source)->toBeNull();
    expect($responsibility->justification)->toBe(
        'Semantic comparison was unavailable, so this requirement was left unevaluated.',
    );

    $skill = MatchFinding::where('match_analysis_id', $analysis->id)
        ->where('category', 'required_skills')
        ->firstOrFail();

    expect($skill->match_state)->toBe(MatchState::Matched);
    expect($skill->matched_candidate_skill_id)->toBe($this->phpSkill->id);

    $response = $this->actingAs($this->user)->getJson("/api/v1/matches/{$analysis->id}");

    $response->assertOk();
    $response->assertJsonPath('data.status', 'completed');
    $response->assertJsonPath('data.failure.code', null);
    $response->assertJsonPath('data.classifier.status', 'unavailable');
    expect($response->json('data.overall_score'))->not->toBeNull();
    expect($response->json('data.findings'))->not->toBeEmpty();
    expect($response->getContent())->not->toContain('invalid response');
});

it('completes with unknown semantic requirements when the classifier output fails schema validation', function () {
    $this->app->instance(
        RequirementClassifier::class,
        new FakeRequirementClassifier(FakeRequirementClassifier::MODE_INVALID_SCHEMA),
    );
    ProfileItem::factory()->experience()->create(['candidate_profile_id' => $this->profile->id]);

    $analysis = MatchAnalysis::factory()->queued()->create([
        'candidate_profile_id' => $this->profile->id,
        'job_opportunity_id' => $this->opportunity->id,
    ]);

    matchRunAnalysisJob($analysis->id);

    $analysis->refresh();
    expect($analysis->status)->toBe(MatchAnalysisStatus::Completed);
    expect($analysis->failure_code)->toBeNull();
    expect($analysis->classifier_status)->toBe('unavailable');
    expect($analysis->unknown_count)->toBe(1);

    $response = $this->actingAs($this->user)->getJson("/api/v1/matches/{$analysis->id}");

    $response->assertOk();
    $response->assertJsonPath('data.status', 'completed');
    $response->assertJsonPath('data.failure.code', null);
    $response->assertJsonPath('data.classifier.status', 'unavailable');
    expect($response->json('data.counts.unknown'))->toBe(1);
    expect($response->getContent())->not->toContain('schema and business validation');
});

it('completes with deterministic results after a classifier provider failure without exposing internal details', function () {
    $this->app->instance(
        RequirementClassifier::class,
        new FakeRequirementClassifier(FakeRequirementClassifier::MODE_PROVIDER_FAILURE),
    );
    ProfileItem::factory()->experience()->create(['candidate_profile_id' => $this->profile->id]);

    $analysis = MatchAnalysis::factory()->queued()->create([
        'candidate_profile_id' => $this->profile->id,
        'job_opportunity_id' => $this->opportunity->id,
    ]);

    matchRunAnalysisJob($analysis->id);

    $analysis->refresh();
    expect($analysis->status)->toBe(MatchAnalysisStatus::Completed);
    expect($analysis->failure_code)->toBeNull();
    expect($analysis->classifier_status)->toBe('unavailable');
    expect($analysis->overall_score)->toBeInt();

    $response = $this->actingAs($this->user)->getJson("/api/v1/matches/{$analysis->id}");

    $response->assertOk();
    $response->assertJsonPath('data.status', 'completed');
    $response->assertJsonPath('data.failure.code', null);
    $response->assertJsonPath('data.failure.reason', null);
    $response->assertJsonPath('data.classifier.status', 'unavailable');
    expect($response->json('data.overall_score'))->not->toBeNull();
    expect($response->getContent())->not->toContain('Temporarily unavailable (provider)');
    expect($response->getContent())->not->toContain('provider is temporarily unavailable');
});
