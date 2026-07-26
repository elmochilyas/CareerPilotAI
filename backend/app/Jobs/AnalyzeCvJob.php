<?php

namespace App\Jobs;

use App\Domain\CvIngestion\Actions\AnalyzeCvTextAction;
use App\Domain\CvIngestion\Enums\CvDocumentStatus;
use App\Models\CvDocument;
use App\Models\CvProcessingRun;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Config;

class AnalyzeCvJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public int $tries = 3;

    public function __construct(
        public int $cvDocumentId,
        public string $idempotencyKey,
        public string $extractedText,
    ) {
        $this->onQueue(Config::get('cv-ingestion.queue', 'cv-ingestion'));
    }

    public function handle(AnalyzeCvTextAction $analyzeAction): void
    {
        $document = CvDocument::find($this->cvDocumentId);

        if (! $document || $document->status === CvDocumentStatus::Deleted) {
            return;
        }

        $run = CvProcessingRun::where('idempotency_key', $this->idempotencyKey)->first();

        if (! $run) {
            $document->update([
                'status' => CvDocumentStatus::Failed,
                'failure_reason' => 'Processing run not found.',
                'failure_code' => 'run_not_found',
            ]);

            return;
        }

        try {
            $analyzeAction->execute($document, $run, $this->extractedText);
        } catch (\Throwable $e) {
            $document->update([
                'status' => CvDocumentStatus::Failed,
                'failure_reason' => $e->getMessage(),
                'failure_code' => 'analysis_error',
            ]);
            $run->update([
                'status' => 'failed',
                'failure_reason' => $e->getMessage(),
                'failure_code' => 'analysis_error',
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
