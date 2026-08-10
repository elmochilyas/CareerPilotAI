<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateResumeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'array'],
            'content.sections' => ['required', 'array'],
            'content.headline' => ['nullable', 'string', 'max:255'],
            'content.summary' => ['nullable', 'string'],
            'content.sections.*.type' => ['required', 'string', 'max:100'],
            'content.sections.*.title' => ['required', 'string', 'max:255'],
            'content.sections.*.items' => ['required', 'array'],
            'content.sections.*.items.*.source_ref' => ['sometimes', 'string', 'max:100'],
            'content.sections.*.items.*.original_text' => ['sometimes', 'string'],
            'content.sections.*.items.*.current_text' => ['sometimes', 'string'],
            'content.sections.*.items.*.source_type' => ['sometimes', 'string', 'max:100'],
            'content.sections.*.items.*.source_id' => ['sometimes', 'string', 'max:36'],
            'content.sections.*.items.*.tailored_text' => ['sometimes', 'string'],
            'content.sections.*.items.*.relevance' => ['sometimes', 'string', 'in:high,medium,low,excluded'],
            'content.sections.*.items.*.metadata' => ['nullable', 'array'],
            'proposal_decisions' => ['sometimes', 'array'],
            'proposal_decisions.*.id' => ['required', 'integer'],
            'proposal_decisions.*.status' => ['required', 'string', 'in:proposed,accepted,rejected'],
            'proposal_decisions.*.edited_text' => ['nullable', 'string'],
        ];
    }
}
