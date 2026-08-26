<?php

namespace App\Domain\Clarification\Actions;

use App\Domain\Clarification\Enums\ClarificationAnswerStatus;
use App\Domain\Clarification\Enums\ClarificationAnswerType;
use App\Domain\Clarification\Enums\ClarificationQuestionStatus;
use App\Domain\Clarification\Services\ClarificationAuditWriter;
use App\Exceptions\Api\ConflictException;
use App\Exceptions\Api\UnprocessableEntityException;
use App\Models\ClarificationAnswer;
use App\Models\ClarificationQuestion;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Records the candidate's answer to one clarification question as pending
 * candidate input. The answer never mutates trusted profile data by itself:
 * a proposal must be built and explicitly accepted first. The unique
 * (question_id) constraint plus a row lock make answer creation idempotent —
 * a duplicate submit either returns the existing pending answer or surfaces
 * `answer_already_exists` instead of duplicating.
 */
final class CreateAnswerAction
{
    public function execute(
        User $user,
        ClarificationQuestion $question,
        ClarificationAnswerType $answerType,
        string $value,
        bool $acknowledgedNoEvidence = false,
    ): ClarificationAnswer {
        $answer = DB::transaction(function () use ($user, $question, $answerType, $value, $acknowledgedNoEvidence): ClarificationAnswer {
            $lockedQuestion = ClarificationQuestion::query()
                ->whereKey($question->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $existing = ClarificationAnswer::query()
                ->where('question_id', $lockedQuestion->id)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                if ($existing->status->isOpen()) {
                    return $existing;
                }

                throw new ConflictException(
                    'This question already has an answer.',
                    'answer_already_exists',
                );
            }

            if (! $lockedQuestion->status->isOpen()) {
                throw match ($lockedQuestion->status) {
                    ClarificationQuestionStatus::Expired => new UnprocessableEntityException(
                        'This clarification session has expired. Start a fresh session.',
                        'clarification_session_expired',
                    ),
                    default => new ConflictException(
                        'This question is no longer open for answers.',
                        'clarification_session_expired',
                    ),
                };
            }

            try {
                $answer = ClarificationAnswer::query()->create([
                    'user_id' => $user->id,
                    'question_id' => $lockedQuestion->id,
                    'answer_type' => $answerType,
                    'value' => $value,
                    'acknowledged_no_evidence' => $acknowledgedNoEvidence,
                    'status' => ClarificationAnswerStatus::Pending,
                ]);
            } catch (QueryException $exception) {
                $race = ClarificationAnswer::query()
                    ->where('question_id', $lockedQuestion->id)
                    ->first();

                if ($race !== null && $race->status->isOpen()) {
                    return $race;
                }

                throw $exception;
            }

            $lockedQuestion->update([
                'status' => ClarificationQuestionStatus::Answered,
            ]);

            return $answer;
        }, attempts: 3);

        // Append-only audit for answer submission (best-effort, outside transaction)
        try {
            $answer->loadMissing('question');
            app(ClarificationAuditWriter::class)->write('answer_submitted', [
                'answer_id' => $answer->id,
                'user_id' => $user->id,
                'match_analysis_id' => $answer->question->match_analysis_id,
                'target_id' => $answer->question_id,
                'metadata' => ['question_id' => $answer->question_id],
            ]);
        } catch (\Throwable $ignored) {
        }

        return $answer->load('question');
    }
}
