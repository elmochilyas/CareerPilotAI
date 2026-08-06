<?php

namespace App\Domain\Matching\Data;

use App\Domain\Matching\Enums\ClassifierComparisonKind;
use App\Domain\Matching\Enums\MatchImportance;

final readonly class ClassifierRequestItem
{
    /**
     * @param  list<int>  $candidateItemIds  profile item ids relevant to this comparison
     */
    public function __construct(
        public int $index,
        public string $text,
        public ClassifierComparisonKind $kind,
        public ?string $category,
        public MatchImportance $importance,
        public array $candidateItemIds,
    ) {}
}
