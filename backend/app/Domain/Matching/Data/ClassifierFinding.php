<?php

namespace App\Domain\Matching\Data;

use App\Domain\Matching\Enums\MatchState;

final readonly class ClassifierFinding
{
    /**
     * @param  array<int, array{type: string, id: int|null, label: string|null}>  $evidenceReferences
     */
    public function __construct(
        public int $index,
        public MatchState $matchState,
        public ?string $category,
        public ?float $confidence,
        public ?string $justification,
        public array $evidenceReferences,
    ) {}
}
