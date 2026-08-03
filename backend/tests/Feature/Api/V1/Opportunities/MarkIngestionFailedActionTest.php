<?php

use App\Domain\Opportunities\Actions\MarkIngestionFailedAction;
use App\Domain\Opportunities\Enums\JobIngestionStatus;
use App\Models\JobOpportunityIngestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class)->group('api', 'opportunities', 'ingestion', 'unit');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->action = app(MarkIngestionFailedAction::class);
});

it('marks queued ingestion as failed', function () {
    $ingestion = JobOpportunityIngestion::factory()->queued()->create([
        'user_id' => $this->user->id,
    ]);

    $this->action->execute(
        ingestionId: $ingestion->id,
        failureCode: 'provider_timeout',
        failureReason: 'AI provider timed out.',
    );

    $ingestion->refresh();
    expect($ingestion->status)->toBe(JobIngestionStatus::Failed);
    expect($ingestion->failure_code)->toBe('provider_timeout');
    expect($ingestion->failure_reason)->toBe('AI provider timed out.');
    expect($ingestion->retry_count)->toBe(1);
});

it('marks processing ingestion as failed', function () {
    $ingestion = JobOpportunityIngestion::factory()->processing()->create([
        'user_id' => $this->user->id,
    ]);

    $this->action->execute(
        ingestionId: $ingestion->id,
        failureCode: 'invalid_ai_output',
    );

    $ingestion->refresh();
    expect($ingestion->status)->toBe(JobIngestionStatus::Failed);
    expect($ingestion->failure_code)->toBe('invalid_ai_output');
});

it('does not modify already failed ingestion', function () {
    $ingestion = JobOpportunityIngestion::factory()->failed()->create([
        'user_id' => $this->user->id,
        'failure_code' => 'original_code',
        'failure_reason' => 'Original failure.',
        'retry_count' => 3,
    ]);

    $this->action->execute(
        ingestionId: $ingestion->id,
        failureCode: 'new_code',
        failureReason: 'New failure.',
    );

    $ingestion->refresh();
    expect($ingestion->status)->toBe(JobIngestionStatus::Failed);
    expect($ingestion->failure_code)->toBe('original_code');
    expect($ingestion->failure_reason)->toBe('Original failure.');
    expect($ingestion->retry_count)->toBe(3);
});

it('does not modify confirmed ingestion', function () {
    $ingestion = JobOpportunityIngestion::factory()->confirmed()->create([
        'user_id' => $this->user->id,
    ]);

    $this->action->execute(
        ingestionId: $ingestion->id,
        failureCode: 'pipeline_error',
    );

    $ingestion->refresh();
    expect($ingestion->status)->toBe(JobIngestionStatus::Confirmed);
    expect($ingestion->failure_code)->toBeNull();
});

it('does not modify cancelled ingestion', function () {
    $ingestion = JobOpportunityIngestion::factory()->cancelled()->create([
        'user_id' => $this->user->id,
    ]);

    $this->action->execute(
        ingestionId: $ingestion->id,
        failureCode: 'pipeline_error',
    );

    $ingestion->refresh();
    expect($ingestion->status)->toBe(JobIngestionStatus::Cancelled);
    expect($ingestion->failure_code)->toBeNull();
});

it('returns safely when ingestion does not exist', function () {
    expect(fn () => $this->action->execute(
        ingestionId: 99999,
        failureCode: 'pipeline_error',
    ))->not->toThrow(Throwable::class);
});

it('sets retry_count to 1 on first failure', function () {
    $ingestion = JobOpportunityIngestion::factory()->queued()->create([
        'user_id' => $this->user->id,
    ]);

    $this->action->execute(ingestionId: $ingestion->id, failureCode: 'error_1');
    $ingestion->refresh();
    expect($ingestion->retry_count)->toBe(1);
});

it('does not increment retry_count when called on already failed ingestion', function () {
    $ingestion = JobOpportunityIngestion::factory()->failed()->create([
        'user_id' => $this->user->id,
        'failure_code' => 'original',
        'retry_count' => 2,
    ]);

    $this->action->execute(ingestionId: $ingestion->id, failureCode: 'new_error');

    $ingestion->refresh();
    expect($ingestion->retry_count)->toBe(2);
});

it('repeated calls do not change version of failed ingestion', function () {
    $ingestion = JobOpportunityIngestion::factory()->failed()->create([
        'user_id' => $this->user->id,
        'failure_code' => 'original',
        'retry_count' => 1,
    ]);

    $this->action->execute(ingestionId: $ingestion->id, failureCode: 'new');
    $this->action->execute(ingestionId: $ingestion->id, failureCode: 'new2');

    $ingestion->refresh();
    expect($ingestion->failure_code)->toBe('original');
    expect($ingestion->retry_count)->toBe(1);
});

it('does not throw when called on failed ingestion', function () {
    $ingestion = JobOpportunityIngestion::factory()->failed()->create([
        'user_id' => $this->user->id,
    ]);

    expect(fn () => $this->action->execute(
        ingestionId: $ingestion->id,
        failureCode: 'error',
    ))->not->toThrow(Throwable::class);
});

it('does not throw when called on confirmed ingestion', function () {
    $ingestion = JobOpportunityIngestion::factory()->confirmed()->create([
        'user_id' => $this->user->id,
    ]);

    expect(fn () => $this->action->execute(
        ingestionId: $ingestion->id,
        failureCode: 'error',
    ))->not->toThrow(Throwable::class);
});
