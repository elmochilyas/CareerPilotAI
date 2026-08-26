<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class RefreshCompanyResearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'company_website' => ['nullable', 'string', 'url', 'max:500'],
            'pasted_content' => ['nullable', 'string', 'max:20000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('company_website') && is_string($this->input('company_website'))) {
            $this->merge(['company_website' => trim((string) $this->input('company_website'))]);
        }

        if ($this->has('pasted_content') && is_string($this->input('pasted_content'))) {
            $this->merge(['pasted_content' => trim((string) $this->input('pasted_content'))]);
        }
    }
}
