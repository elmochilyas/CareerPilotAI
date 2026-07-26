<?php

namespace App\Domain\CvIngestion\Data;

final readonly class TextExtractionResult
{
    public function __construct(
        public string $text,
        public ?int $pageCount,
        public array $metadata,
        public array $warnings,
    ) {}
}
