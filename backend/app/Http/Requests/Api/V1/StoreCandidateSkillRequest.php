<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Skills\Enums\ProficiencyLevel;
use App\Domain\Skills\Enums\SkillState;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreCandidateSkillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'skill_id' => ['nullable', 'integer', 'exists:skills,id'],
            'custom_skill_name' => ['nullable', 'string', 'max:150', 'required_without:skill_id'],
            'state' => ['required', new Enum(SkillState::class)],
            'proficiency_level' => ['required', new Enum(ProficiencyLevel::class)],
            'years_experience' => ['nullable', 'numeric', 'min:0', 'max:30'],
            'last_used_at' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'skill_id.required_without' => 'Either a skill from the catalog or a custom skill name is required.',
            'custom_skill_name.required_without' => 'Either a skill from the catalog or a custom skill name is required.',
        ];
    }
}
