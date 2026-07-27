<?php

use App\Domain\Opportunities\Enums\JobIngestionStatus;

it('starts as draft', function () {
    $status = JobIngestionStatus::Draft;

    expect($status->value)->toBe('draft');
    expect($status->isProcessing())->toBeFalse();
    expect($status->isTerminal())->toBeFalse();
    expect($status->isRetryable())->toBeFalse();
});

it('allows all valid transitions through the pipeline', function () {
    expect(JobIngestionStatus::Draft->canTransitionTo(JobIngestionStatus::Queued))->toBeTrue();
    expect(JobIngestionStatus::Draft->canTransitionTo(JobIngestionStatus::Cancelled))->toBeTrue();
    expect(JobIngestionStatus::Queued->canTransitionTo(JobIngestionStatus::Processing))->toBeTrue();
    expect(JobIngestionStatus::Queued->canTransitionTo(JobIngestionStatus::Failed))->toBeTrue();
    expect(JobIngestionStatus::Queued->canTransitionTo(JobIngestionStatus::Cancelled))->toBeTrue();
    expect(JobIngestionStatus::Processing->canTransitionTo(JobIngestionStatus::ReviewReady))->toBeTrue();
    expect(JobIngestionStatus::Processing->canTransitionTo(JobIngestionStatus::Failed))->toBeTrue();
    expect(JobIngestionStatus::Processing->canTransitionTo(JobIngestionStatus::Cancelled))->toBeTrue();
    expect(JobIngestionStatus::ReviewReady->canTransitionTo(JobIngestionStatus::Confirmed))->toBeTrue();
    expect(JobIngestionStatus::ReviewReady->canTransitionTo(JobIngestionStatus::Queued))->toBeTrue();
    expect(JobIngestionStatus::ReviewReady->canTransitionTo(JobIngestionStatus::Cancelled))->toBeTrue();
    expect(JobIngestionStatus::Failed->canTransitionTo(JobIngestionStatus::Queued))->toBeTrue();
    expect(JobIngestionStatus::Failed->canTransitionTo(JobIngestionStatus::Cancelled))->toBeTrue();
});

it('rejects invalid transitions', function () {
    expect(JobIngestionStatus::Draft->canTransitionTo(JobIngestionStatus::Confirmed))->toBeFalse();
    expect(JobIngestionStatus::Draft->canTransitionTo(JobIngestionStatus::ReviewReady))->toBeFalse();
    expect(JobIngestionStatus::Confirmed->canTransitionTo(JobIngestionStatus::Queued))->toBeFalse();
    expect(JobIngestionStatus::Confirmed->canTransitionTo(JobIngestionStatus::Cancelled))->toBeFalse();
    expect(JobIngestionStatus::Cancelled->canTransitionTo(JobIngestionStatus::Queued))->toBeFalse();
    expect(JobIngestionStatus::Cancelled->canTransitionTo(JobIngestionStatus::Draft))->toBeFalse();
    expect(JobIngestionStatus::ReviewReady->canTransitionTo(JobIngestionStatus::Draft))->toBeFalse();
    expect(JobIngestionStatus::Processing->canTransitionTo(JobIngestionStatus::Queued))->toBeFalse();
});

it('correctly identifies processing states', function () {
    expect(JobIngestionStatus::Queued->isProcessing())->toBeTrue();
    expect(JobIngestionStatus::Processing->isProcessing())->toBeTrue();

    expect(JobIngestionStatus::Draft->isProcessing())->toBeFalse();
    expect(JobIngestionStatus::ReviewReady->isProcessing())->toBeFalse();
    expect(JobIngestionStatus::Confirmed->isProcessing())->toBeFalse();
    expect(JobIngestionStatus::Failed->isProcessing())->toBeFalse();
    expect(JobIngestionStatus::Cancelled->isProcessing())->toBeFalse();
});

it('identifies terminal states', function () {
    expect(JobIngestionStatus::Confirmed->isTerminal())->toBeTrue();
    expect(JobIngestionStatus::Cancelled->isTerminal())->toBeTrue();

    expect(JobIngestionStatus::Draft->isTerminal())->toBeFalse();
    expect(JobIngestionStatus::ReviewReady->isTerminal())->toBeFalse();
    expect(JobIngestionStatus::Failed->isTerminal())->toBeFalse();
});

it('only failed state is retryable', function () {
    expect(JobIngestionStatus::Failed->isRetryable())->toBeTrue();

    expect(JobIngestionStatus::Draft->isRetryable())->toBeFalse();
    expect(JobIngestionStatus::ReviewReady->isRetryable())->toBeFalse();
    expect(JobIngestionStatus::Cancelled->isRetryable())->toBeFalse();
    expect(JobIngestionStatus::Confirmed->isRetryable())->toBeFalse();
});
