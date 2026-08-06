<?php

namespace Database\Factories;

use App\Domain\Matching\Enums\MatchAnalysisStatus;
use App\Models\CandidateProfile;
use App\Models\JobOpportunity;
use App\Models\MatchAnalysis;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MatchAnalysis> */
class MatchAnalysisFactory extends Factory
{
    public function definition(): array
    {
        return [
            'candidate_profile_id' => CandidateProfile::factory(),
            'job_opportunity_id' => JobOpportunity::factory(),
            'status' => MatchAnalysisStatus::Queued,
            'operation_key' => hash('sha256', fake()->uuid()),
            'profile_fingerprint' => hash('sha256', fake()->uuid()),
            'opportunity_fingerprint' => hash('sha256', fake()->uuid()),
            'algorithm_version' => config('matching.algorithm_version'),
            'scoring_version' => config('matching.scoring_version'),
            'classifier_schema_version' => config('matching.classifier_schema_version'),
            'queued_at' => now(),
        ];
    }

    public function queued(): static
    {
        return $this->state(fn (): array => ['status' => MatchAnalysisStatus::Queued, 'queued_at' => now()]);
    }

    public function processing(): static
    {
        return $this->state(fn (): array => [
            'status' => MatchAnalysisStatus::Processing,
            'processing_started_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => MatchAnalysisStatus::Completed,
            'overall_score' => 78,
            'evidence_coverage_score' => 85,
            'required_count' => 5,
            'preferred_count' => 3,
            'matched_count' => 4,
            'partial_count' => 1,
            'gap_count' => 2,
            'unknown_count' => 1,
            'completed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => MatchAnalysisStatus::Failed,
            'failure_code' => 'analysis_failed',
            'failure_reason' => fake()->sentence(),
            'failed_at' => now(),
        ]);
    }
}
