<?php

namespace App\Domain\Matching\Data;

final readonly class ClassifierResult
{
    /**
     * @param  list<ClassifierFinding>  $findings
     * @param  list<string>  $warnings
     */
    public function __construct(
        public string $schemaVersion,
        public array $findings,
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
