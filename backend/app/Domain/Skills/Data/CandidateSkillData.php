<?php

namespace App\Domain\Skills\Data;

use App\Domain\Skills\Enums\ProficiencyLevel;
use App\Domain\Skills\Enums\SkillState;
use Carbon\CarbonImmutable;

readonly class CandidateSkillData
{
    public function __construct(
        public ?int $skillId,
        public ?string $customSkillName,
        public SkillState $state,
        public ?ProficiencyLevel $proficiencyLevel,
        public ?float $yearsExperience,
        public ?CarbonImmutable $lastUsedAt,
        public ?array $evidence,
    ) {}
}
