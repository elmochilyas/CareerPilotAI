<?php

namespace App\Http\Requests\Api\V1\CvIngestion;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveReviewDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['accepted', 'rejected', 'keep_existing', 'edited', 'create_new', 'update_existing'])],
            'edited_value' => ['sometimes', 'nullable', 'array'],
            'action' => ['sometimes', 'nullable', Rule::in(['create_new', 'update_existing'])],
            'target_id' => ['sometimes', 'nullable', 'integer', 'exists:profile_items,id'],
        ];
    }
}
