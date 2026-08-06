<?php

namespace App\Domain\Matching\Data;

use App\Domain\Matching\Enums\MatchCategory;

final readonly class MatchScoreComponent
{
    public function __construct(
        public MatchCategory $category,
        public float $weight,
        public int $score,
        public float $achievedPoints,
        public float $totalPoints,
        public bool $hasCandidateData,
    ) {}
}
