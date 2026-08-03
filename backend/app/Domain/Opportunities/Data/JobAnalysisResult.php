<?php

namespace App\Domain\Opportunities\Data;

final readonly class JobAnalysisResult
{
    public function __construct(
        public string $schemaVersion,
        public array $job,
        public array $warnings,
        public string $provider,
        public string $model,
        public ?string $promptVersion,
        public ?int $latencyMs,
        public ?int $tokensPrompt,
        public ?int $tokensCompletion,
        public ?string $responseId,
    ) {}
}
