<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class SaveSuggestionDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'decision' => [
                'required',
                'string',
                'in:accepted,edited,rejected,keep_blank,resolved',
            ],
            'edited_value' => [
                'nullable',
                'array',
            ],
        ];
    }
}
