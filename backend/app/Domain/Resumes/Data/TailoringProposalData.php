<?php

namespace App\Domain\Resumes\Data;

final readonly class TailoringProposalData
{
    public function __construct(
        public int $resumeId,
        public string $sourceType,
        public ?int $sourceId,
        public string $originalText,
        public string $proposedText,
        public string $changeType,
    ) {}
}
