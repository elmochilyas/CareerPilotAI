<?php

namespace App\Domain\Opportunities\Actions;

use App\Domain\Opportunities\Enums\JobIngestionStatus;
use App\Domain\Opportunities\Services\JobIngestionStateService;
use App\Models\JobOpportunityIngestion;

class CancelIngestionAction
{
    public function __construct(
        private JobIngestionStateService $stateService,
    ) {}

    public function execute(JobOpportunityIngestion $ingestion): void
    {
        $this->stateService->assertNotTerminal($ingestion->status);

        $ingestion->update([
            'status' => JobIngestionStatus::Cancelled,
            'version' => $ingestion->version + 1,
        ]);
    }
}
