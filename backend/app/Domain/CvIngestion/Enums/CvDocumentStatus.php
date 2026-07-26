<?php

namespace App\Domain\CvIngestion\Enums;

enum CvDocumentStatus: string
{
    case Pending = 'pending';
    case Queued = 'queued';
    case Validating = 'validating';
    case Extracting = 'extracting';
    case Analyzing = 'analyzing';
    case ReadyForReview = 'ready_for_review';
    case Importing = 'importing';
    case Imported = 'imported';
    case Failed = 'failed';
    case Deleted = 'deleted';

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, self::allowedTransitions($this), true);
    }

    public static function allowedTransitions(self $current): array
    {
        return match ($current) {
            self::Pending => [self::Queued],
            self::Queued => [self::Validating],
            self::Validating => [self::Extracting, self::Failed],
            self::Extracting => [self::Analyzing, self::Failed],
            self::Analyzing => [self::ReadyForReview, self::Failed],
            self::ReadyForReview => [self::Importing, self::Queued, self::Deleted],
            self::Importing => [self::Imported, self::ReadyForReview],
            self::Imported => [self::Deleted],
            self::Failed => [self::Queued, self::Deleted],
            self::Deleted => [],
        };
    }

    public function isRetryable(): bool
    {
        return $this === self::Failed;
    }

    public function isProcessing(): bool
    {
        return in_array($this, [self::Queued, self::Validating, self::Extracting, self::Analyzing, self::Importing], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Imported, self::Deleted], true);
    }
}
