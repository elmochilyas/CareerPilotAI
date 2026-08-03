<?php

namespace App\Domain\Opportunities\Actions;

use App\Domain\Opportunities\Enums\JobIngestionStatus;
use App\Domain\Opportunities\Services\JobIngestionStateService;
use App\Models\JobOpportunityIngestion;

class RetryIngestionAction
{
    public function __construct(
        private JobIngestionStateService $stateService,
    ) {}

    public function execute(JobOpportunityIngestion $ingestion): void
    {
        $this->stateService->assertRetryable($ingestion->status, $ingestion->failure_code);
        $this->stateService->transition($ingestion->status, JobIngestionStatus::Queued);

        $ingestion->update([
            'status' => JobIngestionStatus::Queued,
            'failure_reason' => null,
            'failure_code' => null,
            'retry_count' => $ingestion->retry_count + 1,
            'last_retry_at' => now(),
            'version' => $ingestion->version + 1,
        ]);
    }
}
