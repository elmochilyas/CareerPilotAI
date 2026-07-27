<?php

namespace App\Jobs;

use App\Domain\Opportunities\Actions\AnalyzeJobAction;
use App\Domain\Opportunities\Actions\MarkIngestionFailedAction;
use App\Domain\Opportunities\Enums\JobIngestionStatus;
use App\Exceptions\Api\ConflictException;
use App\Models\JobOpportunityIngestion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Config;

class ExtractJobInformationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 300;

    public int $tries = 3;

    public function __construct(
        public int $ingestionId,
        public ?int $expectedVersion = null,
    ) {
        $this->onQueue(Config::get('job-ingestion.queue', 'job-ingestion'));
    }

    public function handle(AnalyzeJobAction $analyzeAction): void
    {
        $ingestion = JobOpportunityIngestion::find($this->ingestionId);

        if ($ingestion === null) {
            return;
        }

        if ($ingestion->status === JobIngestionStatus::Cancelled || $ingestion->status === JobIngestionStatus::Confirmed) {
            return;
        }

        if ($ingestion->status !== JobIngestionStatus::Queued) {
            return;
        }

        if ($this->expectedVersion !== null && $ingestion->version !== $this->expectedVersion) {
            return;
        }

        $analyzeAction->execute($ingestion, $this->expectedVersion);
    }

    public function failed(\Throwable $e, MarkIngestionFailedAction $markFailed): void
    {
        $failureCode = $e instanceof ConflictException
            ? $e->getErrorCode()
            : 'unexpected_processing_failure';

        $markFailed->execute(
            ingestionId: $this->ingestionId,
            failureCode: $failureCode,
            failureReason: $this->failureReason($failureCode),
            previous: $e,
        );
    }

    public function middleware(): array
    {
        return [new WithoutOverlapping((string) $this->ingestionId)];
    }

    public function backoff(): array
    {
        return Config::array('job-ingestion.retry_backoff', [10, 30, 60]);
    }

    private function failureReason(string $failureCode): string
    {
        return match ($failureCode) {
            'ai_provider_timeout' => 'The analysis took too long. Please try again.',
            'ai_provider_rate_limited' => 'The analysis service is busy. Please try again shortly.',
            'ai_provider_unavailable' => 'The analysis service is temporarily unavailable.',
            'suggestion_persistence_failed' => 'The analysis could not be saved safely. Please try again.',
            default => 'We could not complete the analysis safely.',
        };
    }
}
