<?php

namespace App\Domain\Resumes\Enums;

enum TailoringRelevance: string
{
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';
    case Excluded = 'excluded';

    public function isRelevant(): bool
    {
        return match ($this) {
            self::High, self::Medium => true,
            self::Low, self::Excluded => false,
        };
    }

    public function priority(): int
    {
        return match ($this) {
            self::High => 0,
            self::Medium => 1,
            self::Low => 2,
            self::Excluded => 3,
        };
    }
}
