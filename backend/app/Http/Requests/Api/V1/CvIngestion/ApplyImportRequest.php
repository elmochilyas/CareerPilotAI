<?php

namespace App\Http\Requests\Api\V1\CvIngestion;

use Illuminate\Foundation\Http\FormRequest;

class ApplyImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'idempotency_key' => ['sometimes', 'string', 'max:64'],
        ];
    }
}
