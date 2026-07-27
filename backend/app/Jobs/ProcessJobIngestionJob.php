<?php

namespace App\Jobs;

use App\Domain\Opportunities\Actions\MarkIngestionFailedAction;
use App\Domain\Opportunities\Enums\JobIngestionStatus;
use App\Domain\Opportunities\Services\JobIngestionStateService;
use App\Models\JobOpportunityIngestion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Config;

class ProcessJobIngestionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 300;

    public int $tries = 3;

    public function __construct(
        public int $ingestionId,
    ) {
        $this->onQueue(Config::get('job-ingestion.queue', 'job-ingestion'));
    }

    public function handle(JobIngestionStateService $stateService): void
    {
        $ingestion = JobOpportunityIngestion::find($this->ingestionId);

        if ($ingestion === null) {
            return;
        }

        if ($ingestion->status === JobIngestionStatus::Cancelled || $ingestion->status === JobIngestionStatus::Confirmed) {
            return;
        }

        if ($ingestion->status !== JobIngestionStatus::Draft) {
            return;
        }

        $stateService->transition($ingestion->status, JobIngestionStatus::Queued);
        $ingestion->update(['status' => JobIngestionStatus::Queued]);

        ExtractJobInformationJob::dispatch($this->ingestionId, $ingestion->version);
    }

    public function failed(\Throwable $e, MarkIngestionFailedAction $markFailed): void
    {
        $markFailed->execute(
            ingestionId: $this->ingestionId,
            failureCode: 'pipeline_error',
            failureReason: 'Failed to start processing the job description.',
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
}
