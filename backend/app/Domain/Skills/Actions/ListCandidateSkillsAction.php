<?php

namespace App\Domain\Skills\Actions;

use App\Domain\Skills\Enums\SkillState;
use App\Models\CandidateProfile;
use App\Models\CandidateSkill;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListCandidateSkillsAction
{
    public function execute(CandidateProfile $profile, ?SkillState $state = null, int $perPage = 20): LengthAwarePaginator
    {
        $query = CandidateSkill::where('candidate_profile_id', $profile->id)
            ->with('skill.aliases');

        if ($state !== null) {
            $query->where('state', $state->value);
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }
}
