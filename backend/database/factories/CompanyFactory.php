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
        ];
    }
}
