<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Matching\Data\MatchAnalysisData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MatchAnalysisData */
class MatchAnalysisResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'candidate_profile_id' => $this->candidateProfileId,
            'job_opportunity_id' => $this->jobOpportunityId,
            'status' => $this->status->value,
            'overall_score' => $this->overallScore,
            'evidence_coverage_score' => $this->evidenceCoverageScore,
            'counts' => [
                'required' => $this->requiredCount,
                'preferred' => $this->preferredCount,
                'matched' => $this->matchedCount,
                'partial' => $this->partialCount,
                'gap' => $this->gapCount,
                'unknown' => $this->unknownCount,
            ],
            'versions' => [
                'algorithm' => $this->algorithmVersion,
                'scoring' => $this->scoringVersion,
                'classifier_schema' => $this->classifierSchemaVersion,
            ],
            'fingerprints' => [
                'profile' => $this->profileFingerprint,
                'opportunity' => $this->opportunityFingerprint,
            ],
            'stale' => $this->stale,
            'latest' => $this->latest,
            'warnings' => $this->warnings,
            'failure' => [
                'code' => $this->failureCode,
                'reason' => $this->failureReason,
            ],
            'classifier' => [
                'provider' => $this->classifierProvider,
                'model' => $this->classifierModel,
                'prompt_version' => $this->classifierPromptVersion,
                'latency_ms' => $this->classifierLatencyMs,
                'tokens_prompt' => $this->classifierTokensPrompt,
                'tokens_completion' => $this->classifierTokensCompletion,
                'response_id' => $this->classifierResponseId,
                'status' => $this->classifierStatus,
            ],
            'request_id' => $this->requestId,
            'score_components' => MatchScoreComponentResource::collection($this->scoreComponents),
            'findings' => MatchFindingResource::collection($this->findings),
            'timestamps' => [
                'queued_at' => $this->queuedAt?->toISOString(),
                'processing_started_at' => $this->processingStartedAt?->toISOString(),
                'completed_at' => $this->completedAt?->toISOString(),
                'failed_at' => $this->failedAt?->toISOString(),
                'created_at' => $this->createdAt->toISOString(),
                'updated_at' => $this->updatedAt->toISOString(),
            ],
        ];
    }
}
