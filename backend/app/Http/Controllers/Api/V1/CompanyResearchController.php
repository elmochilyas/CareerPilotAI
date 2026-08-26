<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\CompanyResearch\Actions\StartCompanyResearchAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RefreshCompanyResearchRequest;
use App\Http\Requests\Api\V1\StartCompanyResearchRequest;
use App\Http\Resources\Api\V1\CompanyResearchResource;
use App\Models\JobOpportunity;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class CompanyResearchController extends Controller
{
    public function __construct(
        private StartCompanyResearchAction $startAction,
    ) {}

    public function show(JobOpportunity $opportunity): JsonResponse
    {
        Gate::authorize('view', $opportunity);

        $opportunity->loadMissing('company');

        $company = $opportunity->company;

        if ($company === null) {
            return response()->json([
                'data' => (new CompanyResearchResource($opportunity))->toArray(request()),
            ]);
        }

        return response()->json([
            'data' => new CompanyResearchResource($company),
        ]);
    }

    public function store(JobOpportunity $opportunity, StartCompanyResearchRequest $request): JsonResponse
    {
        Gate::authorize('view', $opportunity);

        $companyWebsite = $request->validated('company_website');
        $pastedContent = $request->validated('pasted_content');

        $company = $this->startAction->execute($opportunity, $companyWebsite, $pastedContent, false);

        // Return 202 with processing status
        return response()->json([
            'data' => new CompanyResearchResource($company),
        ], 202, [
            'Location' => route('company-research.show', $opportunity),
        ]);
    }

    public function refresh(JobOpportunity $opportunity, RefreshCompanyResearchRequest $request): JsonResponse
    {
        Gate::authorize('view', $opportunity);

        $companyWebsite = $request->validated('company_website');
        $pastedContent = $request->validated('pasted_content');

        $company = $this->startAction->execute($opportunity, $companyWebsite, $pastedContent, true);

        return response()->json([
            'data' => new CompanyResearchResource($company),
        ], 202, [
            'Location' => route('company-research.show', $opportunity),
        ]);
    }
}
