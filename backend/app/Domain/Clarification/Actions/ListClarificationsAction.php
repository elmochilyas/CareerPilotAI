<?php

namespace App\Domain\Clarification\Actions;

use App\Domain\Clarification\Data\ClarificationSessionData;
use App\Domain\Clarification\Data\ClarificationSessionQuestionData;
use App\Domain\Clarification\Enums\ClarificationAnswerStatus;
use App\Domain\Clarification\Enums\ClarificationQuestionStatus;
use App\Models\ClarificationQuestion;
use App\Models\MatchAnalysis;

/**
 * Lists the open clarification session for an analysis: the actionable
 * questions (pending or answered with a pending review) in presentation order
 * with their type, options, evidence basis, and progress. Skipped, expired,
 * and reviewed-away questions are never part of an open session.
 *
 * The session also carries how many additional questions a generate call would
 * create right now, so the brief entry point never appears for an empty,
 * dead-end session.
 */
final class ListClarificationsAction
{
    public function __construct(
        private readonly BuildQuestionSessionAction $buildAction,
    ) {}

    public function execute(MatchAnalysis $analysis): ClarificationSessionData
    {
        $questions = ClarificationQuestion::query()
            ->where('match_analysis_id', $analysis->id)
            ->where(function ($query): void {
                $query
                    ->where('status', ClarificationQuestionStatus::Pending)
                    ->orWhereHas('answer', fn ($answerQuery) => $answerQuery->where(
                        'status',
                        ClarificationAnswerStatus::Pending->value,
                    ));
            })
            ->with(['matchFinding', 'answer.proposal'])
            ->orderBy('question_no')
            ->get();

        $total = ClarificationQuestion::query()
            ->where('match_analysis_id', $analysis->id)
            ->count();

        $answered = ClarificationQuestion::query()
            ->where('match_analysis_id', $analysis->id)
            ->whereHas('answer')
            ->count();

        return new ClarificationSessionData(
            analysisId: $analysis->id,
            questions: $questions
                ->map(fn (ClarificationQuestion $question): ClarificationSessionQuestionData => ClarificationSessionQuestionData::fromModel($question))
                ->all(),
            total: $total,
            answered: $answered,
            generableCount: $this->buildAction->eligibleCount($analysis),
        );
    }
}
