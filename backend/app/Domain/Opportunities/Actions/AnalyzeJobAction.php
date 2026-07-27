<?php

namespace App\Domain\Opportunities\Actions;

use App\Domain\Opportunities\Data\SuggestionData;
use App\Domain\Opportunities\Enums\JobIngestionStatus;
use App\Domain\Opportunities\Services\Contracts\JobAnalyzer;
use App\Domain\Opportunities\Services\JobAnalysisSchemaValidator;
use App\Domain\Opportunities\Services\JobIngestionStateService;
use App\Exceptions\Api\ConflictException;
use App\Models\JobOpportunityIngestion;
use App\Models\JobOpportunitySuggestion;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class AnalyzeJobAction
{
    public function __construct(
        private JobAnalyzer $analyzer,
        private JobAnalysisSchemaValidator $schemaValidator,
        private JobIngestionStateService $stateService,
        private ResolveJobSkillsAction $skillResolver,
    ) {}

    public function execute(JobOpportunityIngestion $ingestion, ?int $expectedVersion = null): void
    {
        try {
            $this->stateService->transition($ingestion->status, JobIngestionStatus::Processing);
            $ingestion->update(['status' => JobIngestionStatus::Processing]);

            $result = $this->analyzer->analyze($ingestion->source_description);

            if (! $this->isCurrentAttempt($ingestion, $expectedVersion)) {
                return;
            }

            $validated = $this->schemaValidator->validate($result);

            try {
                DB::transaction(function () use ($ingestion, $validated): void {
                    $this->persistSuggestions($ingestion, $validated);
                    $this->skillResolver->execute($ingestion);
                });
            } catch (Throwable $exception) {
                throw new ConflictException(
                    'The analysis could not be saved safely.',
                    'suggestion_persistence_failed',
                    [
                        'processing_stage' => 'suggestion_persistence',
                        'provider_response_received' => true,
                        'validation_paths' => [],
                    ],
                    $exception,
                );
            }

            if (! $this->isCurrentAttempt($ingestion, $expectedVersion)) {
                return;
            }

            $this->stateService->transition(JobIngestionStatus::Processing, JobIngestionStatus::ReviewReady);
            $ingestion->update(['status' => JobIngestionStatus::ReviewReady]);
        } catch (Throwable $e) {
            if ($ingestion->status === JobIngestionStatus::Failed || $ingestion->status->isTerminal()) {
                throw $e;
            }

            $this->logFailure($ingestion, $e);

            if ($this->isRetryableError($e)) {
                $ingestion->update(['status' => JobIngestionStatus::Queued]);

                throw $e;
            }

            $failureCode = $this->getPermanentErrorCode($e);

            $ingestion->update([
                'status' => JobIngestionStatus::Failed,
                'failure_reason' => $this->sanitizeReason($e),
                'failure_code' => $failureCode,
                'retry_count' => $ingestion->retry_count + 1,
                'last_retry_at' => now(),
            ]);
        }
    }

    private function isCurrentAttempt(JobOpportunityIngestion $ingestion, ?int $expectedVersion): bool
    {
        $ingestion->refresh();

        if ($ingestion->status !== JobIngestionStatus::Processing) {
            return false;
        }

        return $expectedVersion === null || $ingestion->version === $expectedVersion;
    }

    private function persistSuggestions(JobOpportunityIngestion $ingestion, array $validAnalysis): void
    {
        $schemaVersion = Config::string('job-ingestion.analysis_schema_version', '1.0.0');
        $existingTypes = $ingestion->suggestions()->pluck('type')->map(fn ($t) => $t->value)->toArray();

        foreach ($validAnalysis['suggestions'] as $suggestionData) {
            if (in_array($suggestionData->type, $existingTypes, true)) {
                /** @var JobOpportunitySuggestion|null $matching */
                $matching = $ingestion->suggestions()->where('type', $suggestionData->type)->first();
                if ($matching !== null) {
                    $this->updateSuggestionRow($matching, $suggestionData);

                    continue;
                }
            }

            JobOpportunitySuggestion::create([
                'ingestion_id' => $ingestion->id,
                'type' => $suggestionData->type,
                'group_key' => $suggestionData->groupKey,
                'field' => $suggestionData->field,
                'extracted_value' => $suggestionData->extractedValue,
                'source_evidence' => $suggestionData->sourceEvidence,
                'schema_version' => $suggestionData->schemaVersion,
                'review_decision' => 'pending',
                'version' => 1,
            ]);
        }
    }

    private function updateSuggestionRow(JobOpportunitySuggestion $suggestion, SuggestionData $data): void
    {
        $suggestion->update([
            'extracted_value' => $data->extractedValue,
            'source_evidence' => $data->sourceEvidence,
            'schema_version' => $data->schemaVersion,
            'version' => $suggestion->version + 1,
        ]);
    }

    private function isRetryableError(Throwable $e): bool
    {
        if (! ($e instanceof ConflictException)) {
            return false;
        }

        return in_array($e->getErrorCode(), $this->stateService->getRetryableFailureCodes(), true);
    }

    private function getPermanentErrorCode(Throwable $e): string
    {
        if ($e instanceof ConflictException) {
            $code = $e->getErrorCode();

            return match ($code) {
                'ai_provider_not_configured',
                'ai_provider_authentication_failed',
                'invalid_ai_output',
                'unexpected_processing_failure',
                'schema_validation_failed',
                'description_invalid' => $code,
                default => 'permanent_failure',
            };
        }

        return 'permanent_failure';
    }

    private function sanitizeReason(Throwable $e): string
    {
        if ($e instanceof ConflictException) {
            return match ($e->getErrorCode()) {
                'ai_provider_timeout' => 'The analysis took too long. Please try again.',
                'ai_provider_rate_limited' => 'The analysis service is busy. Please try again shortly.',
                'ai_provider_not_configured' => 'AI analysis is currently unavailable. Please try again later.',
                'ai_provider_authentication_failed' => 'AI analysis is currently unavailable. Please try again later.',
                'ai_provider_unavailable' => 'AI provider is temporarily unavailable.',
                'invalid_ai_output' => 'We could not reliably read this job description. No information was saved.',
                'suggestion_persistence_failed' => 'The analysis finished, but could not be saved safely. Please try again.',
                'unexpected_processing_failure' => 'We could not complete the analysis safely.',
                default => 'An error occurred during job analysis.',
            };
        }

        return 'An unexpected error occurred during job analysis.';
    }

    private function logFailure(JobOpportunityIngestion $ingestion, Throwable $exception): void
    {
        $errorBag = $exception instanceof ConflictException ? $exception->getErrorBag() : [];
        $original = $exception->getPrevious() ?? $exception;

        Log::warning('Job opportunity analysis failed', [
            'ingestion_id' => $ingestion->id,
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'schema_version' => Config::string('job-ingestion.analysis_schema_version', '1.0.0'),
            'failure_code' => $exception instanceof ConflictException
                ? $exception->getErrorCode()
                : 'unexpected_processing_failure',
            'processing_stage' => $errorBag['processing_stage'] ?? 'analysis',
            'provider_response_received' => $errorBag['provider_response_received'] ?? false,
            'exception_class' => $original::class,
            'validation_paths' => $errorBag['validation_paths'] ?? [],
            'attempt' => $ingestion->retry_count + 1,
            'provider_configured' => filled(Config::get('ai.providers.openai.key')),
        ]);
    }
}
