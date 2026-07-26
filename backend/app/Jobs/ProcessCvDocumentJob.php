<?php

namespace App\Jobs;

use App\Domain\CvIngestion\Actions\ValidateCvDocumentAction;
use App\Domain\CvIngestion\Enums\CvDocumentStatus;
use App\Domain\CvIngestion\Enums\CvProcessingRunStatus;
use App\Models\CvDocument;
use App\Models\CvProcessingRun;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

class ProcessCvDocumentJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public int $tries = 3;

    public function __construct(
        public int $cvDocumentId,
        public ?string $idempotencyKey = null,
    ) {
        $this->idempotencyKey ??= hash('sha256', Str::uuid()->toString());
        $this->onQueue(Config::get('cv-ingestion.queue', 'cv-ingestion'));
    }

    public function handle(ValidateCvDocumentAction $validateAction): void
    {
        $document = CvDocument::find($this->cvDocumentId);

        if (! $document || $document->status === CvDocumentStatus::Deleted) {
            return;
        }

        $existingRun = CvProcessingRun::where('idempotency_key', $this->idempotencyKey)->first();
        if ($existingRun) {
            return;
        }

        $run = CvProcessingRun::create([
            'cv_document_id' => $document->id,
            'status' => CvProcessingRunStatus::Processing->value,
            'pipeline_version' => Config::get('cv-ingestion.pipeline_version', '1.0.0'),
            'idempotency_key' => $this->idempotencyKey,
            'started_at' => now(),
        ]);

        try {
            $document->update(['status' => CvDocumentStatus::Validating]);

            $valid = $validateAction->execute($document);

            if (! $valid) {
                $run->update([
                    'status' => CvProcessingRunStatus::Failed->value,
                    'failure_reason' => $document->failure_reason,
                    'failure_code' => $document->failure_code,
                    'completed_at' => now(),
                ]);

                return;
            }

            ExtractTextJob::dispatch($document->id, $this->idempotencyKey)
                ->onQueue(Config::get('cv-ingestion.queue', 'cv-ingestion'));
        } catch (\Throwable $e) {
            $document->update([
                'status' => CvDocumentStatus::Failed,
                'failure_reason' => $e->getMessage(),
                'failure_code' => 'pipeline_error',
            ]);
            $run->update([
                'status' => CvProcessingRunStatus::Failed->value,
                'failure_reason' => $e->getMessage(),
                'failure_code' => 'pipeline_error',
                'completed_at' => now(),
            ]);
        }
    }

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [new WithoutOverlapping((string) $this->cvDocumentId)];
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return config('cv-ingestion.retry_backoff', [5, 15, 30]);
    }
}
