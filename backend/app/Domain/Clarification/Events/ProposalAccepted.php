<?php

namespace App\Domain\Clarification\Events;

use App\Domain\Clarification\Enums\ClarificationTargetType;
use App\Models\ClarificationAnswer;
use App\Models\ClarificationProposal;

/**
 * Dispatched when a clarification proposal is accepted. Carries the origin
 * answer and the accepted target change so an immutable audit record can be
 * written after the profile mutation commits. Metadata is redacted: it never
 * contains raw CV text, credentials, or unnecessary PII.
 */
final class ProposalAccepted
{
    public function __construct(
        public readonly ClarificationProposal $proposal,
        public readonly ClarificationAnswer $answer,
        public readonly ClarificationTargetType $targetType,
        public readonly ?int $targetId,
        public readonly string $field,
        public readonly ?array $beforeValue,
        public readonly ?array $afterValue,
        public readonly array $metadata,
    ) {}
}
