<?php

namespace App\Domain\Clarification\Enums;

enum ClarificationAnswerStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Skipped = 'skipped';
    case Expired = 'expired';

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, self::allowedTransitions($this), true);
    }

    /** @return list<self> */
    public static function allowedTransitions(self $current): array
    {
        return match ($current) {
            self::Pending => [self::Accepted, self::Rejected, self::Skipped, self::Expired],
            self::Accepted => [self::Expired],
            self::Rejected => [self::Expired],
            self::Skipped => [self::Expired],
            self::Expired => [],
        };
    }

    public function isOpen(): bool
    {
        return $this === self::Pending;
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Accepted, self::Rejected, self::Skipped, self::Expired], true);
    }
}
