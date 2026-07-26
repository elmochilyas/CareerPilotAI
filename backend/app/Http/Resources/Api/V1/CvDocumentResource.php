<?php

namespace App\Http\Resources\Api\V1;

use App\Models\CvDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CvDocument */
class CvDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'status' => $this->status->value,
            'failure_reason' => $this->failure_reason,
            'failure_code' => $this->failure_code,
            'metadata' => $this->metadata,
            'latest_run' => $this->whenLoaded('latestRun', fn () => new CvProcessingRunResource($this->latestRun)),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
