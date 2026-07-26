<?php

namespace App\Domain\CvIngestion\Actions;

use App\Domain\CvIngestion\Enums\CvDocumentStatus;
use App\Domain\CvIngestion\Services\FileValidatorService;
use App\Exceptions\Api\ConflictException;
use App\Exceptions\Api\UnprocessableEntityException;
use App\Jobs\ProcessCvDocumentJob;
use App\Models\CvDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UploadCvAction
{
    public function __construct(private FileValidatorService $validator) {}

    public function execute(User $user, UploadedFile $file, string $mode = 'create_new'): CvDocument
    {
        $errors = $this->validator->validate($file);

        if (! empty($errors)) {
            throw new UnprocessableEntityException(
                $errors[0]['message'],
                $errors[0]['code'],
            );
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $storedName = Str::uuid()->toString().'.'.$extension;
        $storagePath = config('cv-ingestion.storage_path', 'cv-ingestion');
        $disk = config('cv-ingestion.storage_disk', 'local');
        $storedPath = $storagePath.'/'.$storedName;

        $file->storeAs($storagePath, $storedName, $disk);

        $checksum = hash_file('sha256', $file->getRealPath());

        $existing = CvDocument::where('user_id', $user->id)
            ->where('checksum', $checksum)
            ->first();

        if ($existing) {
            Storage::disk($disk)->delete($storedPath);

            if ($existing->status === CvDocumentStatus::Deleted) {
                $existing->suggestions()->delete();
                $existing->processingRuns()->delete();
                $existing->importBatches()->delete();

                $file->storeAs($storagePath, $storedName, $disk);

                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $file->getRealPath());
                finfo_close($finfo);

                $existing->update([
                    'original_name' => $file->getClientOriginalName(),
                    'stored_path' => $storedPath,
                    'stored_name' => $storedName,
                    'mime_type' => $mimeType,
                    'size' => $file->getSize(),
                    'checksum' => $checksum,
                    'status' => CvDocumentStatus::Pending,
                    'metadata' => ['mode' => $mode],
                    'failure_reason' => null,
                    'failure_code' => null,
                ]);

                $existing->update(['status' => CvDocumentStatus::Queued]);

                ProcessCvDocumentJob::dispatch($existing->id)
                    ->onQueue(config('cv-ingestion.queue', 'cv-ingestion'))
                    ->delay(now()->addSeconds(3));

                return $existing->fresh();
            }

            throw new ConflictException(
                'A CV with identical content was already uploaded.',
                'file_duplicate',
                ['existing_cv_id' => $existing->id],
            );
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file->getRealPath());
        finfo_close($finfo);

        $document = CvDocument::create([
            'user_id' => $user->id,
            'original_name' => $file->getClientOriginalName(),
            'stored_path' => $storedPath,
            'stored_name' => $storedName,
            'mime_type' => $mimeType,
            'size' => $file->getSize(),
            'checksum' => $checksum,
            'status' => CvDocumentStatus::Pending,
            'metadata' => ['mode' => $mode],
        ]);

        $document->update(['status' => CvDocumentStatus::Queued]);

        ProcessCvDocumentJob::dispatch($document->id)
            ->onQueue(config('cv-ingestion.queue', 'cv-ingestion'))
            ->delay(now()->addSeconds(3));

        return $document->fresh();
    }
}
