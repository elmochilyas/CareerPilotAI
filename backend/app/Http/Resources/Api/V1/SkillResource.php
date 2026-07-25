<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Skill;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Skill */
class SkillResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'normalized_name' => $this->normalized_name,
            'category' => $this->category,
            'is_active' => $this->is_active,
            'aliases' => $this->whenLoaded('aliases', fn () => $this->aliases->pluck('alias')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
