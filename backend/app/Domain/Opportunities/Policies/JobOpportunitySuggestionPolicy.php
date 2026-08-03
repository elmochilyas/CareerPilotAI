<?php

namespace App\Domain\Opportunities\Policies;

use App\Models\JobOpportunitySuggestion;
use App\Models\User;

class JobOpportunitySuggestionPolicy
{
    public function view(User $user, JobOpportunitySuggestion $suggestion): bool
    {
        return $suggestion->ingestion->user_id === $user->id;
    }

    public function update(User $user, JobOpportunitySuggestion $suggestion): bool
    {
        return $suggestion->ingestion->user_id === $user->id;
    }
}
