<?php

namespace App\Domain\Skills\Data;

readonly class EvidenceData
{
    public function __construct(
        public string $type,
        public string $value,
        public ?string $label,
    ) {}
}
