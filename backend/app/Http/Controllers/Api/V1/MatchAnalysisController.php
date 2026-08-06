<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Matching\Actions\CreateMatchAnalysisAction;
use App\Domain\Matching\Actions\ListMatchAnalysesAction;
use App\Domain\Matching\Actions\RecalculateMatchAnalysisAction;
use App\Domain\Matching\Actions\ShowMatchAnalysisAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CreateMatchRequest;
use App\Http\Requests\Api\V1\ListMatchesRequest;
use App\Http\Requests\Api\V1\RecalculateMatchRequest;
use App\Http\Resources\Api\V1\MatchAnalysisResource;
use App\Http\Resources\Api\V1\MatchOperationResource;
use App\Models\JobOpportunity;
use App\Models\MatchAnalysis;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class MatchAnalysisController extends Controller
{
    public function store(
        CreateMatchRequest $request,
        JobOpportunity $opportunity,
        CreateMatchAnalysisAction $action,
    ): JsonResponse {
        Gate::authorize('view', $opportunity);

        $idempotencyKey = $request->validated('idempotency_key') ?? (string) Str::uuid();

        $analysis = $action->execute($opportunity->candidateProfile, $opportunity, $idempotencyKey);

        return response()->json([
            'data' => new MatchOperationResource($analysis),
        ], 202, [
            'Location' => route('matches.show', $analysis),
        ]);
    }

    public function index(
        ListMatchesRequest $request,
        JobOpportunity $opportunity,
        ListMatchAnalysesAction $action,
    ): JsonResponse {
        Gate::authorize('view', $opportunity);

        $analyses = $action->execute(
            $opportunity->candidateProfile,
            $opportunity,
            $request->validated('limit') ?? 20,
        );

        return response()->json([
            'data' => MatchAnalysisResource::collection($analyses->items()),
            'meta' => [
                'path' => $analyses->path(),
                'per_page' => $analyses->perPage(),
                'next_cursor' => $analyses->nextCursor()?->encode(),
                'prev_cursor' => $analyses->previousCursor()?->encode(),
            ],
        ]);
    }

    public function show(
        Request $request,
        MatchAnalysis $matchAnalysis,
        ShowMatchAnalysisAction $action,
    ): JsonResponse {
        Gate::authorize('view', $matchAnalysis);

        $data = $action->execute(
            $matchAnalysis,
            $matchAnalysis->candidateProfile,
            $matchAnalysis->jobOpportunity,
        );

        return response()->json([
            'data' => new MatchAnalysisResource($data),
        ]);
    }

    public function recalculate(
        RecalculateMatchRequest $request,
        MatchAnalysis $matchAnalysis,
        RecalculateMatchAnalysisAction $action,
    ): JsonResponse {
        Gate::authorize('recalculate', $matchAnalysis);

        $analysis = $action->execute(
            $matchAnalysis->candidateProfile,
            $matchAnalysis->jobOpportunity,
            $matchAnalysis,
        );

        return response()->json([
            'data' => new MatchOperationResource($analysis),
        ], 202, [
            'Location' => route('matches.show', $analysis),
        ]);
    }
}
