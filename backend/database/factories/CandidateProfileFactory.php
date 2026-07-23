<?php

namespace Database\Factories;

use App\Domain\Profile\Enums\AvailabilityStatus;
use App\Domain\Profile\Enums\WorkMode;
use App\Models\CandidateProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CandidateProfile> */
class CandidateProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'headline' => fake()->jobTitle(),
            'professional_summary' => fake()->paragraph(),
            'phone' => '+212 600 000 000',
            'city' => 'Casablanca',
            'country' => 'Morocco',
            'linkedin_url' => 'https://linkedin.com/in/candidate',
            'github_url' => null,
            'portfolio_url' => null,
            'availability_status' => AvailabilityStatus::Immediately,
            'target_roles' => ['Backend Developer'],
            'preferred_locations' => ['Casablanca'],
            'work_modes' => [WorkMode::Hybrid->value],
            'contract_types' => ['full-time'],
            'salary_min' => null,
            'salary_max' => null,
            'languages' => [['language' => 'English', 'proficiency' => 'fluent']],
            'profile_completion' => 80,
        ];
    }

    public function empty(): static
    {
        return $this->state(fn (): array => array_fill_keys([
            'headline', 'professional_summary', 'phone', 'city', 'country', 'linkedin_url',
            'github_url', 'portfolio_url', 'availability_status', 'target_roles',
            'preferred_locations', 'work_modes', 'contract_types', 'salary_min', 'salary_max',
            'languages',
        ], null) + ['profile_completion' => 0]);
    }

    public function minimal(): static
    {
        return $this->empty()->state(fn (): array => ['headline' => 'Junior Developer', 'profile_completion' => 10]);
    }

    public function complete(): static
    {
        return $this->state(fn (): array => ['profile_completion' => 100]);
    }
}
