<?php

namespace App\Http\Resources\Api\V1;

use App\Models\JobOpportunitySuggestion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin JobOpportunitySuggestion */
class SuggestionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ingestion_id' => $this->ingestion_id,
            'type' => $this->type->value,
            'group_key' => $this->group_key,
            'field' => $this->field,
            'extracted_value' => $this->extracted_value,
            'edited_value' => $this->edited_value,
            'review_decision' => $this->review_decision->value,
            'source_evidence' => $this->source_evidence,
            'schema_version' => $this->schema_version,
            'resolution' => $this->resolution?->value,
            'resolved_skill_id' => $this->resolved_skill_id,
            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'version' => $this->version,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
