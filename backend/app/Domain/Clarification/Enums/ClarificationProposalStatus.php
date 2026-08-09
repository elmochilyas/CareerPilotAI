<?php

namespace App\Domain\Clarification\Enums;

enum ClarificationProposalStatus: string
{
    case Proposed = 'proposed';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Skipped = 'skipped';

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, self::allowedTransitions($this), true);
    }

    /** @return list<self> */
    public static function allowedTransitions(self $current): array
    {
        return match ($current) {
            self::Proposed => [self::Accepted, self::Rejected, self::Skipped],
            self::Accepted => [],
            self::Rejected => [],
            self::Skipped => [],
        };
    }

    public function isImmutable(): bool
    {
        return $this === self::Accepted;
    }
}
