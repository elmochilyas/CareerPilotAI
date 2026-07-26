<?php

namespace App\Domain\CvIngestion\Data;

final readonly class BatchDecisionData
{
    /** @param array<int, ImportDecisionData> $decisions */
    public function __construct(
        public array $decisions,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            decisions: array_map(
                fn (array $d): ImportDecisionData => ImportDecisionData::fromArray($d),
                $data['decisions'] ?? [],
            ),
        );
    }
}
