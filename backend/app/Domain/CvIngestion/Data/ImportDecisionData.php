<?php

namespace App\Domain\CvIngestion\Data;

final readonly class ImportDecisionData
{
    /** @param array<string, mixed>|null $editedValue */
    public function __construct(
        public int $suggestionId,
        public string $decision,
        public ?array $editedValue = null,
        public ?string $action = null,
        public ?int $targetId = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            suggestionId: $data['id'],
            decision: $data['decision'],
            editedValue: $data['edited_value'] ?? null,
            action: $data['action'] ?? null,
            targetId: $data['target_id'] ?? null,
        );
    }
}
