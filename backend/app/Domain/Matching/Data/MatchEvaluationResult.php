<?php

namespace App\Domain\Matching\Data;

final readonly class MatchEvaluationResult
{
    /**
     * @param  list<MatchFindingResult>  $findings
     * @param  array<string, bool>  $categoryHasCandidateData
     */
    public function __construct(
        public array $findings,
        public array $categoryHasCandidateData,
    ) {}
}
