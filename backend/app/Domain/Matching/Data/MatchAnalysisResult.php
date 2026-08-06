<?php

namespace App\Domain\Matching\Data;

final readonly class MatchAnalysisResult
{
    /**
     * @param  list<MatchScoreComponent>  $scoreComponents
     * @param  list<MatchFindingResult>  $findings
     * @param  list<string>  $warnings
     */
    public function __construct(
        public int $overallScore,
        public int $evidenceCoverageScore,
        public int $requiredCount,
        public int $preferredCount,
        public int $matchedCount,
        public int $partialCount,
        public int $gapCount,
        public int $unknownCount,
        public array $scoreComponents,
        public array $findings,
        public array $warnings,
    ) {}
}
