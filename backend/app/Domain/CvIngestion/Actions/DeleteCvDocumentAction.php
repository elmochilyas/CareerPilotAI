<?php

namespace App\Domain\CvIngestion\Actions;

use App\Domain\CvIngestion\Enums\CvDocumentStatus;
use App\Exceptions\Api\ConflictException;
use App\Models\CvDocument;
use Illuminate\Support\Facades\Storage;

class DeleteCvDocumentAction
{
    public function execute(CvDocument $document): void
    {
        if ($document->status->isProcessing()) {
            throw new ConflictException(
                'Document is currently being processed. Please try again later.',
                'document_processing',
            );
        }

        $disk = config('cv-ingestion.storage_disk', 'local');
        Storage::disk($disk)->delete($document->stored_path);

        $document->suggestions()->delete();
        $document->update(['status' => CvDocumentStatus::Deleted]);
    }
}
