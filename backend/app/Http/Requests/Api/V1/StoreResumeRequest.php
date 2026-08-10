<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreResumeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'opportunity_id' => ['required', 'integer', 'exists:job_opportunities,id'],
            'title' => ['nullable', 'string', 'max:255'],
        ];
    }
}
