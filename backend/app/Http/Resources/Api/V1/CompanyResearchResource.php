<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\CompanyResearch\Services\CompanyResearchService;
use App\Models\Company;
use App\Models\JobOpportunity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Company
 */
class CompanyResearchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // When resource is a Company model
        if ($this->resource instanceof Company) {
            /** @var Company $company */
            $company = $this->resource;
            $service = app(CompanyResearchService::class);
            $stale = $service->isStale($company);
            $staleReason = $service->staleReason($company);
            $research = $company->research;

            if (! is_array($research) || $research === []) {
                return [
                    'status' => $company->research_status ?? 'not_researched',
                    'researched_at' => $company->researched_at?->toISOString(),
                    'stale' => $stale,
                    'stale_reason' => $staleReason,
                    'version' => $company->research_version ?? 1,
                    'fallback_reason' => $company->research_failure_code,
                    'brief' => null,
                    'sources' => [],
                    'generated_at' => null,
                ];
            }

            return [
                'status' => $research['status'] ?? $company->research_status ?? 'completed',
                'researched_at' => $company->researched_at?->toISOString(),
                'stale' => $stale,
                'stale_reason' => $staleReason,
                'version' => $research['version'] ?? $company->research_version ?? 1,
                'fallback_reason' => $research['fallback_reason'] ?? $company->research_failure_code,
                'brief' => $research,
                'overview' => $research['overview'] ?? null,
                'products' => $research['products'] ?? [],
                'technology_context' => $research['technology_context'] ?? [],
                'role_context' => $research['role_context'] ?? [],
                'recent_information' => $research['recent_information'] ?? [],
                'candidate_preparation' => $research['candidate_preparation'] ?? [],
                'sources' => $research['sources'] ?? [],
                'generated_at' => $research['generated_at'] ?? null,
                'ai_meta' => $research['ai_meta'] ?? null,
            ];
        }

        // When resource is JobOpportunity with no company
        if ($this->resource instanceof JobOpportunity) {
            return [
                'status' => 'not_researched',
                'researched_at' => null,
                'stale' => false,
                'stale_reason' => null,
                'version' => 1,
                'fallback_reason' => null,
                'brief' => null,
                'sources' => [],
                'generated_at' => null,
            ];
        }

        // Generic array fallback (for not_researched synthetic)
        if (is_array($this->resource)) {
            return $this->resource;
        }

        return [
            'status' => 'not_researched',
            'researched_at' => null,
            'stale' => false,
            'stale_reason' => null,
            'version' => 1,
            'fallback_reason' => null,
            'brief' => null,
            'sources' => [],
            'generated_at' => null,
        ];
    }
}
