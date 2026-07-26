<?php

namespace App\Http\Resources\Api\V1;

use App\Models\CvImportBatch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CvImportBatch */
class CvImportBatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'cv_document_id' => $this->cv_document_id,
            'status' => $this->status->value,
            'imported_at' => $this->imported_at?->toIso8601String(),
            'failure_reason' => $this->failure_reason,
            'failure_code' => $this->failure_code,
            'summary' => $this->summary,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
