<?php

namespace App\Http\Resources\Api\V1;

use App\Models\JobOpportunitySkill;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin JobOpportunitySkill */
class OpportunitySkillResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'skill_id' => $this->skill_id,
            'original_label' => $this->original_label,
            'classification' => $this->classification,
            'proficiency' => $this->proficiency,
            'years_experience' => $this->years_experience,
            'source_evidence' => $this->source_evidence,
            'display_order' => $this->display_order,
        ];
    }
}
