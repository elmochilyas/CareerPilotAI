<?php

namespace App\Domain\Clarification\Actions;

use App\Domain\Clarification\Enums\ClarificationAnswerStatus;
use App\Domain\Clarification\Enums\ClarificationProposalStatus;
use App\Domain\Clarification\Services\ClarificationAuditWriter;
use App\Exceptions\Api\ConflictException;
use App\Exceptions\Api\NotFoundException;
use App\Exceptions\Api\UnprocessableEntityException;
use App\Models\ClarificationAnswer;
use App\Models\ClarificationProposal;
use App\Models\ClarificationQuestion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Reviews a pending answer's proposal. Without a decision the proposal is built
 * (when missing) and returned for preview with no mutation. An accept or edit
 * applies the trusted mutation transactionally through ApplyProposalAction; a
 * reject or skip records the rejection without any profile change. The
 * skill-verified state is never editable.
 */
final class ReviewAnswerAction
{
    public function __construct(
        private readonly BuildProposalAction $buildProposal,
        private readonly ApplyProposalAction $applyProposal,
    ) {}

    /**
     * @return array{answer: ClarificationAnswer, proposal: ClarificationProposal}
     */
    public function execute(
        User $user,
        ClarificationQuestion $question,
        ?string $decision,
        ?string $editedValue = null,
    ): array {
        $answer = ClarificationAnswer::query()
            ->where('question_id', $question->id)
            ->where('user_id', $user->id)
            ->with('proposal')
            ->first();

        if ($answer === null) {
            throw new NotFoundException(
                'No answer exists for this clarification question.',
                'clarification_answer_not_found',
            );
        }

        $proposal = $answer->proposal ?? $this->buildProposal->execute($answer);

        if ($decision === null) {
            return $this->result($proposal);
        }

        return match ($decision) {
            'accept' => $this->result($this->applyProposal->execute($proposal)),
            'edit' => $this->result($this->applyProposal->execute($this->editProposal($proposal, $editedValue))),
            'reject' => $this->result($this->recordRejection($proposal, ClarificationAnswerStatus::Rejected, ClarificationProposalStatus::Rejected)),
            'skip' => $this->result($this->recordRejection($proposal, ClarificationAnswerStatus::Skipped, ClarificationProposalStatus::Skipped)),
            default => throw new UnprocessableEntityException(
                'Unknown review decision.',
                'invalid_review_decision',
            ),
        };
    }

    /**
     * @return array{answer: ClarificationAnswer, proposal: ClarificationProposal}
     */
    private function result(ClarificationProposal $proposal): array
    {
        $answer = $proposal->answer;
        $answer->loadMissing('proposal');

        return [
            'answer' => $answer,
            'proposal' => $proposal,
        ];
    }

    /**
     * Applies an edit to an editable proposal value (years of experience or
     * evidence URL) and persists it. Skill state is never editable.
     */
    private function editProposal(ClarificationProposal $proposal, ?string $editedValue): ClarificationProposal
    {
        if ($editedValue === null || trim($editedValue) === '') {
            throw new UnprocessableEntityException(
                'An edited value is required to review with an edit.',
                'invalid_review_edit',
            );
        }

        $after = $proposal->after_value ?? [];

        if (array_key_exists('years_experience', $after)) {
            if (! is_numeric($editedValue)) {
                throw new UnprocessableEntityException(
                    'The years of experience must be a number.',
                    'invalid_review_edit',
                );
            }

            $after['years_experience'] = (float) $editedValue;
        } elseif (isset($after['evidence'][0]['value'])) {
            if (! filter_var($editedValue, FILTER_VALIDATE_URL)) {
                throw new UnprocessableEntityException(
                    'The edited evidence must be a valid URL.',
                    'invalid_evidence_url',
                );
            }

            $after['evidence'][0]['value'] = $editedValue;
        } else {
            throw new UnprocessableEntityException(
                'This proposal does not support editing its value.',
                'proposal_not_editable',
            );
        }

        return DB::transaction(function () use ($proposal, $after): ClarificationProposal {
            $locked = ClarificationProposal::query()
                ->whereKey($proposal->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== ClarificationProposalStatus::Proposed) {
                throw new ConflictException(
                    'This proposal can no longer be edited.',
                    'proposal_not_reviewable',
                );
            }

            $locked->update(['after_value' => $after]);

            return $locked->load('answer');
        }, attempts: 3);
    }

    private function recordRejection(
        ClarificationProposal $proposal,
        ClarificationAnswerStatus $answerStatus,
        ClarificationProposalStatus $proposalStatus,
    ): ClarificationProposal {
        return DB::transaction(function () use ($proposal, $answerStatus, $proposalStatus): ClarificationProposal {
            $locked = ClarificationProposal::query()
                ->whereKey($proposal->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== ClarificationProposalStatus::Proposed) {
                throw new ConflictException(
                    'This proposal can no longer be reviewed.',
                    'proposal_not_reviewable',
                );
            }

            $answer = $locked->answer()->lockForUpdate()->firstOrFail();

            if (! $answer->status->isOpen()) {
                throw new ConflictException(
                    'This answer is no longer reviewable.',
                    'answer_not_reviewable',
                );
            }

            $locked->update(['status' => $proposalStatus]);
            $answer->update([
                'status' => $answerStatus,
                'proposal_id' => $locked->id,
            ]);

            // Append-only audit for rejection/skip (best-effort)
            try {
                $event = $proposalStatus === ClarificationProposalStatus::Rejected ? 'proposal_rejected' : 'question_skipped';
                app(ClarificationAuditWriter::class)->write($event, [
                    'answer_id' => $answer->id,
                    'proposal_id' => $locked->id,
                    'user_id' => $answer->user_id,
                    'match_analysis_id' => $answer->question->match_analysis_id,
                ]);
            } catch (\Throwable $ignored) {
            }

            return $locked->load('answer');
        }, attempts: 3);
    }
}
