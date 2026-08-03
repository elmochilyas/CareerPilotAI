<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Opportunities\Enums\SuggestionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddManualSuggestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => [
                'required',
                'string',
                Rule::in([
                    SuggestionType::Responsibility->value,
                    SuggestionType::RequiredSkill->value,
                    SuggestionType::PreferredSkill->value,
                ]),
            ],
            'value' => [
                'required',
                'string',
                Rule::when(
                    $this->input('type') === SuggestionType::Responsibility->value,
                    ['max:2000'],
                    ['max:255'],
                ),
            ],
            'ingestion_version' => ['required', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('value')) {
            $this->merge([
                'value' => trim((string) $this->input('value')),
            ]);
        }
    }
}
