<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Company> */
class CompanyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'website' => fake()->optional()->url(),
            'industry' => fake()->optional()->word(),
            'location' => fake()->optional()->city(),
            'size_band' => fake()->optional()->randomElement(['1-10', '11-50', '51-200', '201-1000', '1000+']),
            'research_status' => 'not_researched',
            'research_version' => 1,
        ];
    }

    public function researched(): static
    {
        return $this->state(fn (array $attributes): array => [
            'research' => [
                'version' => 1,
                'status' => 'completed',
                'overview' => [
                    'name' => $attributes['name'] ?? fake()->company(),
                    'website' => $attributes['website'] ?? null,
                    'industry' => $attributes['industry'] ?? null,
                    'headquarters' => null,
                    'description' => 'A technology company.',
                ],
                'products' => [],
                'technology_context' => [],
                'role_context' => [],
                'recent_information' => [],
                'candidate_preparation' => [],
                'sources' => [],
                'generated_at' => now()->toISOString(),
                'fallback_reason' => null,
                'ai_meta' => null,
            ],
            'researched_at' => now(),
            'research_status' => 'completed',
        ]);
    }

    public function processing(): static
    {
        return $this->state(fn (): array => [
            'research_status' => 'processing',
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'research_status' => 'failed',
            'research_failure_code' => 'provider_unavailable',
        ]);
    }

    public function limited(): static
    {
        return $this->state(fn (array $attributes): array => [
            'research' => [
                'version' => 1,
                'status' => 'limited',
                'overview' => [
                    'name' => $attributes['name'] ?? fake()->company(),
                    'website' => $attributes['website'] ?? null,
                    'industry' => null,
                    'headquarters' => null,
                    'description' => 'Limited research from opportunity.',
                ],
                'products' => [],
                'technology_context' => [],
                'role_context' => [],
                'recent_information' => [],
                'candidate_preparation' => [],
                'sources' => [],
                'generated_at' => now()->toISOString(),
                'fallback_reason' => 'fetch_unavailable',
                'ai_meta' => null,
            ],
            'researched_at' => now(),
            'research_status' => 'limited',
        ]);
    }
}
