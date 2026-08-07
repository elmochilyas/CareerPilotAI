<?php

namespace App\Domain\Clarification\Data;

final class ClarificationSessionData
{
    /**
     * @param  list<ClarificationSessionQuestionData>  $questions
     */
    public function __construct(
        public readonly int $analysisId,
        public readonly array $questions,
        public readonly int $total,
        public readonly int $answered,
        public readonly int $generableCount,
    ) {}
}
