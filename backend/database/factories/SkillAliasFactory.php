<?php

namespace Database\Factories;

use App\Models\Skill;
use App\Models\SkillAlias;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SkillAlias> */
class SkillAliasFactory extends Factory
{
    public function definition(): array
    {
        return [
            'skill_id' => Skill::factory(),
            'alias' => fake()->unique()->word(),
        ];
    }
}
