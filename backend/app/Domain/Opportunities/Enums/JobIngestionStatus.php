<?php

namespace App\Domain\Opportunities\Enums;

enum JobIngestionStatus: string
{
    case Draft = 'draft';
    case Queued = 'queued';
    case Processing = 'processing';
    case ReviewReady = 'review_ready';
    case Confirmed = 'confirmed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, self::allowedTransitions($this), true);
    }

    public static function allowedTransitions(self $current): array
    {
        return match ($current) {
            self::Draft => [self::Queued, self::Cancelled],
            self::Queued => [self::Processing, self::Failed, self::Cancelled],
            self::Processing => [self::ReviewReady, self::Failed, self::Cancelled],
            self::ReviewReady => [self::Confirmed, self::Queued, self::Cancelled],
            self::Confirmed => [],
            self::Failed => [self::Queued, self::Cancelled],
            self::Cancelled => [],
        };
    }

    public function isRetryable(): bool
    {
        return $this === self::Failed;
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Confirmed, self::Cancelled], true);
    }

    public function isProcessing(): bool
    {
        return in_array($this, [self::Queued, self::Processing], true);
    }
}
