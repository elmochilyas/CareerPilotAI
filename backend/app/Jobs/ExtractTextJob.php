<?php

namespace App\Jobs;

use App\Domain\CvIngestion\Enums\CvDocumentStatus;
use App\Domain\CvIngestion\Enums\CvProcessingRunStatus;
use App\Domain\CvIngestion\Services\DocxTextExtractor;
use App\Domain\CvIngestion\Services\PdfTextExtractor;
use App\Models\CvDocument;
use App\Models\CvProcessingRun;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;

class ExtractTextJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    public int $tries = 3;

    public function __construct(
        public int $cvDocumentId,
        public string $idempotencyKey,
    ) {
        $this->onQueue(Config::get('cv-ingestion.queue', 'cv-ingestion'));
    }

    public function handle(): void
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

        $disk = Config::get('cv-ingestion.storage_disk', 'local');
        $localPath = Storage::disk($disk)->path($document->stored_path);

        if (! file_exists($localPath)) {
            $document->update([
                'status' => CvDocumentStatus::Failed,
                'failure_reason' => 'File not found on storage.',
                'failure_code' => 'file_not_found',
            ]);
            $run->update([
                'status' => CvProcessingRunStatus::Failed->value,
                'failure_reason' => 'File not found',
                'failure_code' => 'file_not_found',
                'completed_at' => now(),
            ]);

            return;
        }

        $document->update(['status' => CvDocumentStatus::Extracting]);

        $extractor = match ($document->mime_type) {
            'application/pdf' => new PdfTextExtractor,
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => new DocxTextExtractor,
            default => throw new \RuntimeException('Unsupported MIME type: '.$document->mime_type),
        };

        try {
            $result = $extractor->extract($localPath);

            if (trim($result->text) === '') {
                $document->update([
                    'status' => CvDocumentStatus::Failed,
                    'failure_reason' => 'No extractable text found. The file may be image-based or empty.',
                    'failure_code' => 'file_no_text',
                ]);
                $run->update([
                    'status' => CvProcessingRunStatus::Failed->value,
                    'failure_reason' => 'No extractable text',
                    'failure_code' => 'file_no_text',
                    'completed_at' => now(),
                ]);

                return;
            }

            $metadata = $document->metadata ?? [];
            $metadata['extraction'] = [
                'page_count' => $result->pageCount,
                'text_length' => strlen($result->text),
                'warnings' => $result->warnings,
            ];
            $metadata['extracted_text'] = $result->text;
            $document->update([
                'status' => CvDocumentStatus::Analyzing,
                'metadata' => $metadata,
            ]);

            AnalyzeCvJob::dispatch($document->id, $this->idempotencyKey)
                ->onQueue(Config::get('cv-ingestion.queue', 'cv-ingestion'));
        } catch (\Throwable $e) {
            $document->update([
                'status' => CvDocumentStatus::Failed,
                'failure_reason' => $e->getMessage(),
                'failure_code' => 'extraction_error',
            ]);
            $run->update([
                'status' => CvProcessingRunStatus::Failed->value,
                'failure_reason' => $e->getMessage(),
                'failure_code' => 'extraction_error',
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
