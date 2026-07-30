<?php

namespace App\Http\Resources\Api\V1;

use App\Models\JobRequirement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin JobRequirement */
class RequirementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category' => $this->category,
            'content' => $this->content,
            'classification' => $this->classification,
            'language' => $this->language,
            'language_proficiency' => $this->language_proficiency,
            'source_evidence' => $this->source_evidence,
            'display_order' => $this->display_order,
        ];
    }
}
