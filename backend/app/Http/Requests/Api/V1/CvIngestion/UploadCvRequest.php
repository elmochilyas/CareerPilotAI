<?php

namespace App\Http\Requests\Api\V1\CvIngestion;

use Illuminate\Foundation\Http\FormRequest;

class UploadCvRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxSize = config('cv-ingestion.max_file_size', 20971520) / 1024;

        return [
            'file' => [
                'required',
                'file',
                "max:{$maxSize}",
                'mimes:pdf,docx',
                'mimetypes:application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ],
            'mode' => ['sometimes', 'string', 'in:create_new,update_existing'],
        ];
    }
}
