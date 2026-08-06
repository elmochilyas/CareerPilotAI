<?php

use App\Domain\Matching\Actions\MarkMatchAnalysisFailedAction;
use App\Domain\Matching\Data\ClassifierRequest;
use App\Domain\Matching\Data\ClassifierResult;
use App\Domain\Matching\Enums\MatchAnalysisStatus;
use App\Domain\Matching\Enums\MatchCategory;
use App\Domain\Matching\Enums\MatchState;
use App\Domain\Matching\Enums\RequirementSourceType;
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
use Illuminate\Support\Facades\DB;
use Tests\Support\FakeRequirementClassifier;

uses(RefreshDatabase::class)->group('matching', 'jobs');

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

function matchRunJob(int $analysisId): void
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

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->profile = CandidateProfile::factory()->complete()->create(['user_id' => $this->user->id]);
    $this->phpSkill = matchVerifiedSkill($this->profile, 'PHP');
    $this->opportunity = matchConfirmedOpportunity($this->profile, $this->user, $this->phpSkill);
    $this->app->instance(RequirementClassifier::class, new FakeRequirementClassifier);
});

it('completes an analysis end to end and persists scores and findings', function () {
    ProfileItem::factory()->experience()->create(['candidate_profile_id' => $this->profile->id]);

    $analysis = MatchAnalysis::factory()->queued()->create([
        'candidate_profile_id' => $this->profile->id,
        'job_opportunity_id' => $this->opportunity->id,
    ]);

    matchRunJob($analysis->id);

    $analysis->refresh();
    expect($analysis->status)->toBe(MatchAnalysisStatus::Completed);
    expect($analysis->overall_score)->toBeInt();
    expect($analysis->evidence_coverage_score)->toBeInt();
    expect($analysis->completed_at)->not->toBeNull();
    expect($analysis->classifier_provider)->toBe('fake');
    expect($analysis->classifier_model)->toBe('fake-classifier-v1');
    expect($analysis->classifier_status)->toBe('success');

    expect(MatchScore::where('match_analysis_id', $analysis->id)->count())->toBeGreaterThan(0);
    expect(MatchFinding::where('match_analysis_id', $analysis->id)->count())->toBe(2);

    $responsibility = MatchFinding::where('match_analysis_id', $analysis->id)
        ->where('category', MatchCategory::Evidence->value)
        ->first();

    expect($responsibility)->not->toBeNull();
    expect($responsibility->match_state)->toBe(MatchState::Matched);
    expect($responsibility->classifier_source)->toBe('fake:fake-classifier-v1');
    expect($responsibility->evidence_refs)->not->toBeEmpty();

    $skill = MatchFinding::where('match_analysis_id', $analysis->id)
        ->where('source_type', RequirementSourceType::JobOpportunitySkill->value)
        ->first();

    expect($skill)->not->toBeNull();
    expect($skill->match_state)->toBe(MatchState::Matched);
    expect($skill->factor)->toBe('1.00');
    expect($skill->matched_candidate_skill_id)->toBe($this->phpSkill->id);
});

it('propagates the stored request id into the classifier request', function () {
    ProfileItem::factory()->experience()->create(['candidate_profile_id' => $this->profile->id]);

    $spy = new class extends FakeRequirementClassifier
    {
        public ?ClassifierRequest $lastRequest = null;

        public function classify(ClassifierRequest $request): ClassifierResult
        {
            $this->lastRequest = $request;

            return parent::classify($request);
        }
    };

    $this->app->instance(RequirementClassifier::class, $spy);

    $analysis = MatchAnalysis::factory()->queued()->create([
        'candidate_profile_id' => $this->profile->id,
        'job_opportunity_id' => $this->opportunity->id,
        'request_id' => 'req_test_123',
    ]);

    matchRunJob($analysis->id);

    expect($spy->lastRequest)->not->toBeNull();
    expect($spy->lastRequest->requestId)->toBe('req_test_123');
});

it('completes deterministically when the classifier provider fails', function () {
    $this->app->instance(
        RequirementClassifier::class,
        new FakeRequirementClassifier(FakeRequirementClassifier::MODE_PROVIDER_FAILURE),
    );

    ProfileItem::factory()->experience()->create(['candidate_profile_id' => $this->profile->id]);

    $analysis = MatchAnalysis::factory()->queued()->create([
        'candidate_profile_id' => $this->profile->id,
        'job_opportunity_id' => $this->opportunity->id,
    ]);

    matchRunJob($analysis->id);

    $analysis->refresh();
    expect($analysis->status)->toBe(MatchAnalysisStatus::Completed);
    expect($analysis->failure_code)->toBeNull();
    expect($analysis->classifier_status)->toBe('unavailable');
    expect($analysis->overall_score)->toBeInt();

    expect(MatchScore::where('match_analysis_id', $analysis->id)->count())->toBeGreaterThan(0);

    $responsibility = MatchFinding::where('match_analysis_id', $analysis->id)
        ->where('category', MatchCategory::Evidence->value)
        ->first();

    expect($responsibility)->not->toBeNull();
    expect($responsibility->match_state)->toBe(MatchState::Unknown);
    expect($responsibility->classifier_source)->toBeNull();

    $skill = MatchFinding::where('match_analysis_id', $analysis->id)
        ->where('source_type', RequirementSourceType::JobOpportunitySkill->value)
        ->first();

    expect($skill)->not->toBeNull();
    expect($skill->match_state)->toBe(MatchState::Matched);
    expect($skill->matched_candidate_skill_id)->toBe($this->phpSkill->id);
});

it('marks the analysis failed when the framework invokes failed() with only the exception', function () {
    $analysis = MatchAnalysis::factory()->queued()->create([
        'candidate_profile_id' => $this->profile->id,
        'job_opportunity_id' => $this->opportunity->id,
    ]);

    $job = new ProcessMatchAnalysisJob($analysis->id);

    $job->failed(new RuntimeException('Unexpected infrastructure failure.'));

    $analysis->refresh();
    expect($analysis->status)->toBe(MatchAnalysisStatus::Failed);
    expect($analysis->failure_code)->toBe('analysis_failed');
    expect($analysis->failure_reason)->toContain('Unexpected infrastructure failure');
    expect($analysis->failed_at)->not->toBeNull();
});

it('marks the analysis failed safely when an unexpected exception occurs', function () {
    $this->app->instance(
        RequirementClassifier::class,
        new class implements RequirementClassifier
        {
            public function classify(ClassifierRequest $request): ClassifierResult
            {
                throw new RuntimeException('Unexpected infrastructure failure.');
            }
        },
    );

    ProfileItem::factory()->experience()->create(['candidate_profile_id' => $this->profile->id]);

    $analysis = MatchAnalysis::factory()->queued()->create([
        'candidate_profile_id' => $this->profile->id,
        'job_opportunity_id' => $this->opportunity->id,
    ]);

    try {
        matchRunJob($analysis->id);
    } catch (Throwable $exception) {
        app(MarkMatchAnalysisFailedAction::class)->execute(
            $analysis->id,
            'analysis_failed',
            'The analysis could not be completed.',
            $exception,
        );
    }

    $analysis->refresh();
    expect($analysis->status)->toBe(MatchAnalysisStatus::Failed);
    expect($analysis->failure_code)->toBe('analysis_failed');
    expect($analysis->failed_at)->not->toBeNull();

    expect(MatchScore::where('match_analysis_id', $analysis->id)->count())->toBe(0);
    expect(MatchFinding::where('match_analysis_id', $analysis->id)->count())->toBe(0);
});

it('skips an analysis that is already completed', function () {
    $analysis = MatchAnalysis::factory()->completed()->create([
        'candidate_profile_id' => $this->profile->id,
        'job_opportunity_id' => $this->opportunity->id,
    ]);

    matchRunJob($analysis->id);

    $analysis->refresh();
    expect($analysis->status)->toBe(MatchAnalysisStatus::Completed);
    expect(MatchScore::where('match_analysis_id', $analysis->id)->count())->toBe(0);
});

it('returns safely when the analysis does not exist', function () {
    expect(fn () => matchRunJob(999_999))->not->toThrow(Throwable::class);
});

it('completes end to end on the real database queue', function () {
    config()->set('queue.default', 'database');
    ProfileItem::factory()->experience()->create(['candidate_profile_id' => $this->profile->id]);

    $analysis = MatchAnalysis::factory()->queued()->create([
        'candidate_profile_id' => $this->profile->id,
        'job_opportunity_id' => $this->opportunity->id,
    ]);

    ProcessMatchAnalysisJob::dispatch($analysis->id);

    $this->assertDatabaseHas('jobs', ['queue' => 'matching']);

    $this->artisan('queue:work', ['--once' => true, '--queue' => 'matching']);

    $analysis->refresh();
    expect($analysis->status)->toBe(MatchAnalysisStatus::Completed);
    expect($analysis->overall_score)->toBeInt();
    expect(MatchScore::where('match_analysis_id', $analysis->id)->count())->toBeGreaterThan(0);
    expect(MatchFinding::where('match_analysis_id', $analysis->id)->count())->toBeGreaterThan(0);
});

it('completes deterministically when the classifier fails on the database queue', function () {
    config()->set('queue.default', 'database');
    $this->app->instance(
        RequirementClassifier::class,
        new FakeRequirementClassifier(FakeRequirementClassifier::MODE_PROVIDER_FAILURE),
    );

    ProfileItem::factory()->experience()->create(['candidate_profile_id' => $this->profile->id]);

    $analysis = MatchAnalysis::factory()->queued()->create([
        'candidate_profile_id' => $this->profile->id,
        'job_opportunity_id' => $this->opportunity->id,
    ]);

    ProcessMatchAnalysisJob::dispatch($analysis->id);

    $this->assertDatabaseHas('jobs', ['queue' => 'matching']);

    $this->artisan('queue:work', ['--once' => true, '--queue' => 'matching']);

    expect(DB::table('jobs')->where('queue', 'matching')->count())->toBe(0);

    $analysis->refresh();
    expect($analysis->status)->toBe(MatchAnalysisStatus::Completed);
    expect($analysis->classifier_status)->toBe('unavailable');
    expect($analysis->overall_score)->toBeInt();
    expect(MatchScore::where('match_analysis_id', $analysis->id)->count())->toBeGreaterThan(0);
    expect(MatchFinding::where('match_analysis_id', $analysis->id)->count())->toBeGreaterThan(0);
});
