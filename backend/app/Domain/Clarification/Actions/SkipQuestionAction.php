<?php

namespace App\Domain\Clarification\Actions;

use App\Domain\Clarification\Enums\ClarificationQuestionStatus;
use App\Domain\Clarification\Services\ClarificationAuditWriter;
use App\Exceptions\Api\ConflictException;
use App\Models\CandidateProfile;
use App\Models\ClarificationQuestion;
use App\Models\MatchAnalysis;
use Illuminate\Support\Facades\Auth;
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

            // Append-only audit for skip (best-effort)
            try {
                $userId = Auth::id();
                if ($userId === null) {
                    $candidateProfileId = MatchAnalysis::whereKey($lockedQuestion->match_analysis_id)->value('candidate_profile_id');
                    if ($candidateProfileId !== null) {
                        $userId = CandidateProfile::whereKey($candidateProfileId)->value('user_id');
                    }
                }
                if ($userId !== null) {
                    app(ClarificationAuditWriter::class)->write('question_skipped', [
                        'user_id' => $userId,
                        'match_analysis_id' => $lockedQuestion->match_analysis_id,
                        'target_id' => $lockedQuestion->id,
                        'metadata' => ['question_id' => $lockedQuestion->id],
                    ]);
                }
            } catch (\Throwable $ignored) {
            }
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
