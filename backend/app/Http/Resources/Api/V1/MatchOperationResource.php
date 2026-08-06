<?php

namespace App\Http\Resources\Api\V1;

use App\Models\MatchAnalysis;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MatchAnalysis */
class MatchOperationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'candidate_profile_id' => $this->candidate_profile_id,
            'job_opportunity_id' => $this->job_opportunity_id,
            'request_id' => $this->request_id,
            'failure_code' => $this->failure_code,
            'failure_reason' => $this->failure_reason,
            'queued_at' => $this->queued_at?->toISOString(),
            'processing_started_at' => $this->processing_started_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'failed_at' => $this->failed_at?->toISOString(),
        ];
    }
}
