<?php

use App\Domain\Opportunities\Actions\AnalyzeJobAction;
use App\Domain\Opportunities\Data\JobAnalysisResult;
use App\Domain\Opportunities\Enums\JobIngestionStatus;
use App\Domain\Opportunities\Services\Contracts\JobAnalyzer;
use App\Exceptions\Api\ConflictException;
use App\Jobs\ExtractJobInformationJob;
use App\Models\JobOpportunityIngestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class)->group('api', 'opportunities', 'ingestion', 'jobs');

beforeEach(function () {
    $this->user = User::factory()->create();

    $this->app->bind(JobAnalyzer::class, function () {
        return new class implements JobAnalyzer
        {
            public function analyze(string $description): JobAnalysisResult
            {
                return new JobAnalysisResult(
                    schemaVersion: '1.0.0',
                    job: ['title' => 'Developer'],
                    warnings: [],
                    provider: 'test',
                    model: 'test',
                    promptVersion: '1.0.0',
                    latencyMs: 0,
                    tokensPrompt: 0,
                    tokensCompletion: 0,
                    responseId: 'test',
                );
            }
        };
    });
});

it('calls analyze action for queued ingestion', function () {
    $ingestion = JobOpportunityIngestion::factory()->queued()->create([
        'user_id' => $this->user->id,
    ]);

    $job = new ExtractJobInformationJob($ingestion->id);
    $job->handle(app(AnalyzeJobAction::class));

    $ingestion->refresh();
    expect($ingestion->status)->toBe(JobIngestionStatus::ReviewReady);
});

it('skips cancelled ingestion', function () {
    $ingestion = JobOpportunityIngestion::factory()->cancelled()->create([
        'user_id' => $this->user->id,
    ]);

    $job = new ExtractJobInformationJob($ingestion->id);
    $job->handle(app(AnalyzeJobAction::class));

    $ingestion->refresh();
    expect($ingestion->status)->toBe(JobIngestionStatus::Cancelled);
});

it('skips confirmed ingestion', function () {
    $ingestion = JobOpportunityIngestion::factory()->confirmed()->create([
        'user_id' => $this->user->id,
    ]);

    $job = new ExtractJobInformationJob($ingestion->id);
    $job->handle(app(AnalyzeJobAction::class));

    $ingestion->refresh();
    expect($ingestion->status)->toBe(JobIngestionStatus::Confirmed);
});

it('skips processing ingestion', function () {
    $ingestion = JobOpportunityIngestion::factory()->processing()->create([
        'user_id' => $this->user->id,
    ]);

    $job = new ExtractJobInformationJob($ingestion->id);
    $job->handle(app(AnalyzeJobAction::class));

    $ingestion->refresh();
    expect($ingestion->status)->toBe(JobIngestionStatus::Processing);
});

it('skips review_ready ingestion', function () {
    $ingestion = JobOpportunityIngestion::factory()->reviewReady()->create([
        'user_id' => $this->user->id,
    ]);

    $job = new ExtractJobInformationJob($ingestion->id);
    $job->handle(app(AnalyzeJobAction::class));

    $ingestion->refresh();
    expect($ingestion->status)->toBe(JobIngestionStatus::ReviewReady);
});

it('skips failed ingestion', function () {
    $ingestion = JobOpportunityIngestion::factory()->failed()->create([
        'user_id' => $this->user->id,
    ]);

    $job = new ExtractJobInformationJob($ingestion->id);
    $job->handle(app(AnalyzeJobAction::class));

    $ingestion->refresh();
    expect($ingestion->status)->toBe(JobIngestionStatus::Failed);
});

it('returns safely when ingestion does not exist', function () {
    $job = new ExtractJobInformationJob(99999);

    expect(fn () => $job->handle(app(AnalyzeJobAction::class)))->not->toThrow(Throwable::class);
});

it('skips a stale extraction job from an earlier ingestion version', function () {
    $ingestion = JobOpportunityIngestion::factory()->queued()->create([
        'user_id' => $this->user->id,
        'version' => 3,
    ]);

    $job = new ExtractJobInformationJob($ingestion->id, expectedVersion: 2);
    $job->handle(app(AnalyzeJobAction::class));

    expect($ingestion->fresh()->status)->toBe(JobIngestionStatus::Queued);
    expect($ingestion->fresh()->suggestions()->count())->toBe(0);
});

it('does not persist stale results after a newer analysis attempt starts', function () {
    $ingestion = JobOpportunityIngestion::factory()->queued()->create([
        'user_id' => $this->user->id,
        'version' => 3,
    ]);

    $this->app->bind(JobAnalyzer::class, function () use ($ingestion) {
        return new class($ingestion) implements JobAnalyzer
        {
            public function __construct(private JobOpportunityIngestion $ingestion) {}

            public function analyze(string $description): JobAnalysisResult
            {
                JobOpportunityIngestion::query()
                    ->whereKey($this->ingestion->id)
                    ->update([
                        'status' => JobIngestionStatus::Queued,
                        'version' => 5,
                    ]);

                return new JobAnalysisResult(
                    schemaVersion: '1.0.0',
                    job: ['title' => 'Stale result'],
                    warnings: [],
                    provider: 'test',
                    model: 'test',
                    promptVersion: '1.0.0',
                    latencyMs: 0,
                    tokensPrompt: 0,
                    tokensCompletion: 0,
                    responseId: 'stale',
                );
            }
        };
    });

    $job = new ExtractJobInformationJob($ingestion->id, expectedVersion: 3);
    $job->handle(app(AnalyzeJobAction::class));

    expect($ingestion->fresh()->status)->toBe(JobIngestionStatus::Queued);
    expect($ingestion->fresh()->version)->toBe(5);
    expect($ingestion->fresh()->suggestions()->count())->toBe(0);
});

it('marks permanent failure when analyze action detects permanent error', function () {
    $this->app->bind(JobAnalyzer::class, function () {
        return new class implements JobAnalyzer
        {
            public function analyze(string $description): JobAnalysisResult
            {
                throw new ConflictException('Invalid output.', 'invalid_ai_output');
            }
        };
    });

    $ingestion = JobOpportunityIngestion::factory()->queued()->create([
        'user_id' => $this->user->id,
    ]);

    $job = new ExtractJobInformationJob($ingestion->id);
    $job->handle(app(AnalyzeJobAction::class));

    $ingestion->refresh();
    expect($ingestion->status)->toBe(JobIngestionStatus::Failed);
    expect($ingestion->failure_code)->toBe('invalid_ai_output');
});

it('marks failed ingestion when state service throws conflict on re-run', function () {
    $ingestion = JobOpportunityIngestion::factory()->queued()->create([
        'user_id' => $this->user->id,
    ]);

    $analyzeAction = app(AnalyzeJobAction::class);

    $job = new ExtractJobInformationJob($ingestion->id);
    $job->handle($analyzeAction);

    $ingestion->refresh();
    expect($ingestion->status)->toBe(JobIngestionStatus::ReviewReady);

    $rerunJob = new ExtractJobInformationJob($ingestion->id);
    $rerunJob->handle($analyzeAction);

    $ingestion->refresh();
    expect($ingestion->status)->toBe(JobIngestionStatus::ReviewReady);
});

it('marks the ingestion failed when failed() is invoked with only the exception', function () {
    $ingestion = JobOpportunityIngestion::factory()->processing()->create([
        'user_id' => $this->user->id,
    ]);

    $job = new ExtractJobInformationJob($ingestion->id);

    $job->failed(new RuntimeException('Unexpected failure.'));

    $ingestion->refresh();
    expect($ingestion->status)->toBe(JobIngestionStatus::Failed);
    expect($ingestion->failure_code)->toBe('unexpected_processing_failure');
    expect($ingestion->failure_reason)->toBe('We could not complete the analysis safely.');
    expect($ingestion->retry_count)->toBe(1);
    expect($ingestion->last_retry_at)->not->toBeNull();
});

it('maps a conflict exception error code when failed() is invoked', function () {
    $ingestion = JobOpportunityIngestion::factory()->processing()->create([
        'user_id' => $this->user->id,
    ]);

    $job = new ExtractJobInformationJob($ingestion->id);

    $job->failed(new ConflictException('Invalid output.', 'invalid_ai_output'));

    $ingestion->refresh();
    expect($ingestion->status)->toBe(JobIngestionStatus::Failed);
    expect($ingestion->failure_code)->toBe('invalid_ai_output');
});

it('does not leave a processing ingestion stuck after failed()', function () {
    $ingestion = JobOpportunityIngestion::factory()->processing()->create([
        'user_id' => $this->user->id,
    ]);

    $job = new ExtractJobInformationJob($ingestion->id);
    $job->failed(new RuntimeException('Boom'));

    expect($ingestion->fresh()->status)->toBe(JobIngestionStatus::Failed);
});

it('is idempotent when failed() is invoked again', function () {
    $ingestion = JobOpportunityIngestion::factory()->processing()->create([
        'user_id' => $this->user->id,
    ]);

    $job = new ExtractJobInformationJob($ingestion->id);

    $job->failed(new RuntimeException('Boom'));
    $job->failed(new RuntimeException('Boom again'));

    $ingestion->refresh();
    expect($ingestion->status)->toBe(JobIngestionStatus::Failed);
    expect($ingestion->failure_code)->toBe('unexpected_processing_failure');
    expect($ingestion->retry_count)->toBe(1);
});
