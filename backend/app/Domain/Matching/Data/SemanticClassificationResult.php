<?php

namespace App\Domain\Matching\Data;

final readonly class SemanticClassificationResult
{
    /**
     * @param  list<MatchFindingResult>  $findings
     * @param  list<string>  $warnings
     */
    public function __construct(
        public array $findings,
        public ?string $provider,
        public ?string $model,
        public ?string $promptVersion,
        public ?int $latencyMs,
        public ?int $tokensPrompt,
        public ?int $tokensCompletion,
        public ?string $responseId,
        public ?string $status,
        public array $warnings,
    ) {}
}
