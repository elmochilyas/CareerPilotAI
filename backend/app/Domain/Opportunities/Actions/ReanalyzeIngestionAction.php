<?php

namespace App\Domain\Opportunities\Actions;

use App\Domain\Opportunities\Enums\JobIngestionStatus;
use App\Exceptions\Api\ConflictException;
use App\Models\JobOpportunityIngestion;
use Illuminate\Support\Facades\DB;

class ReanalyzeIngestionAction
{
    public function execute(JobOpportunityIngestion $ingestion): JobOpportunityIngestion
    {
        return DB::transaction(function () use ($ingestion): JobOpportunityIngestion {
            $lockedIngestion = JobOpportunityIngestion::query()
                ->whereKey($ingestion->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedIngestion->status !== JobIngestionStatus::Cancelled) {
                throw new ConflictException(
                    'Only a cancelled ingestion can be reanalyzed.',
                    'ingestion_not_cancelled',
                );
            }

            $lockedIngestion->suggestions()->delete();
            $lockedIngestion->update([
                'status' => JobIngestionStatus::Queued,
                'failure_reason' => null,
                'failure_code' => null,
                'retry_count' => $lockedIngestion->retry_count + 1,
                'last_retry_at' => now(),
                'confirmed_at' => null,
                'version' => $lockedIngestion->version + 1,
            ]);

            return $lockedIngestion->refresh();
        }, attempts: 3);
    }
}
