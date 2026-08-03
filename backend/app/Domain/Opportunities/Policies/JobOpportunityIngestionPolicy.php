<?php

namespace App\Domain\Opportunities\Policies;

use App\Models\JobOpportunityIngestion;
use App\Models\User;

class JobOpportunityIngestionPolicy
{
    public function view(User $user, JobOpportunityIngestion $ingestion): bool
    {
        return $ingestion->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->exists;
    }

    public function update(User $user, JobOpportunityIngestion $ingestion): bool
    {
        return $ingestion->user_id === $user->id;
    }

    public function delete(User $user, JobOpportunityIngestion $ingestion): bool
    {
        return $ingestion->user_id === $user->id;
    }

    public function retry(User $user, JobOpportunityIngestion $ingestion): bool
    {
        return $ingestion->user_id === $user->id;
    }

    public function confirm(User $user, JobOpportunityIngestion $ingestion): bool
    {
        return $ingestion->user_id === $user->id;
    }
}
