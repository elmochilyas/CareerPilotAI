<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Resumes\Actions\ApproveResumeAction;
use App\Domain\Resumes\Actions\CreateResumeAction;
use App\Domain\Resumes\Actions\DeleteResumeAction;
use App\Domain\Resumes\Actions\ExportResumePdfAction;
use App\Domain\Resumes\Actions\PreviewResumeAction;
use App\Domain\Resumes\Actions\RenderResumeDocumentAction;
use App\Domain\Resumes\Actions\TailorResumeAction;
use App\Domain\Resumes\Actions\UpdateResumeContentAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreResumeRequest;
use App\Http\Requests\Api\V1\TailorResumeRequest;
use App\Http\Requests\Api\V1\UpdateResumeRequest;
use App\Http\Resources\Api\V1\ResumeListItemResource;
use App\Http\Resources\Api\V1\ResumeResource;
use App\Models\Resume;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ResumeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $profile = $request->user()->candidateProfile;

        $query = Resume::where('candidate_profile_id', $profile->id)
            ->with(['opportunity', 'candidateProfile'])
            ->orderByDesc('version_no')
            ->orderByDesc('updated_at');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('opportunity_id')) {
            $query->where('job_opportunity_id', $request->input('opportunity_id'));
        }

        $resumes = $query->cursorPaginate(20);

        return response()->json([
            'data' => ResumeListItemResource::collection($resumes->items()),
            'meta' => [
                'next_cursor' => $resumes->nextCursor()?->encode(),
                'has_more' => $resumes->hasMorePages(),
            ],
        ]);
    }

    public function store(StoreResumeRequest $request, CreateResumeAction $action): JsonResponse
    {
        Gate::authorize('create', Resume::class);

        $resume = $action->execute(
            user: $request->user(),
            opportunityId: $request->validated('opportunity_id'),
            title: $request->validated('title'),
        );

        return response()->json(
            ['data' => new ResumeResource($resume)],
            Response::HTTP_CREATED,
        );
    }

    public function show(Resume $resume): JsonResponse
    {
        Gate::authorize('view', $resume);

        return response()->json(['data' => new ResumeResource($resume)]);
    }

    public function update(UpdateResumeRequest $request, Resume $resume, UpdateResumeContentAction $action): JsonResponse
    {
        Gate::authorize('update', $resume);

        $updated = $action->execute(
            resume: $resume,
            content: $request->validated('content'),
            proposalDecisions: $request->validated('proposal_decisions', []),
        );

        return response()->json(['data' => new ResumeResource($updated)]);
    }

    public function tailor(TailorResumeRequest $request, Resume $resume, TailorResumeAction $action): JsonResponse
    {
        Gate::authorize('update', $resume);

        $result = $action->execute(
            resume: $resume,
            useAi: $request->boolean('use_ai', true),
        );

        return response()->json([
            'data' => [
                'proposals' => $result->proposals,
                'metadata' => $result->metadata,
            ],
        ], Response::HTTP_OK);
    }

    public function approve(Resume $resume, ApproveResumeAction $action): JsonResponse
    {
        Gate::authorize('update', $resume);

        $resume = $action->execute($resume);

        return response()->json(['data' => new ResumeResource($resume)]);
    }

    public function destroy(Resume $resume, DeleteResumeAction $action): JsonResponse
    {
        Gate::authorize('delete', $resume);

        $action->execute($resume);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function preview(Resume $resume, PreviewResumeAction $action): JsonResponse
    {
        Gate::authorize('view', $resume);

        $preview = $action->execute($resume);

        return response()->json(['data' => $preview]);
    }

    public function downloadPdf(Resume $resume, ExportResumePdfAction $action): SymfonyResponse
    {
        Gate::authorize('view', $resume);

        $export = $action->execute($resume);

        return response($export['content'], Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$export['filename'].'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function documentPreview(Resume $resume, RenderResumeDocumentAction $action): SymfonyResponse
    {
        Gate::authorize('view', $resume);

        return response($action->execute($resume), Response::HTTP_OK, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; frame-ancestors 'self' http://localhost:5173",
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
