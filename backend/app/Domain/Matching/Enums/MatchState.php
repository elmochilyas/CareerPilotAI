<?php

namespace App\Domain\Matching\Enums;

enum MatchState: string
{
    case Matched = 'matched';
    case Partial = 'partial';
    case Gap = 'gap';
    case Unknown = 'unknown';

    public function isUncertain(): bool
    {
        return $this === self::Unknown;
    }

    public function isSatisfied(): bool
    {
        return in_array($this, [self::Matched, self::Partial], true);
    }
}
