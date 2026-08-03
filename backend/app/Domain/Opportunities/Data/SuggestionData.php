<?php

namespace App\Domain\Opportunities\Data;

final readonly class SuggestionData
{
    public function __construct(
        public string $type,
        public ?string $groupKey,
        public ?string $field,
        public array $extractedValue,
        public ?string $sourceEvidence,
        public string $schemaVersion,
    ) {}
}
