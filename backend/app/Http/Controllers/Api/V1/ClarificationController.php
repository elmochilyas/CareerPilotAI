<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Clarification\Actions\BuildQuestionSessionAction;
use App\Domain\Clarification\Actions\CreateAnswerAction;
use App\Domain\Clarification\Actions\ListClarificationsAction;
use App\Domain\Clarification\Actions\ReviewAnswerAction;
use App\Domain\Clarification\Actions\SkipQuestionAction;
use App\Domain\Clarification\Data\ClarificationAnswerData;
use App\Domain\Clarification\Enums\ClarificationAnswerType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AnswerClarificationRequest;
use App\Http\Requests\Api\V1\ReviewClarificationRequest;
use App\Http\Requests\Api\V1\SkipClarificationRequest;
use App\Http\Resources\Api\V1\ClarificationAnswerResource;
use App\Http\Resources\Api\V1\ClarificationSessionResource;
use App\Models\ClarificationQuestion;
use App\Models\MatchAnalysis;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ClarificationController extends Controller
{
    public function index(
        Request $request,
        MatchAnalysis $matchAnalysis,
        ListClarificationsAction $action,
    ): JsonResponse {
        Gate::authorize('view', $matchAnalysis);

        $session = $action->execute($matchAnalysis);

        return response()->json([
            'data' => new ClarificationSessionResource($session),
        ]);
    }

    public function generate(
        Request $request,
        MatchAnalysis $matchAnalysis,
        BuildQuestionSessionAction $buildAction,
        ListClarificationsAction $listAction,
    ): JsonResponse {
        Gate::authorize('view', $matchAnalysis);

        $created = DB::transaction(function () use ($matchAnalysis, $buildAction): int {
            $lockedAnalysis = MatchAnalysis::query()
                ->whereKey($matchAnalysis->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            return count($buildAction->execute($lockedAnalysis));
        });

        $session = $listAction->execute($matchAnalysis);

        return response()->json([
            'data' => new ClarificationSessionResource($session),
        ], $created > 0 ? Response::HTTP_CREATED : Response::HTTP_OK);
    }

    public function answer(
        AnswerClarificationRequest $request,
        ClarificationQuestion $clarificationQuestion,
        CreateAnswerAction $action,
    ): JsonResponse {
        Gate::authorize('answer', $clarificationQuestion);

        $answer = $action->execute(
            $request->user(),
            $clarificationQuestion,
            ClarificationAnswerType::from($request->validated('answer_type')),
            (string) $request->validated('value'),
            $request->boolean('acknowledged_no_evidence'),
        );

        return response()->json([
            'data' => new ClarificationAnswerResource(ClarificationAnswerData::fromModel($answer)),
        ], 201);
    }

    public function review(
        ReviewClarificationRequest $request,
        ClarificationQuestion $clarificationQuestion,
        ReviewAnswerAction $action,
    ): JsonResponse {
        Gate::authorize('review', $clarificationQuestion);

        $result = $action->execute(
            $request->user(),
            $clarificationQuestion,
            $request->validated('decision'),
            $request->validated('edited_value'),
        );

        Gate::authorize('view', $result['answer']);
        Gate::authorize('view', $result['proposal']);

        return response()->json([
            'data' => new ClarificationAnswerResource(ClarificationAnswerData::fromModel($result['answer'])),
        ]);
    }

    public function skip(
        SkipClarificationRequest $request,
        ClarificationQuestion $clarificationQuestion,
        SkipQuestionAction $action,
    ): JsonResponse {
        Gate::authorize('skip', $clarificationQuestion);

        $action->execute($clarificationQuestion);

        $clarificationQuestion->refresh();

        return response()->json([
            'data' => [
                'id' => $clarificationQuestion->id,
                'status' => $clarificationQuestion->status->value,
            ],
        ]);
    }
}
