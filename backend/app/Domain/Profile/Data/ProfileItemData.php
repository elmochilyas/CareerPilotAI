<?php

namespace App\Domain\Profile\Data;

final readonly class ProfileItemData
{
    /** @param array<string, mixed> $attributes */
    public function __construct(public array $attributes, public ?string $updatedAt) {}

    /** @param array<string, mixed> $data */
    public static function from(array $data): self
    {
        $updatedAt = $data['updated_at'] ?? null;
        unset($data['updated_at']);

        return new self($data, $updatedAt);
    }
}
