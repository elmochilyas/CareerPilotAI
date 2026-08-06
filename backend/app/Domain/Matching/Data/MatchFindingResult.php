<?php

namespace App\Domain\Matching\Data;

use App\Domain\Matching\Enums\MatchImportance;
use App\Domain\Matching\Enums\MatchState;
use App\Domain\Matching\Enums\RequirementSourceType;

final readonly class MatchFindingResult
{
    /**
     * @param  array<int, array{type: string, id: int|null, label: string|null}>  $evidenceRefs
     */
    public function __construct(
        public RequirementSourceType $sourceType,
        public int $sourceId,
        public string $requirementText,
        public ?string $requirementLabel,
        public MatchImportance $importance,
        public ?string $category,
        public MatchState $matchState,
        public float $factor,
        public ?int $matchedCandidateSkillId,
        public array $evidenceRefs,
        public ?string $justification,
        public ?string $confidence,
        public ?string $classifierSource,
        public int $displayOrder,
    ) {}
}
