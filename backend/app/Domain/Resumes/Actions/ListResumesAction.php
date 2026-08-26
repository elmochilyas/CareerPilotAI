<?php

namespace App\Domain\Resumes\Actions;

use App\Models\CandidateProfile;
use App\Models\Resume;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final readonly class ListResumesAction
{
    public function execute(
        CandidateProfile $profile,
        ?string $status = null,
        ?int $opportunityId = null,
    ): LengthAwarePaginator {
        $query = Resume::query()
            ->where('candidate_profile_id', $profile->id)
            ->with(['opportunity', 'candidateProfile']);

        if ($status !== null) {
            $query->where('status', $status);
        }

        if ($opportunityId !== null) {
            $query->where('job_opportunity_id', $opportunityId);
        }

        return $query
            ->orderByDesc('version_no')
            ->orderByDesc('updated_at')
            ->paginate(15);
    }
}
