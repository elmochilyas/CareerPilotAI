<?php

namespace App\Http\Resources\Api\V1;

use App\Models\CandidateSkill;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CandidateSkill */
class CandidateSkillResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $skillData = null;

        if ($this->relationLoaded('skill') && $this->skill !== null) {
            $skillData = [
                'id' => $this->skill->id,
                'name' => $this->skill->name,
                'normalized_name' => $this->skill->normalized_name,
                'category' => $this->skill->category,
                'aliases' => $this->skill->relationLoaded('aliases')
                    ? $this->skill->aliases->pluck('alias')
                    : [],
            ];
        }

        $evidence = $this->evidence ?? [];
        $isVerified = $this->state->value === 'verified';
        $verificationAtRisk = $isVerified && empty($evidence);

        return [
            'id' => $this->id,
            'skill' => $skillData,
            'is_custom' => $this->skill_id === null,
            'custom_skill_name' => $this->custom_skill_name,
            'state' => $this->state->value,
            'proficiency_level' => $this->proficiency_level?->value,
            'years_experience' => $this->years_experience,
            'last_used_at' => $this->last_used_at?->toDateString(),
            'evidence' => $evidence,
            'verification_at_risk' => $verificationAtRisk,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
