<?php

namespace App\Domain\CompanyResearch\Actions;

use App\Domain\CompanyResearch\Services\CompanyResolver;
use App\Exceptions\Api\ConflictException;
use App\Jobs\ResearchCompanyJob;
use App\Models\Company;
use App\Models\JobOpportunity;
use App\Support\RequestIdContext;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class StartCompanyResearchAction
{
    public function __construct(
        private CompanyResolver $resolver,
    ) {}

    /**
     * @return Company the locked company now in processing state
     */
    public function execute(
        JobOpportunity $opportunity,
        ?string $companyWebsite = null,
        ?string $pastedContent = null,
        bool $isRefresh = false,
    ): Company {
        return DB::transaction(function () use ($opportunity, $companyWebsite, $pastedContent): Company {
            // Ensure opportunity has a company
            $company = $this->ensureCompany($opportunity, $companyWebsite);

            // Lock the company row
            $locked = Company::query()
                ->whereKey($company->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->research_status === 'processing') {
                throw new ConflictException(
                    'Company research is already in progress.',
                    'research_in_progress',
                );
            }

            // Update status to processing and sync JSON status for resource consistency
            $existingResearch = $locked->research;

            if (! is_array($existingResearch)) {
                $existingResearch = [];
            }

            $existingResearch['status'] = 'processing';
            $existingResearch['version'] = $existingResearch['version'] ?? 1;

            $locked->update([
                'research_status' => 'processing',
                'research' => $existingResearch,
                'research_failure_code' => null,
            ]);

            // Update website if supplied and different
            if ($companyWebsite !== null && trim($companyWebsite) !== '' && $locked->website !== $companyWebsite) {
                $canonical = $this->resolver->canonicalizeDomain($companyWebsite);

                $locked->update([
                    'website' => $companyWebsite,
                    'website_canonical' => $canonical,
                ]);
            }

            $requestId = RequestIdContext::get();

            ResearchCompanyJob::dispatch(
                companyId: $locked->id,
                opportunityId: $opportunity->id,
                requestId: $requestId,
                pastedContent: $pastedContent !== null ? mb_substr($pastedContent, 0, Config::integer('company-research.max_pasted_content_length', 20000)) : null,
                suppliedWebsite: $companyWebsite,
            )->afterCommit()->onQueue(Config::string('company-research.queue', 'company-research'));

            return $locked->fresh() ?? $locked;
        });
    }

    private function ensureCompany(JobOpportunity $opportunity, ?string $companyWebsite): Company
    {
        if ($opportunity->company_id !== null) {
            $existing = Company::find($opportunity->company_id);

            if ($existing !== null) {
                return $existing;
            }
        }

        $companyName = $opportunity->company_name;
        $website = $companyWebsite ?? $opportunity->source_url ?? $opportunity->application_url;

        // Try resolver
        $resolved = null;

        try {
            $resolved = $this->resolver->resolveOrCreate($companyName, $website);
        } catch (\Throwable $e) {
            // fall through to manual
        }

        if ($resolved !== null) {
            // Ensure opportunity's company_id set
            if ($opportunity->company_id !== $resolved->id) {
                $opportunity->update(['company_id' => $resolved->id]);
            }

            return $resolved;
        }

        // Resolver returned null (ambiguous) but we still need a company row for storage
        // Create distinct company for this opportunity to avoid merging ambiguous names
        $fallbackName = trim((string) $companyName);

        if ($fallbackName === '') {
            $fallbackName = 'Company for Opportunity #'.$opportunity->id;
        }

        $canonical = $website !== null ? $this->resolver->canonicalizeDomain($website) : null;
        $normalized = $this->resolver->normalizeName($fallbackName);

        $company = Company::query()->create([
            'name' => $fallbackName,
            'website' => $website,
            'name_normalized' => $normalized,
            'website_canonical' => $canonical,
            'research_status' => 'not_researched',
            'research_version' => 1,
        ]);

        $opportunity->update(['company_id' => $company->id]);

        return $company;
    }
}
