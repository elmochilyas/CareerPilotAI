<?php

namespace App\Domain\Matching\Data;

use App\Domain\Matching\Enums\MatchImportance;
use App\Domain\Matching\Enums\RequirementSourceType;

final readonly class MatchRequirement
{
    public function __construct(
        public RequirementSourceType $sourceType,
        public int $sourceId,
        public string $text,
        public ?string $label,
        public MatchImportance $importance,
        public ?string $category,
        public ?string $sourceCategory,
        public ?string $language,
        public int $displayOrder,
    ) {}
}
