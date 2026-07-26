<?php

namespace App\Domain\CvIngestion\Actions;

use App\Domain\CvIngestion\Data\TextExtractionResult;
use App\Domain\CvIngestion\Enums\CvDocumentStatus;
use App\Domain\CvIngestion\Services\DocxTextExtractor;
use App\Domain\CvIngestion\Services\PdfTextExtractor;
use App\Models\CvDocument;
use Illuminate\Support\Facades\Storage;

class ExtractTextFromDocumentAction
{
    public function execute(CvDocument $document): TextExtractionResult
    {
        $disk = config('cv-ingestion.storage_disk', 'local');
        $localPath = Storage::disk($disk)->path($document->stored_path);

        if (! file_exists($localPath)) {
            $document->update([
                'status' => CvDocumentStatus::Failed,
                'failure_reason' => 'File not found on storage.',
                'failure_code' => 'file_not_found',
            ]);

            throw new \RuntimeException('File not found: '.$document->stored_path);
        }

        $extractor = match ($document->mime_type) {
            'application/pdf' => new PdfTextExtractor,
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => new DocxTextExtractor,
            default => throw new \RuntimeException('Unsupported MIME type: '.$document->mime_type),
        };

        $result = $extractor->extract($localPath);

        if (trim($result->text) === '') {
            $document->update([
                'status' => CvDocumentStatus::Failed,
                'failure_reason' => 'No extractable text found. The file may be image-based or empty.',
                'failure_code' => 'file_no_text',
            ]);

            throw new \RuntimeException('No extractable text found.');
        }

        $metadata = $document->metadata ?? [];
        $metadata['extraction'] = [
            'page_count' => $result->pageCount,
            'text_length' => strlen($result->text),
            'warnings' => $result->warnings,
        ];
        $document->update([
            'status' => CvDocumentStatus::Extracting,
            'metadata' => $metadata,
        ]);

        return $result;
    }
}
