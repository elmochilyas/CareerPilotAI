<?php

namespace Database\Factories;

use App\Domain\Opportunities\Enums\JobIngestionStatus;
use App\Models\JobOpportunityIngestion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<JobOpportunityIngestion> */
class JobOpportunityIngestionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'source_description' => fake()->paragraphs(3, true),
            'source_url' => fake()->optional()->url(),
            'content_hash' => hash('sha256', fake()->uuid()),
            'personal_label' => fake()->optional()->words(3, true),
            'version' => 1,
            'status' => JobIngestionStatus::Draft,
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => JobIngestionStatus::Draft]);
    }

    public function queued(): static
    {
        return $this->state(['status' => JobIngestionStatus::Queued]);
    }

    public function processing(): static
    {
        return $this->state(['status' => JobIngestionStatus::Processing]);
    }

    public function reviewReady(): static
    {
        return $this->state(['status' => JobIngestionStatus::ReviewReady]);
    }

    public function confirmed(): static
    {
        return $this->state([
            'status' => JobIngestionStatus::Confirmed,
            'confirmed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state([
            'status' => JobIngestionStatus::Failed,
            'failure_reason' => fake()->sentence(),
            'failure_code' => 'analysis_failed',
            'retry_count' => 1,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => JobIngestionStatus::Cancelled]);
    }
}
