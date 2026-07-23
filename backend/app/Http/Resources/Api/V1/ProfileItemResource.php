<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ProfileItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ProfileItem */
class ProfileItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'type' => $this->type->value, 'title' => $this->title, 'organization' => $this->organization, 'location' => $this->location, 'start_date' => $this->start_date?->toDateString(), 'end_date' => $this->end_date?->toDateString(), 'description' => $this->description, 'metadata' => $this->metadata, 'display_order' => $this->display_order, 'created_at' => $this->created_at?->toIso8601String(), 'updated_at' => $this->updated_at?->toIso8601String()];
    }
}
