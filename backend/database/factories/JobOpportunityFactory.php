<?php

namespace Database\Factories;

use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\JobOpportunity;
use App\Models\JobOpportunityIngestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<JobOpportunity> */
class JobOpportunityFactory extends Factory
{
    public function definition(): array
    {
        return [
            'candidate_profile_id' => CandidateProfile::factory(),
            'ingestion_id' => JobOpportunityIngestion::factory(),
            'company_id' => Company::factory(),
            'title' => fake()->jobTitle(),
            'company_name' => fake()->company(),
            'summary' => fake()->paragraph(),
            'source_hash' => hash('sha256', fake()->uuid()),
            'saved_at' => now(),
        ];
    }
}
