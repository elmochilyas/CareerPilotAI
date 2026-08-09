<?php

namespace App\Http\Requests\Api\V1\CvIngestion;

use App\Domain\CvIngestion\Enums\CvSuggestionReviewStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class ListSuggestionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'review_status' => ['nullable', new Enum(CvSuggestionReviewStatus::class)],
        ];
    }
}
