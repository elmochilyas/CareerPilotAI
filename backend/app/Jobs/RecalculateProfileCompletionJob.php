<?php

namespace App\Jobs;

use App\Domain\Profile\Services\ProfileCompletionService;
use App\Models\CandidateProfile;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class RecalculateProfileCompletionJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 60;

    public function __construct(
        public int $profileId,
    ) {
        $this->onQueue('default');
    }

    public function handle(ProfileCompletionService $completionService): void
    {
        $profile = CandidateProfile::find($this->profileId);

        if (! $profile) {
            return;
        }

        $profile->load('items');
        $completionService->persist($profile);
    }

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [new WithoutOverlapping((string) $this->profileId)];
    }
}
