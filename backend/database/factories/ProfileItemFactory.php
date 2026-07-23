<?php

namespace Database\Factories;

use App\Domain\Profile\Enums\ProfileItemType;
use App\Models\CandidateProfile;
use App\Models\ProfileItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProfileItem> */
class ProfileItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'candidate_profile_id' => CandidateProfile::factory(),
            'type' => ProfileItemType::Experience,
            'title' => fake()->jobTitle(),
            'organization' => fake()->company(),
            'location' => fake()->city(),
            'start_date' => '2024-01-01',
            'end_date' => null,
            'description' => fake()->paragraph(),
            'metadata' => ['employment_type' => 'full-time'],
            'display_order' => 0,
        ];
    }

    public function education(): static
    {
        return $this->state(fn (): array => ['type' => ProfileItemType::Education, 'title' => 'Bachelor of Science', 'metadata' => ['degree' => 'Bachelor', 'field' => 'Computer Science']]);
    }

    public function experience(): static
    {
        return $this->state(fn (): array => ['type' => ProfileItemType::Experience, 'metadata' => ['employment_type' => 'full-time']]);
    }

    public function project(): static
    {
        return $this->state(fn (): array => ['type' => ProfileItemType::Project, 'organization' => null, 'metadata' => ['project_url' => 'https://example.com/project']]);
    }

    public function certification(): static
    {
        return $this->state(fn (): array => ['type' => ProfileItemType::Certification, 'metadata' => ['issuer' => 'Example Issuer']]);
    }
}
