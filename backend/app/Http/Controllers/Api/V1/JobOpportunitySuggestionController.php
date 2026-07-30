<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Opportunities\Actions\AddManualSuggestionAction;
use App\Domain\Opportunities\Actions\BatchSaveDecisionsAction;
use App\Domain\Opportunities\Actions\SaveSuggestionDecisionAction;
use App\Domain\Opportunities\Enums\SuggestionType;
use App\Exceptions\Api\ConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AddManualSuggestionRequest;
use App\Http\Requests\Api\V1\BatchSaveDecisionsRequest;
use App\Http\Requests\Api\V1\SaveSuggestionDecisionRequest;
use App\Http\Resources\Api\V1\SuggestionResource;
use App\Models\JobOpportunityIngestion;
use App\Models\JobOpportunitySuggestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class JobOpportunitySuggestionController extends Controller
{
    public function index(JobOpportunityIngestion $ingestion): JsonResponse
    {
        Gate::authorize('view', $ingestion);

        $suggestions = $ingestion->suggestions()->orderBy('created_at')->get();

        return response()->json([
            'data' => SuggestionResource::collection($suggestions),
        ]);
    }

    public function store(
        JobOpportunityIngestion $ingestion,
        AddManualSuggestionRequest $request,
        AddManualSuggestionAction $action,
    ): JsonResponse {
        Gate::authorize('update', $ingestion);

        $suggestion = $action->execute(
            $ingestion,
            SuggestionType::from($request->string('type')->toString()),
            $request->string('value')->trim()->toString(),
            $request->integer('ingestion_version'),
        );

        return response()->json([
            'data' => new SuggestionResource($suggestion),
        ], 201);
    }

    public function update(
        JobOpportunityIngestion $ingestion,
        JobOpportunitySuggestion $suggestion,
        SaveSuggestionDecisionRequest $request,
        SaveSuggestionDecisionAction $action,
    ): JsonResponse {
        Gate::authorize('update', $ingestion);

        if ($suggestion->ingestion_id !== $ingestion->id) {
            throw new ConflictException('Suggestion does not belong to this ingestion.', 'suggestion_mismatch');
        }

        $result = $action->execute(
            $ingestion,
            $suggestion,
            $request->input('decision'),
            $request->integer('version'),
            $request->input('edited_value'),
            $request->filled('resolved_skill_id')
                ? $request->integer('resolved_skill_id')
                : null,
        );

        return response()->json([
            'data' => new SuggestionResource($result),
        ]);
    }

    public function batch(
        JobOpportunityIngestion $ingestion,
        BatchSaveDecisionsRequest $request,
        BatchSaveDecisionsAction $action,
    ): JsonResponse {
        Gate::authorize('update', $ingestion);

        $results = $action->execute($ingestion, $request->input('decisions', []));

        return response()->json([
            'data' => SuggestionResource::collection($results),
        ]);
    }
}
