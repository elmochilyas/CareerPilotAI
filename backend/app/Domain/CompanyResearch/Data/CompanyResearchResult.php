<?php

namespace App\Domain\CompanyResearch\Data;

final class CompanyResearchResult
{
    /**
     * @param  array<string, mixed>  $brief
     * @param  string[]  $warnings
     */
    public function __construct(
        public readonly string $schemaVersion,
        public readonly array $brief,
        public readonly array $warnings,
        public readonly string $provider,
        public readonly string $model,
        public readonly string $promptVersion,
        public readonly int $latencyMs,
        public readonly ?int $tokensPrompt,
        public readonly ?int $tokensCompletion,
        public readonly ?string $responseId,
    ) {}
}
