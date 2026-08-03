<?php

namespace App\Http\Resources\Api\V1;

use App\Models\JobOpportunityIngestion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin JobOpportunityIngestion */
class IngestionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'source_url' => $this->source_url,
            'personal_label' => $this->personal_label,
            'failure_reason' => $this->failure_reason,
            'failure_code' => $this->failure_code,
            'retry_count' => $this->retry_count,
            'last_retry_at' => $this->last_retry_at?->toISOString(),
            'confirmed_at' => $this->confirmed_at?->toISOString(),
            'confirmed_opportunity_id' => $this->whenLoaded(
                'opportunity',
                fn (): ?int => $this->opportunity?->id,
            ),
            'version' => $this->version,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
