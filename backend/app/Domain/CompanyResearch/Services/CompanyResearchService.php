<?php

namespace App\Domain\CompanyResearch\Services;

use App\Models\Company;
use App\Models\JobOpportunity;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;

class CompanyResearchService
{
    /**
     * Return structured brief array for an opportunity, or null if none.
     *
     * @return array<string, mixed>|null
     */
    public function getBrief(JobOpportunity $opportunity): ?array
    {
        $company = $opportunity->company;

        if ($company !== null && is_array($company->research)) {
            return $company->research;
        }

        // Also check opportunity's company via relation load fallback
        if ($opportunity->relationLoaded('company') && $opportunity->company !== null) {
            return $opportunity->company->research;
        }

        // Try fresh load if company_id exists but not loaded
        if ($opportunity->company_id !== null) {
            $c = Company::find($opportunity->company_id);

            if ($c !== null && is_array($c->research)) {
                return $c->research;
            }
        }

        return null;
    }

    public function isStale(Company $company): bool
    {
        if ($company->researched_at === null) {
            return false;
        }

        $staleDays = Config::integer('company-research.stale_days', 30);

        return Carbon::now()->greaterThan($company->researched_at->copy()->addDays($staleDays));
    }

    public function staleReason(Company $company): ?string
    {
        return $this->isStale($company) ? 'research_outdated' : null;
    }

    /**
     * Compute staleness for resource from company or opportunity.
     *
     * @return array{stale: bool, stale_reason: ?string, researched_at: ?string}
     */
    public function stalenessForOpportunity(JobOpportunity $opportunity): array
    {
        $company = null;

        if ($opportunity->relationLoaded('company')) {
            $company = $opportunity->company;
        } elseif ($opportunity->company_id !== null) {
            $company = Company::find($opportunity->company_id);
        }

        if ($company === null) {
            return [
                'stale' => false,
                'stale_reason' => null,
                'researched_at' => null,
            ];
        }

        return [
            'stale' => $this->isStale($company),
            'stale_reason' => $this->staleReason($company),
            'researched_at' => $company->researched_at?->toISOString(),
        ];
    }
}
