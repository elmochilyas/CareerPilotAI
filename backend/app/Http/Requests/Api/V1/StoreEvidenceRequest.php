<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Skills\Enums\EvidenceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreEvidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', new Enum(EvidenceType::class)],
            'value' => ['required', 'string', 'max:2048'],
            'label' => ['nullable', 'string', 'max:255'],
            'updated_at' => ['nullable', 'string'],
        ];
    }
}
