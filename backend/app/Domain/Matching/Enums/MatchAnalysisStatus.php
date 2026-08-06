<?php

namespace App\Domain\Matching\Enums;

enum MatchAnalysisStatus: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, self::allowedTransitions($this), true);
    }

    public static function allowedTransitions(self $current): array
    {
        return match ($current) {
            self::Queued => [self::Processing, self::Failed],
            self::Processing => [self::Completed, self::Failed],
            self::Completed => [],
            self::Failed => [self::Queued],
        };
    }

    public function isRetryable(): bool
    {
        return $this === self::Failed;
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Failed], true);
    }

    public function isProcessing(): bool
    {
        return in_array($this, [self::Queued, self::Processing], true);
    }
}
