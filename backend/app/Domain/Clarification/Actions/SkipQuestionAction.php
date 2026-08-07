<?php

namespace App\Domain\Clarification\Actions;

use App\Domain\Clarification\Enums\ClarificationQuestionStatus;
use App\Exceptions\Api\ConflictException;
use App\Models\ClarificationQuestion;
use Illuminate\Support\Facades\DB;

/**
 * Skips an open clarification question without creating an answer or proposal.
 * Skipping never mutates profile data and never triggers a stale-marking job.
 * Skipping an already-skipped or expired question is idempotent; skipping a
 * question that already has an answer is rejected.
 */
final class SkipQuestionAction
{
    public function execute(ClarificationQuestion $question): void
    {
        DB::transaction(function () use ($question): void {
            $lockedQuestion = ClarificationQuestion::query()
                ->whereKey($question->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($lockedQuestion->status, [ClarificationQuestionStatus::Skipped, ClarificationQuestionStatus::Expired], true)) {
                return;
            }

            if (! $lockedQuestion->status->isOpen()) {
                throw new ConflictException(
                    'This question already has an answer.',
                    'answer_already_exists',
                );
            }

            $lockedQuestion->update([
                'status' => ClarificationQuestionStatus::Skipped,
            ]);
        }, attempts: 3);
    }

    /**
     * True when the question is still pending.
     */
    public function isSkippable(ClarificationQuestion $question): bool
    {
        return $question->status->isOpen();
    }
}
