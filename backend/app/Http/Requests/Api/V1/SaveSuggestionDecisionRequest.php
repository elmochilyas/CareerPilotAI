<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'version' => ['required', 'integer', 'min:1'],
            'edited_value' => [
                Rule::requiredIf($this->input('decision') === 'edited'),
                'nullable',
                'array',
            ],
            'resolved_skill_id' => [
                'nullable',
                'integer',
                Rule::exists('skills', 'id')->where('is_active', true),
            ],
        ];
    }
}
