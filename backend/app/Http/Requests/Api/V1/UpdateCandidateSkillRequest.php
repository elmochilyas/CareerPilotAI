<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Skills\Enums\ProficiencyLevel;
use App\Domain\Skills\Enums\SkillState;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateCandidateSkillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'state' => ['nullable', new Enum(SkillState::class)],
            'proficiency_level' => ['nullable', new Enum(ProficiencyLevel::class)],
            'years_experience' => ['nullable', 'numeric', 'min:0', 'max:30'],
            'last_used_at' => ['nullable', 'date'],
            'updated_at' => ['nullable', 'string'],
        ];
    }
}
