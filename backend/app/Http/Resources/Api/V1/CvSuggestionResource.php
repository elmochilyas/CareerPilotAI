<?php

namespace App\Http\Resources\Api\V1;

use App\Models\CvSuggestion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CvSuggestion */
class CvSuggestionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'cv_document_id' => $this->cv_document_id,
            'type' => $this->type->value,
            'category' => $this->category,
            'field_name' => $this->field_name,
            'current_value' => $this->current_value,
            'suggested_value' => $this->suggested_value,
            'source_page' => $this->source_page,
            'source_text' => $this->source_text,
            'extraction_method' => $this->extraction_method->value,
            'schema_version' => $this->schema_version,
            'confidence' => $this->confidence,
            'review_status' => $this->review_status->value,
            'reviewed_decision' => $this->reviewed_decision,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
