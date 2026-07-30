<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BatchSaveDecisionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'decisions' => ['required', 'array', 'min:1'],
            'decisions.*.id' => ['required', 'integer', 'exists:job_opportunity_suggestions,id'],
            'decisions.*.decision' => [
                'required',
                'string',
                'in:accepted,edited,rejected,keep_blank,resolved',
            ],
            'decisions.*.version' => ['required', 'integer', 'min:1'],
            'decisions.*.edited_value' => [
                'nullable',
                'array',
            ],
            'decisions.*.resolved_skill_id' => [
                'nullable',
                'integer',
                Rule::exists('skills', 'id')->where('is_active', true),
            ],
        ];
    }
}
