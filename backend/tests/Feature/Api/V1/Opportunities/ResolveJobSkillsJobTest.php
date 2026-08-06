<?php

use App\Domain\Opportunities\Enums\JobIngestionStatus;
use App\Jobs\ResolveJobSkillsJob;
use App\Models\JobOpportunityIngestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class)->group('api', 'opportunities', 'ingestion', 'jobs');

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('marks the ingestion failed when failed() is invoked with only the exception', function () {
    $ingestion = JobOpportunityIngestion::factory()->processing()->create([
        'user_id' => $this->user->id,
    ]);

    $job = new ResolveJobSkillsJob($ingestion->id);

    $job->failed(new RuntimeException('Unexpected pipeline failure.'));

    $ingestion->refresh();
    expect($ingestion->status)->toBe(JobIngestionStatus::Failed);
    expect($ingestion->failure_code)->toBe('pipeline_error');
    expect($ingestion->failure_reason)->toBe('Failed to resolve job skills.');
    expect($ingestion->retry_count)->toBe(1);
    expect($ingestion->last_retry_at)->not->toBeNull();
});

it('does not leave a processing ingestion stuck after failed()', function () {
    $ingestion = JobOpportunityIngestion::factory()->processing()->create([
        'user_id' => $this->user->id,
    ]);

    $job = new ResolveJobSkillsJob($ingestion->id);
    $job->failed(new RuntimeException('Boom'));

    expect($ingestion->fresh()->status)->toBe(JobIngestionStatus::Failed);
});

it('is idempotent when failed() is invoked again', function () {
    $ingestion = JobOpportunityIngestion::factory()->processing()->create([
        'user_id' => $this->user->id,
    ]);

    $job = new ResolveJobSkillsJob($ingestion->id);

    $job->failed(new RuntimeException('Boom'));
    $job->failed(new RuntimeException('Boom again'));

    $ingestion->refresh();
    expect($ingestion->status)->toBe(JobIngestionStatus::Failed);
    expect($ingestion->failure_code)->toBe('pipeline_error');
    expect($ingestion->failure_reason)->toBe('Failed to resolve job skills.');
    expect($ingestion->retry_count)->toBe(1);
});
