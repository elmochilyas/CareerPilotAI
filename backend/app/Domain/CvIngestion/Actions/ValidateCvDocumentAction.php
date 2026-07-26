<?php

namespace App\Domain\CvIngestion\Actions;

use App\Domain\CvIngestion\Enums\CvDocumentStatus;
use App\Domain\CvIngestion\Services\FileValidatorService;
use App\Models\CvDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ValidateCvDocumentAction
{
    public function __construct(private FileValidatorService $validator) {}

    public function execute(CvDocument $document): bool
    {
        $disk = config('cv-ingestion.storage_disk', 'local');
        $localPath = Storage::disk($disk)->path($document->stored_path);

        if (! file_exists($localPath)) {
            $document->update([
                'status' => CvDocumentStatus::Failed,
                'failure_reason' => 'File not found on storage.',
                'failure_code' => 'file_not_found',
            ]);

            return false;
        }

        $uploadedFile = new UploadedFile(
            $localPath,
            $document->original_name,
            $document->mime_type,
            null,
            true,
        );

        $errors = $this->validator->validate($uploadedFile);

        if (! empty($errors)) {
            $document->update([
                'status' => CvDocumentStatus::Failed,
                'failure_reason' => $errors[0]['message'],
                'failure_code' => $errors[0]['code'],
            ]);

            return false;
        }

        $document->update(['status' => CvDocumentStatus::Validating]);

        return true;
    }
}
