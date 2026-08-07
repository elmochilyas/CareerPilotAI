<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewClarificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'decision' => ['nullable', Rule::in(['accept', 'edit', 'reject', 'skip'])],
            'edited_value' => [
                'nullable',
                'string',
                'max:2000',
                Rule::requiredIf($this->input('decision') === 'edit'),
            ],
        ];
    }
}
