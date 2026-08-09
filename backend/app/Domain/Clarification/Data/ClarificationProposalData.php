<?php

namespace App\Domain\Clarification\Data;

use App\Models\ClarificationProposal;

final class ClarificationProposalData
{
    public function __construct(
        public readonly int $id,
        public readonly int $answerId,
        public readonly string $targetType,
        public readonly ?int $targetId,
        public readonly string $field,
        public readonly ?array $beforeValue,
        public readonly ?array $afterValue,
        public readonly string $status,
    ) {}

    public static function fromModel(ClarificationProposal $proposal): self
    {
        return new self(
            id: $proposal->id,
            answerId: $proposal->answer_id,
            targetType: $proposal->target_type->value,
            targetId: $proposal->target_id,
            field: $proposal->field,
            beforeValue: $proposal->before_value,
            afterValue: $proposal->after_value,
            status: $proposal->status->value,
        );
    }
}
