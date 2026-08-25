<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Resumes\Services\ResumeWorkspacePresenter;
use App\Domain\Resumes\Services\TailoringAnalyzer;
use App\Models\Resume;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Resume */
class ResumeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $stale = false;
        $staleReason = null;

        try {
            $this->loadMissing(['candidateProfile', 'opportunity']);
            $opportunity = $this->opportunity;
            if ($opportunity !== null) {
                $analyzer = app(TailoringAnalyzer::class);
                $reason = $analyzer->computeStaleness($this->candidateProfile, $opportunity);
                if ($reason !== 'fresh' && $reason !== 'unknown') {
                    $stale = true;
                    $staleReason = $reason;
                }
            }
        } catch (\Throwable) {
            // Never break resource on staleness failure
            $stale = false;
            $staleReason = null;
        }

        return [
            'id' => $this->id,
            'candidate_profile_id' => $this->candidate_profile_id,
            'opportunity_id' => $this->opportunity_id,
            'status' => $this->status->value,
            'title' => $this->title,
            'template_key' => $this->template_key,
            'content' => app(ResumeWorkspacePresenter::class)->present($this->resource),
            'generated_by' => $this->generated_by,
            'stale' => $stale,
            'stale_reason' => $staleReason,
            'version_no' => $this->version_no,
            'profile_snapshot' => $this->profile_snapshot,
            'opportunity_snapshot' => $this->opportunity_snapshot,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
