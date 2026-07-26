<?php

namespace App\Http\Resources\Api\V1;

use App\Models\CvProcessingRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CvProcessingRun */
class CvProcessingRunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'cv_document_id' => $this->cv_document_id,
            'status' => $this->status->value,
            'pipeline_version' => $this->pipeline_version,
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'failure_reason' => $this->failure_reason,
            'failure_code' => $this->failure_code,
            'ai_provider' => $this->ai_provider,
            'ai_model' => $this->ai_model,
            'ai_prompt_version' => $this->ai_prompt_version,
            'ai_latency_ms' => $this->ai_latency_ms,
            'ai_tokens_prompt' => $this->ai_tokens_prompt,
            'ai_tokens_completion' => $this->ai_tokens_completion,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
