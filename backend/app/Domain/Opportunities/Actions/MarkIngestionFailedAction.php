<?php

namespace App\Domain\Opportunities\Actions;

use App\Domain\Opportunities\Enums\JobIngestionStatus;
use App\Exceptions\Api\ConflictException;
use App\Models\JobOpportunityIngestion;

class MarkIngestionFailedAction
{
    public function execute(
        int $ingestionId,
        string $failureCode = 'pipeline_error',
        ?string $failureReason = null,
        ?\Throwable $previous = null,
    ): void {
        $ingestion = JobOpportunityIngestion::find($ingestionId);

        if ($ingestion === null) {
            return;
        }

        if ($ingestion->status->isTerminal()) {
            return;
        }

        if ($ingestion->status === JobIngestionStatus::Failed) {
            return;
        }

        if ($failureReason === null && $previous !== null) {
            $failureReason = $this->sanitizeReason($previous);
        }

        if ($failureReason === null) {
            $failureReason = 'An unexpected error occurred during processing.';
        }

        $ingestion->update([
            'status' => JobIngestionStatus::Failed,
            'failure_reason' => $failureReason,
            'failure_code' => $failureCode,
            'retry_count' => $ingestion->retry_count + 1,
            'last_retry_at' => now(),
        ]);
    }

    private function sanitizeReason(\Throwable $e): string
    {
        if ($e instanceof ConflictException) {
            return match ($e->getErrorCode()) {
                'provider_timeout' => 'AI provider did not respond within the configured timeout.',
                'provider_rate_limited' => 'AI provider rate limit exceeded. Try again later.',
                'ai_provider_not_configured' => 'AI analysis is currently unavailable. Please try again later.',
                'ai_provider_authentication_failed' => 'AI analysis is currently unavailable. Please try again later.',
                'ai_provider_unavailable' => 'AI provider is temporarily unavailable.',
                'invalid_ai_output' => 'Invalid AI output received.',
                default => 'An error occurred during job analysis.',
            };
        }

        $message = $e->getMessage();

        $blocked = [
            'API key', 'api_key', 'secret', 'password', 'token',
            'stack trace', 'Stack trace', 'in /app/', 'in /vendor/',
        ];

        foreach ($blocked as $pattern) {
            if (str_contains($message, $pattern)) {
                return 'An internal processing error occurred.';
            }
        }

        return str($message)->limit(500)->toString();
    }
}
