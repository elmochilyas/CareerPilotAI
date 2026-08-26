<?php

namespace App\Domain\CompanyResearch\Services\Contracts;

use App\Domain\CompanyResearch\Data\CompanyResearchResult;
use App\Models\Company;
use App\Models\JobOpportunity;

interface CompanyResearchAnalyzer
{
    /**
     * @param  array<int, array{url: string, title: ?string, content: string, source_type: string, retrieved_at: ?string}>  $evidence
     */
    public function analyze(array $evidence, JobOpportunity $opportunity, ?Company $company): CompanyResearchResult;
}
