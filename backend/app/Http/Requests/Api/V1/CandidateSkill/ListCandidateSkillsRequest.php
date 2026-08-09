<?php

namespace App\Http\Requests\Api\V1\CandidateSkill;

use App\Domain\Skills\Enums\SkillState;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class ListCandidateSkillsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'state' => ['nullable', new Enum(SkillState::class)],
        ];
    }
}
