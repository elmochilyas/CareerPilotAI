<?php

namespace App\Domain\Resumes\Data;

final readonly class TailoringResult
{
    /**
     * @param  array<int, TailoringProposalData>  $proposals
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public array $proposals,
        public array $metadata = [],
    ) {}
}
