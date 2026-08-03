<?php

use App\Domain\Opportunities\Enums\JobIngestionStatus;
use App\Domain\Opportunities\Services\JobIngestionStateService;
use App\Jobs\ExtractJobInformationJob;
use App\Jobs\ProcessJobIngestionJob;
use App\Models\JobOpportunityIngestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class)->group('api', 'opportunities', 'ingestion', 'jobs');

beforeEach(function () {
    Queue::fake();
    $this->user = User::factory()->create();
    $this->stateService = app(JobIngestionStateService::class);
});

it('transitions draft ingestion to queued', function () {
    $ingestion = JobOpportunityIngestion::factory()->draft()->create([
        'user_id' => $this->user->id,
    ]);

    $job = new ProcessJobIngestionJob($ingestion->id);
    $job->handle($this->stateService);

    $ingestion->refresh();
    expect($ingestion->status)->toBe(JobIngestionStatus::Queued);
});

it('dispatches extract job to correct queue', function () {
    $ingestion = JobOpportunityIngestion::factory()->draft()->create([
        'user_id' => $this->user->id,
    ]);

    $job = new ProcessJobIngestionJob($ingestion->id);
    $job->handle($this->stateService);

    Queue::assertPushedOn('job-ingestion', ExtractJobInformationJob::class);
    Queue::assertPushed(ExtractJobInformationJob::class, function ($job) use ($ingestion) {
        return $job->ingestionId === $ingestion->id;
    });
});

it('does not dispatch duplicate extract jobs on repeated execution', function () {
    $ingestion = JobOpportunityIngestion::factory()->draft()->create([
        'user_id' => $this->user->id,
    ]);

    $job = new ProcessJobIngestionJob($ingestion->id);
    $job->handle($this->stateService);
    $job->handle($this->stateService);

    Queue::assertPushed(ExtractJobInformationJob::class, 1);
});

it('skips cancelled ingestion', function () {
    $ingestion = JobOpportunityIngestion::factory()->cancelled()->create([
        'user_id' => $this->user->id,
    ]);

    $job = new ProcessJobIngestionJob($ingestion->id);
    $job->handle($this->stateService);

    $ingestion->refresh();
    expect($ingestion->status)->toBe(JobIngestionStatus::Cancelled);
    Queue::assertNotPushed(ExtractJobInformationJob::class);
});

it('skips confirmed ingestion', function () {
    $ingestion = JobOpportunityIngestion::factory()->confirmed()->create([
        'user_id' => $this->user->id,
    ]);

    $job = new ProcessJobIngestionJob($ingestion->id);
    $job->handle($this->stateService);

    $ingestion->refresh();
    expect($ingestion->status)->toBe(JobIngestionStatus::Confirmed);
    Queue::assertNotPushed(ExtractJobInformationJob::class);
});

it('skips already queued ingestion', function () {
    $ingestion = JobOpportunityIngestion::factory()->queued()->create([
        'user_id' => $this->user->id,
    ]);

    $job = new ProcessJobIngestionJob($ingestion->id);
    $job->handle($this->stateService);

    $ingestion->refresh();
    expect($ingestion->status)->toBe(JobIngestionStatus::Queued);
    Queue::assertNotPushed(ExtractJobInformationJob::class);
});

it('returns safely when ingestion does not exist', function () {
    $job = new ProcessJobIngestionJob(99999);
    expect(fn () => $job->handle($this->stateService))
        ->not->toThrow(Throwable::class);
});
