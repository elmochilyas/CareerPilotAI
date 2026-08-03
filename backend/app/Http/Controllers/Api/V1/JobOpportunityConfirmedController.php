<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Opportunities\Actions\ConfirmOpportunityAction;
use App\Domain\Opportunities\Actions\GeneratePreviewAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ConfirmRequest;
use App\Http\Requests\Api\V1\PreviewRequest;
use App\Http\Resources\Api\V1\OpportunityResource;
use App\Http\Resources\Api\V1\PreviewResource;
use App\Models\JobOpportunity;
use App\Models\JobOpportunityIngestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class JobOpportunityConfirmedController extends Controller
{
    public function preview(
        JobOpportunityIngestion $ingestion,
        PreviewRequest $request,
        GeneratePreviewAction $action,
    ): JsonResponse {
        Gate::authorize('view', $ingestion);

        $preview = $action->execute($ingestion);

        return response()->json([
            'data' => new PreviewResource($preview),
        ]);
    }

    public function confirm(
        JobOpportunityIngestion $ingestion,
        ConfirmRequest $request,
        ConfirmOpportunityAction $action,
    ): JsonResponse {
        Gate::authorize('update', $ingestion);

        $opportunity = $action->execute(
            $ingestion,
            $request->string('version_token')->toString(),
        );

        return response()->json([
            'data' => new OpportunityResource($opportunity),
        ]);
    }

    public function index(): JsonResponse
    {
        $user = Auth::user();
        $profile = $user->candidateProfile;

        if ($profile === null) {
            return response()->json(['data' => []]);
        }

        $opportunities = JobOpportunity::where('candidate_profile_id', $profile->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'data' => OpportunityResource::collection($opportunities),
            'meta' => [
                'current_page' => $opportunities->currentPage(),
                'last_page' => $opportunities->lastPage(),
                'per_page' => $opportunities->perPage(),
                'total' => $opportunities->total(),
            ],
        ]);
    }

    public function show(JobOpportunity $opportunity): JsonResponse
    {
        Gate::authorize('view', $opportunity);

        return response()->json([
            'data' => new OpportunityResource($opportunity->load(['requirements', 'skills', 'company'])),
        ]);
    }
}
