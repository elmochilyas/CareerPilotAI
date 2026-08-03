<?php

use App\Domain\Opportunities\Actions\AnalyzeJobAction;
use App\Domain\Opportunities\Data\JobAnalysisResult;
use App\Domain\Opportunities\Enums\JobIngestionStatus;
use App\Domain\Opportunities\Services\Contracts\JobAnalyzer;
use App\Domain\Opportunities\Services\JobIngestionStateService;
use App\Exceptions\Api\ConflictException;
use App\Jobs\ExtractJobInformationJob;
use App\Jobs\ProcessJobIngestionJob;
use App\Models\JobOpportunityIngestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class)->group('api', 'opportunities', 'ingestion', 'pipeline');

function buildFakeJobAnalysisResult(): JobAnalysisResult
{
    return new JobAnalysisResult(
        schemaVersion: '1.0.0',
        job: [
            'title' => 'Senior Laravel Developer',
            'company' => 'TechCorp',
            'department' => 'Engineering',
            'external_reference' => '',
            'summary' => 'We are looking for a senior developer.',
            'application_url' => 'https://techcorp.example.com/apply',
            'location' => [
                'city' => 'Casablanca',
                'region' => 'Casablanca-Settat',
                'country' => 'Morocco',
            ],
            'work_mode' => 'hybrid',
            'contract_type' => 'full-time',
            'seniority_level' => 'senior',
            'working_hours' => '40h/week',
            'travel_required' => false,
            'relocation_required' => false,
            'responsibilities' => [
                ['text' => 'Develop and maintain Laravel applications', 'source' => 'job description'],
                ['text' => 'Lead code reviews', 'source' => 'job description'],
            ],
            'required_experience' => [
                ['summary' => 'Experience with Laravel', 'years' => 5, 'source' => 'job description'],
            ],
            'preferred_experience' => [
                ['summary' => 'Experience with Vue.js', 'years' => 2, 'source' => 'job description'],
            ],
            'education_requirements' => [
                ['degree' => 'Bachelor', 'field' => 'Computer Science', 'required' => true],
            ],
            'required_skills' => [
                ['label' => 'Laravel', 'proficiency' => 'advanced', 'years_experience' => 5, 'source' => 'job description'],
                ['label' => 'PHP', 'proficiency' => 'advanced', 'years_experience' => 5, 'source' => 'job description'],
            ],
            'preferred_skills' => [
                ['label' => 'Vue.js', 'proficiency' => 'intermediate', 'years_experience' => 2, 'source' => 'job description'],
            ],
            'languages' => [
                ['language' => 'English', 'required' => true, 'proficiency' => 'advanced', 'source' => 'job description'],
            ],
            'certifications' => [
                ['name' => 'AWS Certified Developer', 'required' => false, 'source' => 'job description'],
            ],
            'compensation' => [
                'salary_min' => 60000,
                'salary_max' => 90000,
                'currency' => 'USD',
                'period' => 'yearly',
                'text' => 'Competitive salary',
            ],
            'benefits' => ['Health insurance', 'Remote allowance'],
            'publication_date' => '2026-07-01',
            'application_deadline' => '2026-08-01',
            'expected_start_date' => '2026-09-01',
            'employment_duration' => 'permanent',
            'additional_requirements' => ['Must be based in Morocco'],
        ],
        warnings: [],
        provider: 'test',
        model: 'test-analyzer-v1',
        promptVersion: '1.0.0',
        latencyMs: 100,
        tokensPrompt: 500,
        tokensCompletion: 300,
        responseId: 'test_'.uniqid(),
    );
}

beforeEach(function () {
    Queue::fake();
    $this->user = User::factory()->create();

    $this->app->bind(JobAnalyzer::class, function () {
        $result = buildFakeJobAnalysisResult();

        return new class($result) implements JobAnalyzer
        {
            public function __construct(private JobAnalysisResult $result) {}

            public function analyze(string $description): JobAnalysisResult
            {
                return $this->result;
            }
        };
    });
});

it('processes ingestion from draft to review_ready through the full pipeline', function () {
    $ingestion = JobOpportunityIngestion::factory()->draft()->create([
        'user_id' => $this->user->id,
    ]);

    $stateService = app(JobIngestionStateService::class);

    $processJob = new ProcessJobIngestionJob($ingestion->id);
    $processJob->handle($stateService);

    $ingestion->refresh();
    expect($ingestion->status)->toBe(JobIngestionStatus::Queued);

    Queue::assertPushed(ExtractJobInformationJob::class, 1);

    $extractJob = new ExtractJobInformationJob($ingestion->id);
    $extractJob->handle(app(AnalyzeJobAction::class));

    $ingestion->refresh();
    expect($ingestion->status)->toBe(JobIngestionStatus::ReviewReady);
});

it('creates suggestions after pipeline processing', function () {
    $ingestion = JobOpportunityIngestion::factory()->draft()->create([
        'user_id' => $this->user->id,
    ]);

    $stateService = app(JobIngestionStateService::class);

    $processJob = new ProcessJobIngestionJob($ingestion->id);
    $processJob->handle($stateService);

    $extractJob = new ExtractJobInformationJob($ingestion->id);
    $extractJob->handle(app(AnalyzeJobAction::class));

    $ingestion->refresh();
    expect($ingestion->suggestions()->count())->toBeGreaterThan(0);
});

it('does not create duplicate suggestions on repeated pipeline execution', function () {
    $ingestion = JobOpportunityIngestion::factory()->draft()->create([
        'user_id' => $this->user->id,
    ]);

    $stateService = app(JobIngestionStateService::class);
    $analyzeAction = app(AnalyzeJobAction::class);

    $processJob = new ProcessJobIngestionJob($ingestion->id);
    $processJob->handle($stateService);

    $extractJob = new ExtractJobInformationJob($ingestion->id);
    $extractJob->handle($analyzeAction);

    $firstCount = $ingestion->fresh()->suggestions()->count();

    $processJob->handle($stateService);
    $extractJob->handle($analyzeAction);

    expect($ingestion->fresh()->suggestions()->count())->toBe($firstCount);
});

it('marks permanent failure when analyzer throws invalid ai output', function () {
    $this->app->bind(JobAnalyzer::class, function () {
        return new class implements JobAnalyzer
        {
            public function analyze(string $description): JobAnalysisResult
            {
                throw new ConflictException(
                    'AI provider returned an invalid response.',
                    'invalid_ai_output',
                );
            }
        };
    });

    $ingestion = JobOpportunityIngestion::factory()->draft()->create([
        'user_id' => $this->user->id,
    ]);

    $stateService = app(JobIngestionStateService::class);

    $processJob = new ProcessJobIngestionJob($ingestion->id);
    $processJob->handle($stateService);

    $extractJob = new ExtractJobInformationJob($ingestion->id);
    $extractJob->handle(app(AnalyzeJobAction::class));

    $ingestion->refresh();
    expect($ingestion->status)->toBe(JobIngestionStatus::Failed);
    expect($ingestion->failure_code)->toBe('invalid_ai_output');
});

it('marks permanent failure for missing provider config without retry', function () {
    $this->app->bind(JobAnalyzer::class, function () {
        return new class implements JobAnalyzer
        {
            public function analyze(string $description): JobAnalysisResult
            {
                throw new ConflictException(
                    'AI provider is not configured.',
                    'ai_provider_not_configured',
                );
            }
        };
    });

    $ingestion = JobOpportunityIngestion::factory()->draft()->create([
        'user_id' => $this->user->id,
    ]);

    $stateService = app(JobIngestionStateService::class);

    $processJob = new ProcessJobIngestionJob($ingestion->id);
    $processJob->handle($stateService);

    $extractJob = new ExtractJobInformationJob($ingestion->id);
    $extractJob->handle(app(AnalyzeJobAction::class));

    $ingestion->refresh();
    expect($ingestion->status)->toBe(JobIngestionStatus::Failed);
    expect($ingestion->failure_code)->toBe('ai_provider_not_configured');
    expect($ingestion->failure_reason)->toBe('AI analysis is currently unavailable. Please try again later.');
});

it('does not revive cancelled ingestion through stale job', function () {
    $ingestion = JobOpportunityIngestion::factory()->cancelled()->create([
        'user_id' => $this->user->id,
    ]);

    $stateService = app(JobIngestionStateService::class);

    $processJob = new ProcessJobIngestionJob($ingestion->id);
    $processJob->handle($stateService);

    $ingestion->refresh();
    expect($ingestion->status)->toBe(JobIngestionStatus::Cancelled);

    Queue::assertNotPushed(ExtractJobInformationJob::class);
});

it('pipeline remains idempotent when process job runs twice', function () {
    $ingestion = JobOpportunityIngestion::factory()->draft()->create([
        'user_id' => $this->user->id,
    ]);

    $stateService = app(JobIngestionStateService::class);

    $processJob = new ProcessJobIngestionJob($ingestion->id);
    $processJob->handle($stateService);
    $processJob->handle($stateService);

    $ingestion->refresh();
    expect($ingestion->status)->toBe(JobIngestionStatus::Queued);

    Queue::assertPushed(ExtractJobInformationJob::class, 1);
});
