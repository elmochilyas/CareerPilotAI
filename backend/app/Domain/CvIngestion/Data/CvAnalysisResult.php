<?php

namespace App\Domain\CvIngestion\Data;

final readonly class CvAnalysisResult
{
    /** @param array<string, mixed> $basicInformation */
    public function __construct(
        public array $basicInformation,
        public ?string $headline,
        public ?string $professionalSummary,
        /** @var array<int, array<string, mixed>> */
        public array $professionalLinks,
        /** @var array<int, array<string, mixed>> */
        public array $experiences,
        /** @var array<int, array<string, mixed>> */
        public array $projects,
        /** @var array<int, array<string, mixed>> */
        public array $education,
        /** @var array<int, array<string, mixed>> */
        public array $certifications,
        /** @var array<int, array<string, mixed>> */
        public array $languages,
        /** @var array<int, array<string, mixed>> */
        public array $skills,
        /** @var list<string> */
        public array $warnings,
        public string $provider,
        public string $model,
        public ?string $promptVersion,
        public ?int $latencyMs,
        public ?int $tokensPrompt,
        public ?int $tokensCompletion,
        public ?string $responseId,
        public ?string $schemaVersion,
    ) {}
}
