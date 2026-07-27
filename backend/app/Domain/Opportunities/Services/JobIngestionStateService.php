<?php

namespace App\Domain\Opportunities\Services;

use App\Domain\Opportunities\Enums\JobIngestionStatus;
use App\Exceptions\Api\ConflictException;

class JobIngestionStateService
{
    public function transition(JobIngestionStatus $from, JobIngestionStatus $to): void
    {
        if (! $from->canTransitionTo($to)) {
            throw new ConflictException(
                "Cannot transition from {$from->value} to {$to->value}.",
                'invalid_state_transition',
            );
        }
    }

    public function assertNotTerminal(JobIngestionStatus $status): void
    {
        if ($status->isTerminal()) {
            throw new ConflictException(
                "Ingestion is already {$status->value} and cannot be modified.",
                'ingestion_not_modifiable',
            );
        }
    }

    public function assertRetryable(JobIngestionStatus $status, ?string $failureCode = null): void
    {
        if (! $status->isRetryable()) {
            throw new ConflictException(
                'Ingestion is not in a retryable state.',
                'ingestion_not_retryable',
            );
        }

        if ($failureCode !== null && in_array($failureCode, [
            'invalid_ai_output',
            'schema_validation_failed',
            'description_invalid',
        ], true)) {
            throw new ConflictException(
                'This failure is permanent and cannot be retried.',
                'permanent_failure',
            );
        }
    }

    public function assertReviewable(JobIngestionStatus $status): void
    {
        if ($status !== JobIngestionStatus::ReviewReady) {
            throw new ConflictException(
                'Ingestion is not ready for review.',
                'ingestion_not_reviewable',
            );
        }
    }

    public function getRetryableFailureCodes(): array
    {
        return [
            'provider_unavailable',
            'provider_timeout',
            'provider_rate_limited',
            'ai_provider_timeout',
            'ai_provider_rate_limited',
            'ai_provider_unavailable',
            'suggestion_persistence_failed',
            'transient_queue_error',
            'network_error',
        ];
    }
}
