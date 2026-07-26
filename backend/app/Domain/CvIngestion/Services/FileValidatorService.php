<?php

namespace App\Domain\CvIngestion\Services;

use Illuminate\Http\UploadedFile;

class FileValidatorService
{
    /** @var array<string, string> */
    private const MIME_SIGNATURES = [
        'application/pdf' => '%PDF',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'PK',
    ];

    public function validate(UploadedFile $file): array
    {
        $errors = [];

        $extension = strtolower($file->getClientOriginalExtension());
        $mimeType = $file->getMimeType();
        $size = $file->getSize();
        $realPath = $file->getRealPath();

        if (! $realPath || ! file_exists($realPath)) {
            return [['code' => 'file_corrupt', 'message' => 'File is corrupted or unreadable.']];
        }

        if (! in_array($extension, config('cv-ingestion.allowed_extensions', ['pdf', 'docx']), true)) {
            $errors[] = ['code' => 'file_type_not_allowed', 'message' => "File type '.{$extension}' is not allowed. Allowed: pdf, docx."];
        }

        if (! in_array($mimeType, config('cv-ingestion.allowed_mime_types', []), true)) {
            $errors[] = ['code' => 'file_type_mismatch', 'message' => 'File MIME type does not match allowed types.'];
        }

        if ($size > config('cv-ingestion.max_file_size', 20971520)) {
            $errors[] = ['code' => 'file_too_large', 'message' => 'File exceeds maximum size of 20MB.'];
        }

        $header = file_get_contents($realPath, false, null, 0, 4);
        if ($header === false) {
            $errors[] = ['code' => 'file_corrupt', 'message' => 'Unable to read file header.'];
        }

        $expectedSignature = self::MIME_SIGNATURES[$mimeType] ?? null;
        if ($expectedSignature !== null && ! str_starts_with($header, $expectedSignature)) {
            $errors[] = ['code' => 'file_type_mismatch', 'message' => 'File binary signature does not match the expected type.'];
        }

        $protectionErrors = $this->checkProtection($realPath, $mimeType, $extension);
        array_push($errors, ...$protectionErrors);

        if ($extension === 'docx') {
            $structureErrors = $this->checkDocxStructure($realPath);
            array_push($errors, ...$structureErrors);
        }

        if ($this->isEmpty($realPath, $mimeType)) {
            $errors[] = ['code' => 'file_empty', 'message' => 'File appears to be empty or contains no extractable content.'];
        }

        return $errors;
    }

    private function checkProtection(string $realPath, string $mimeType, string $extension): array
    {
        if ($mimeType === 'application/pdf') {
            $handle = fopen($realPath, 'rb');
            if ($handle !== false) {
                $header = fread($handle, 1024 * 1024);
                fclose($handle);
                if ($header !== false && preg_match('/\/Encrypt\s/', $header)) {
                    return [['code' => 'file_protected', 'message' => 'PDF is password-protected or encrypted.']];
                }
            }
        }

        if ($extension === 'docx') {
            $zip = new \ZipArchive;
            if ($zip->open($realPath) === true) {
                $protected = $zip->locateName('EncryptedPackage') !== false;
                $zip->close();
                if ($protected) {
                    return [['code' => 'file_protected', 'message' => 'DOCX is password-protected or encrypted.']];
                }
            }
        }

        return [];
    }

    private function checkDocxStructure(string $realPath): array
    {
        $zip = new \ZipArchive;
        $result = $zip->open($realPath);

        if ($result !== true) {
            return [];
        }

        $hasDocument = $zip->locateName('word/document.xml') !== false;
        $zip->close();

        if (! $hasDocument) {
            return [['code' => 'file_corrupt', 'message' => 'DOCX is missing word/document.xml.']];
        }

        return [];
    }

    private function isEmpty(string $realPath, string $mimeType): bool
    {
        $size = filesize($realPath);

        if ($mimeType === 'application/pdf') {
            if ($size < 150) {
                return true;
            }
        }

        if ($mimeType === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') {
            if ($size < 1000) {
                return true;
            }
        }

        return false;
    }
}
