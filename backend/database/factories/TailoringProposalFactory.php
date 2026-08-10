<?php

namespace Database\Factories;

use App\Models\Resume;
use App\Models\TailoringProposal;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TailoringProposal> */
class TailoringProposalFactory extends Factory
{
    protected $model = TailoringProposal::class;

    public function definition(): array
    {
        return [
            'resume_id' => Resume::factory(),
            'source_type' => 'profile_item',
            'source_id' => null,
            'original_text' => fake()->sentence(),
            'proposed_text' => fake()->sentence(),
            'change_type' => 'reword',
            'status' => 'proposed',
            'edited_text' => null,
            'accepted_at' => null,
            'ai_metadata' => null,
        ];
    }

    public function proposed(): static
    {
        return $this->state(fn (): array => ['status' => 'proposed']);
    }

    public function accepted(): static
    {
        return $this->state(fn (): array => [
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (): array => ['status' => 'rejected']);
    }
}
