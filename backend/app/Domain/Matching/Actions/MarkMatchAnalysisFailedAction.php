<?php

namespace App\Domain\Matching\Actions;

use App\Domain\Matching\Enums\MatchAnalysisStatus;
use App\Domain\Matching\Exceptions\RequirementClassifierException;
use App\Models\MatchAnalysis;

class MarkMatchAnalysisFailedAction
{
    public function execute(
        int $analysisId,
        ?string $failureCode = null,
        ?string $failureReason = null,
        ?\Throwable $previous = null,
    ): void {
        $analysis = MatchAnalysis::find($analysisId);

        if ($analysis === null) {
            return;
        }

        if ($analysis->status->isTerminal()) {
            return;
        }

        if ($failureCode === null) {
            $failureCode = $this->sanitizeCode($previous);
        }

        if ($failureReason === null) {
            $failureReason = $this->sanitizeReason($previous)
                ?? 'An unexpected error occurred during analysis.';
        }

        $analysis->update([
            'status' => MatchAnalysisStatus::Failed,
            'failure_code' => $failureCode,
            'failure_reason' => $failureReason,
            'failed_at' => now(),
        ]);
    }

    private function sanitizeCode(?\Throwable $e): string
    {
        if ($e instanceof RequirementClassifierException) {
            return $e->problemCode;
        }

        return 'analysis_failed';
    }

    private function sanitizeReason(?\Throwable $e): ?string
    {
        if ($e instanceof RequirementClassifierException) {
            return match ($e->problemCode) {
                'ai_classifier_provider_not_configured' => 'AI classification is currently unavailable. Please try again later.',
                'ai_classifier_rate_limited' => 'AI provider rate limit exceeded. Try again later.',
                'ai_classifier_authentication_failed' => 'AI classification is currently unavailable. Please try again later.',
                'ai_classifier_timeout' => 'AI provider did not respond within the configured timeout.',
                'ai_classifier_unavailable' => 'AI provider is temporarily unavailable.',
                'ai_classifier_malformed_response' => 'Invalid AI output received.',
                'ai_classifier_schema_invalid' => 'AI output failed validation.',
                default => 'An error occurred during match analysis.',
            };
        }

        if ($e === null) {
            return null;
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
