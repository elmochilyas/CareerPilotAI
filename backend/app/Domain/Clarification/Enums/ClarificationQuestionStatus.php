<?php

namespace App\Domain\Clarification\Enums;

enum ClarificationQuestionStatus: string
{
    case Pending = 'pending';
    case Answered = 'answered';
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
            self::Pending => [self::Answered, self::Skipped, self::Expired],
            self::Answered => [self::Expired],
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
        return in_array($this, [self::Answered, self::Skipped, self::Expired], true);
    }
}
