<?php

namespace App\Domain\CvIngestion\Data;

final readonly class SuggestionData
{
    /** @param array<string, mixed> $suggestedValue */
    public function __construct(
        public string $type,
        public ?string $category,
        public ?string $fieldName,
        public ?array $currentValue,
        public array $suggestedValue,
        public ?int $sourcePage,
        public ?string $sourceText,
        public string $extractionMethod,
        public string $schemaVersion,
        public ?float $confidence,
    ) {}
}
