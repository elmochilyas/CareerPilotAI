<?php

namespace App\Domain\CvIngestion\Actions;

use App\Domain\CvIngestion\Enums\CvDocumentStatus;
use App\Exceptions\Api\ConflictException;
use App\Jobs\ProcessCvDocumentJob;
use App\Models\CvDocument;

class RetryProcessingAction
{
    public function execute(CvDocument $document): CvDocument
    {
        if (! $document->status->isRetryable()) {
            throw new ConflictException(
                'Document is not in a retryable state.',
                'document_not_retryable',
            );
        }

        $document->update([
            'status' => CvDocumentStatus::Queued,
            'failure_reason' => null,
            'failure_code' => null,
        ]);

        ProcessCvDocumentJob::dispatch($document->id)
            ->onQueue(config('cv-ingestion.queue', 'cv-ingestion'))
            ->delay(now()->addSeconds(3));

        return $document->fresh();
    }
}
