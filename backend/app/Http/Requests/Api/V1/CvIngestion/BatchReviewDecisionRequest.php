<?php

namespace App\Http\Requests\Api\V1\CvIngestion;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BatchReviewDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $documentId = $this->route('cvDocument')?->id;

        return [
            'decisions' => ['required', 'array', 'min:1'],
            'decisions.*.id' => [
                'required',
                'integer',
                $documentId
                    ? Rule::exists('cv_suggestions', 'id')->where('cv_document_id', $documentId)
                    : 'exists:cv_suggestions,id',
            ],
            'decisions.*.decision' => ['required', Rule::in(['accepted', 'rejected', 'keep_existing', 'edited', 'create_new', 'update_existing'])],
            'decisions.*.edited_value' => ['sometimes', 'nullable', 'array'],
            'decisions.*.action' => ['sometimes', 'nullable', Rule::in(['create_new', 'update_existing'])],
        ];
    }
}
