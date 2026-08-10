<?php

namespace Database\Factories;

use App\Models\CandidateProfile;
use App\Models\JobOpportunity;
use App\Models\Resume;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Resume> */
class ResumeFactory extends Factory
{
    protected $model = Resume::class;

    public function definition(): array
    {
        return [
            'candidate_profile_id' => CandidateProfile::factory(),
            'job_opportunity_id' => null,
            'file_id' => null,
            'title' => fake()->sentence(3),
            'template_key' => null,
            'content' => ['sections' => []],
            'status' => 'draft',
            'generated_by' => 'manual',
            'approved_at' => null,
            'version_no' => 1,
            'profile_snapshot' => null,
            'opportunity_snapshot' => null,
            'match_snapshot' => null,
            'ai_metadata' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (): array => ['status' => 'draft']);
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => 'approved',
            'approved_at' => now(),
        ]);
    }

    public function forOpportunity(JobOpportunity $opportunity): static
    {
        return $this->state(fn (): array => [
            'job_opportunity_id' => $opportunity->id,
        ]);
    }
}
