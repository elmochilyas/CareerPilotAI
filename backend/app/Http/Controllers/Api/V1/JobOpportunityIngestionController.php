<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Opportunities\Actions\CancelIngestionAction;
use App\Domain\Opportunities\Actions\CreateIngestionAction;
use App\Domain\Opportunities\Actions\ReanalyzeIngestionAction;
use App\Domain\Opportunities\Actions\RetryIngestionAction;
use App\Domain\Opportunities\Enums\JobIngestionStatus;
use App\Exceptions\Api\ConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreIngestionRequest;
use App\Http\Resources\Api\V1\IngestionResource;
use App\Jobs\ExtractJobInformationJob;
use App\Jobs\ProcessJobIngestionJob;
use App\Models\JobOpportunityIngestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class JobOpportunityIngestionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status');

        $query = JobOpportunityIngestion::where('user_id', Auth::id())
            ->with('opportunity:id,ingestion_id');

        if ($status !== null && JobIngestionStatus::tryFrom($status) !== null) {
            $query->where('status', $status);
        }

        $ingestions = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json([
            'data' => IngestionResource::collection($ingestions),
            'meta' => [
                'current_page' => $ingestions->currentPage(),
                'last_page' => $ingestions->lastPage(),
                'per_page' => $ingestions->perPage(),
                'total' => $ingestions->total(),
            ],
        ]);
    }

    public function store(StoreIngestionRequest $request, CreateIngestionAction $action): JsonResponse
    {
        $user = Auth::user();

        $description = $request->input('source_description');
        $sourceUrl = $request->input('source_url');
        $label = $request->input('personal_label');

        $ingestion = $action->execute($user, $description, $sourceUrl, $label);

        if (! $ingestion->wasRecentlyCreated) {
            $confirmed = $ingestion->opportunity()->first();

            throw new ConflictException(
                'This job description has already been imported.',
                'duplicate_ingestion',
                [
                    'existing_ingestion_id' => $ingestion->id,
                    'existing_opportunity_id' => $confirmed?->id,
                    'existing_status' => $ingestion->status->value,
                ],
            );
        }

        $ingestion->update(['status' => JobIngestionStatus::Draft]);

        ProcessJobIngestionJob::dispatch($ingestion->id);

        return response()->json([
            'data' => new IngestionResource($ingestion->load('opportunity:id,ingestion_id')),
        ], 201);
    }

    public function show(JobOpportunityIngestion $ingestion): JsonResponse
    {
        Gate::authorize('view', $ingestion);

        return response()->json([
            'data' => new IngestionResource($ingestion->load([
                'suggestions',
                'opportunity:id,ingestion_id',
            ])),
        ]);
    }

    public function retry(Request $request, JobOpportunityIngestion $ingestion, RetryIngestionAction $action): JsonResponse
    {
        Gate::authorize('update', $ingestion);

        $action->execute($ingestion);

        $requeuedIngestion = $ingestion->fresh();
        ExtractJobInformationJob::dispatch($requeuedIngestion->id, $requeuedIngestion->version);

        return response()->json([
            'data' => new IngestionResource(
                $requeuedIngestion->load('opportunity:id,ingestion_id'),
            ),
        ]);
    }

    public function reanalyze(
        Request $request,
        JobOpportunityIngestion $ingestion,
        ReanalyzeIngestionAction $action,
    ): JsonResponse {
        Gate::authorize('update', $ingestion);

        $requeuedIngestion = $action->execute($ingestion);

        ExtractJobInformationJob::dispatch(
            $requeuedIngestion->id,
            $requeuedIngestion->version,
        );

        return response()->json([
            'data' => new IngestionResource(
                $requeuedIngestion->load('opportunity:id,ingestion_id'),
            ),
        ]);
    }

    public function destroy(Request $request, JobOpportunityIngestion $ingestion, CancelIngestionAction $action): JsonResponse
    {
        Gate::authorize('delete', $ingestion);

        if ($ingestion->status === JobIngestionStatus::Confirmed) {
            throw new ConflictException('Cannot delete a confirmed ingestion.', 'already_confirmed');
        }

        $action->execute($ingestion);

        return response()->json([
            'data' => new IngestionResource(
                $ingestion->fresh()->load('opportunity:id,ingestion_id'),
            ),
        ]);
    }

    public function source(Request $request, JobOpportunityIngestion $ingestion): JsonResponse
    {
        Gate::authorize('view', $ingestion);

        return response()->json([
            'data' => [
                'source_description' => $ingestion->source_description,
                'source_url' => $ingestion->source_url,
            ],
        ]);
    }
}
