<?php

namespace App\Domain\Resumes\Data;

final readonly class ResumeItemData
{
    public function __construct(
        public int $sourceId,
        public string $sourceType,
        public string $text,
        public int $displayOrder,
        public ?array $metadata = null,
    ) {}
}
