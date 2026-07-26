<?php

use App\Domain\CvIngestion\Enums\CvDocumentStatus;

it('starts as pending', function () {
    $status = CvDocumentStatus::Pending;

    expect($status->value)->toBe('pending');
    expect($status->isProcessing())->toBeFalse();
    expect($status->isTerminal())->toBeFalse();
    expect($status->isRetryable())->toBeFalse();
});

it('allows transition from pending to queued', function () {
    expect(CvDocumentStatus::Pending->canTransitionTo(CvDocumentStatus::Queued))->toBeTrue();
});

it('rejects transition from pending to ready_for_review', function () {
    expect(CvDocumentStatus::Pending->canTransitionTo(CvDocumentStatus::ReadyForReview))->toBeFalse();
});

it('allows all valid transitions through the pipeline', function () {
    expect(CvDocumentStatus::Pending->canTransitionTo(CvDocumentStatus::Queued))->toBeTrue();
    expect(CvDocumentStatus::Queued->canTransitionTo(CvDocumentStatus::Validating))->toBeTrue();
    expect(CvDocumentStatus::Validating->canTransitionTo(CvDocumentStatus::Extracting))->toBeTrue();
    expect(CvDocumentStatus::Validating->canTransitionTo(CvDocumentStatus::Failed))->toBeTrue();
    expect(CvDocumentStatus::Extracting->canTransitionTo(CvDocumentStatus::Analyzing))->toBeTrue();
    expect(CvDocumentStatus::Extracting->canTransitionTo(CvDocumentStatus::Failed))->toBeTrue();
    expect(CvDocumentStatus::Analyzing->canTransitionTo(CvDocumentStatus::ReadyForReview))->toBeTrue();
    expect(CvDocumentStatus::Analyzing->canTransitionTo(CvDocumentStatus::Failed))->toBeTrue();
    expect(CvDocumentStatus::ReadyForReview->canTransitionTo(CvDocumentStatus::Importing))->toBeTrue();
    expect(CvDocumentStatus::ReadyForReview->canTransitionTo(CvDocumentStatus::Queued))->toBeTrue();
    expect(CvDocumentStatus::ReadyForReview->canTransitionTo(CvDocumentStatus::Deleted))->toBeTrue();
    expect(CvDocumentStatus::Importing->canTransitionTo(CvDocumentStatus::Imported))->toBeTrue();
    expect(CvDocumentStatus::Importing->canTransitionTo(CvDocumentStatus::ReadyForReview))->toBeTrue();
    expect(CvDocumentStatus::Imported->canTransitionTo(CvDocumentStatus::Deleted))->toBeTrue();
    expect(CvDocumentStatus::Failed->canTransitionTo(CvDocumentStatus::Queued))->toBeTrue();
    expect(CvDocumentStatus::Failed->canTransitionTo(CvDocumentStatus::Deleted))->toBeTrue();
});

it('rejects invalid transitions', function () {
    expect(CvDocumentStatus::Pending->canTransitionTo(CvDocumentStatus::Imported))->toBeFalse();
    expect(CvDocumentStatus::Deleted->canTransitionTo(CvDocumentStatus::Queued))->toBeFalse();
    expect(CvDocumentStatus::Imported->canTransitionTo(CvDocumentStatus::Importing))->toBeFalse();
    expect(CvDocumentStatus::ReadyForReview->canTransitionTo(CvDocumentStatus::Extracting))->toBeFalse();
    expect(CvDocumentStatus::Failed->canTransitionTo(CvDocumentStatus::Extracting))->toBeFalse();
});

it('correctly identifies processing states', function () {
    expect(CvDocumentStatus::Queued->isProcessing())->toBeTrue();
    expect(CvDocumentStatus::Validating->isProcessing())->toBeTrue();
    expect(CvDocumentStatus::Extracting->isProcessing())->toBeTrue();
    expect(CvDocumentStatus::Analyzing->isProcessing())->toBeTrue();
    expect(CvDocumentStatus::Importing->isProcessing())->toBeTrue();

    expect(CvDocumentStatus::Pending->isProcessing())->toBeFalse();
    expect(CvDocumentStatus::ReadyForReview->isProcessing())->toBeFalse();
    expect(CvDocumentStatus::Imported->isProcessing())->toBeFalse();
    expect(CvDocumentStatus::Failed->isRetryable())->toBeTrue();
});

it('identifies terminal states', function () {
    expect(CvDocumentStatus::Imported->isTerminal())->toBeTrue();
    expect(CvDocumentStatus::Deleted->isTerminal())->toBeTrue();

    expect(CvDocumentStatus::Pending->isTerminal())->toBeFalse();
    expect(CvDocumentStatus::Failed->isTerminal())->toBeFalse();
});

it('only failed state is retryable', function () {
    expect(CvDocumentStatus::Failed->isRetryable())->toBeTrue();
    expect(CvDocumentStatus::Pending->isRetryable())->toBeFalse();
    expect(CvDocumentStatus::ReadyForReview->isRetryable())->toBeFalse();
    expect(CvDocumentStatus::Deleted->isRetryable())->toBeFalse();
});
