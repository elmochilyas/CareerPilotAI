<?php

namespace App\Domain\Clarification\Data;

use App\Models\ClarificationAnswer;

final class ClarificationAnswerData
{
    public function __construct(
        public readonly int $id,
        public readonly int $userId,
        public readonly int $questionId,
        public readonly string $answerType,
        public readonly string $value,
        public readonly bool $acknowledgedNoEvidence,
        public readonly string $status,
        public readonly ?ClarificationProposalData $proposal,
    ) {}

    public static function fromModel(ClarificationAnswer $answer): self
    {
        return new self(
            id: $answer->id,
            userId: $answer->user_id,
            questionId: $answer->question_id,
            answerType: $answer->answer_type->value,
            value: $answer->value,
            acknowledgedNoEvidence: $answer->acknowledged_no_evidence,
            status: $answer->status->value,
            proposal: $answer->proposal !== null
                ? ClarificationProposalData::fromModel($answer->proposal)
                : null,
        );
    }
}
