<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Skills\Enums\SkillState;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class RestoreCandidateSkillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'state' => ['required', new Enum(SkillState::class)],
            'updated_at' => ['nullable', 'string'],
        ];
    }
}
