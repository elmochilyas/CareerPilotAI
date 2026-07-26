<?php

namespace App\Http\Controllers\Api\V1\CvIngestion;

use App\Domain\CvIngestion\Actions\ApplyImportAction;
use App\Domain\CvIngestion\Actions\DeleteCvDocumentAction;
use App\Domain\CvIngestion\Actions\PreviewImportAction;
use App\Domain\CvIngestion\Actions\RetryProcessingAction;
use App\Domain\CvIngestion\Actions\SaveBatchReviewDecisionsAction;
use App\Domain\CvIngestion\Actions\SaveReviewDecisionAction;
use App\Domain\CvIngestion\Actions\UploadCvAction;
use App\Domain\CvIngestion\Data\BatchDecisionData;
use App\Domain\CvIngestion\Data\ImportDecisionData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CvIngestion\BatchReviewDecisionRequest;
use App\Http\Requests\Api\V1\CvIngestion\SaveReviewDecisionRequest;
use App\Http\Requests\Api\V1\CvIngestion\UploadCvRequest;
use App\Http\Resources\Api\V1\CvDocumentResource;
use App\Http\Resources\Api\V1\CvImportBatchResource;
use App\Http\Resources\Api\V1\CvSuggestionResource;
use App\Models\CvDocument;
use App\Models\CvImportBatch;
use App\Models\CvSuggestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CvDocumentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $documents = CvDocument::with('latestRun')
            ->where('user_id', $request->user()->id)
            ->whereNotIn('status', ['deleted'])
            ->orderBy('created_at', 'desc')
            ->cursorPaginate(20);

        return response()->json([
            'data' => CvDocumentResource::collection($documents->items()),
            'meta' => [
                'next_cursor' => $documents->nextCursor()?->encode(),
                'has_more' => $documents->hasMorePages(),
            ],
        ]);
    }

    public function store(UploadCvRequest $request, UploadCvAction $action): JsonResponse
    {
        Gate::authorize('create', CvDocument::class);

        $document = $action->execute(
            $request->user(),
            $request->file('file'),
            $request->input('mode', 'create_new'),
        );

        return response()->json(
            ['data' => new CvDocumentResource($document)],
            Response::HTTP_CREATED,
        );
    }

    public function show(Request $request, CvDocument $cvDocument): JsonResponse
    {
        Gate::authorize('view', $cvDocument);

        $cvDocument->load('latestRun');

        return response()->json(['data' => new CvDocumentResource($cvDocument)]);
    }

    public function download(Request $request, CvDocument $cvDocument): BinaryFileResponse|JsonResponse
    {
        Gate::authorize('download', $cvDocument);

        if ($cvDocument->status->value === 'deleted') {
            return response()->json([
                'type' => 'https://example.com/problems/gone',
                'title' => 'Document deleted',
                'status' => 410,
                'detail' => 'This document has been deleted.',
            ], 410);
        }

        $disk = config('cv-ingestion.storage_disk', 'local');

        if (! Storage::disk($disk)->exists($cvDocument->stored_path)) {
            return response()->json([
                'type' => 'https://example.com/problems/not-found',
                'title' => 'File not found',
                'status' => 404,
                'detail' => 'The file could not be found on the server.',
            ], 404);
        }

        $path = Storage::disk($disk)->path($cvDocument->stored_path);

        $safeFilename = str_replace(['"', "\r", "\n"], '', $cvDocument->original_name);

        return response()->file($path, [
            'Content-Disposition' => 'inline; filename="'.$safeFilename.'"',
            'Content-Type' => $cvDocument->mime_type,
        ]);
    }

    public function retry(Request $request, CvDocument $cvDocument, RetryProcessingAction $action): JsonResponse
    {
        Gate::authorize('retry', $cvDocument);

        $document = $action->execute($cvDocument);
        $document->load('latestRun');

        return response()->json(['data' => new CvDocumentResource($document)]);
    }

    public function destroy(Request $request, CvDocument $cvDocument, DeleteCvDocumentAction $action): JsonResponse
    {
        Gate::authorize('delete', $cvDocument);

        $action->execute($cvDocument);

        return response()->json(['message' => 'Document deleted']);
    }

    public function suggestions(Request $request, CvDocument $cvDocument): JsonResponse
    {
        Gate::authorize('view', $cvDocument);

        $query = $cvDocument->suggestions()->orderBy('created_at');

        if ($request->has('review_status')) {
            $query->where('review_status', $request->query('review_status'));
        }

        return response()->json(['data' => CvSuggestionResource::collection($query->get())]);
    }

    public function updateSuggestion(
        SaveReviewDecisionRequest $request,
        CvDocument $cvDocument,
        CvSuggestion $cvSuggestion,
        SaveReviewDecisionAction $action,
    ): JsonResponse {
        Gate::authorize('view', $cvDocument);
        Gate::authorize('update', $cvSuggestion);

        $decisionData = new ImportDecisionData(
            suggestionId: $cvSuggestion->id,
            decision: $request->input('decision'),
            editedValue: $request->input('edited_value'),
            action: $request->input('action'),
            targetId: $request->input('target_id'),
        );

        $updated = $action->execute($cvDocument, $cvSuggestion, $decisionData);

        return response()->json(['data' => new CvSuggestionResource($updated)]);
    }

    public function batchUpdateSuggestions(
        BatchReviewDecisionRequest $request,
        CvDocument $cvDocument,
        SaveBatchReviewDecisionsAction $action,
    ): JsonResponse {
        Gate::authorize('view', $cvDocument);

        $batchData = BatchDecisionData::fromArray($request->validated());
        $results = $action->execute($cvDocument, $batchData);

        return response()->json(['data' => CvSuggestionResource::collection($results)]);
    }

    public function importPreview(Request $request, CvDocument $cvDocument, PreviewImportAction $action): JsonResponse
    {
        Gate::authorize('view', $cvDocument);

        $preview = $action->execute($cvDocument);

        return response()->json(['data' => $preview]);
    }

    public function apply(Request $request, CvDocument $cvDocument, ApplyImportAction $action): JsonResponse
    {
        Gate::authorize('update', $cvDocument);

        $idempotencyKey = $request->header('Idempotency-Key', (string) Str::uuid());
        $batch = $action->execute($cvDocument, $idempotencyKey, $request->input('profile_updated_at'));

        return response()->json(['data' => new CvImportBatchResource($batch)]);
    }

    public function importResult(Request $request, CvDocument $cvDocument): JsonResponse
    {
        Gate::authorize('view', $cvDocument);

        $batch = CvImportBatch::where('cv_document_id', $cvDocument->id)
            ->orderBy('created_at', 'desc')
            ->first();

        if (! $batch) {
            return response()->json([
                'type' => 'https://example.com/problems/not-found',
                'title' => 'No import found',
                'status' => 404,
                'detail' => 'No import batch exists for this document.',
            ], 404);
        }

        return response()->json(['data' => new CvImportBatchResource($batch)]);
    }
}
