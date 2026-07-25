<?php

namespace Database\Factories;

use App\Domain\Skills\Enums\ProficiencyLevel;
use App\Domain\Skills\Enums\SkillState;
use App\Models\CandidateProfile;
use App\Models\CandidateSkill;
use App\Models\Skill;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CandidateSkill> */
class CandidateSkillFactory extends Factory
{
    public function definition(): array
    {
        return [
            'candidate_profile_id' => CandidateProfile::factory(),
            'skill_id' => Skill::factory(),
            'state' => SkillState::Claimed,
            'proficiency_level' => fake()->randomElement(ProficiencyLevel::cases()),
            'years_experience' => fake()->randomFloat(1, 0, 15),
            'last_used_at' => fake()->optional()->date(),
            'evidence' => null,
        ];
    }

    public function claimed(): static
    {
        return $this->state(fn (): array => ['state' => SkillState::Claimed, 'evidence' => null]);
    }

    public function verified(): static
    {
        return $this->state(fn (): array => [
            'state' => SkillState::Verified,
            'evidence' => [
                ['key' => fake()->uuid(), 'type' => 'url', 'value' => 'https://example.com/cert', 'label' => 'Certification'],
            ],
        ]);
    }

    public function learning(): static
    {
        return $this->state(fn (): array => ['state' => SkillState::Learning, 'evidence' => null]);
    }

    public function archived(): static
    {
        return $this->state(fn (): array => ['state' => SkillState::Archived, 'evidence' => null]);
    }

    public function custom(): static
    {
        return $this->state(fn (): array => ['skill_id' => null]);
    }
}
