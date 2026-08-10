<?php

namespace App\Domain\Resumes\Policies;

use App\Models\Resume;
use App\Models\User;

final class ResumePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Resume $resume): bool
    {
        return $user->id === $resume->candidateProfile->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Resume $resume): bool
    {
        return $user->id === $resume->candidateProfile->user_id
            && $resume->status->value === 'draft';
    }

    public function delete(User $user, Resume $resume): bool
    {
        return $user->id === $resume->candidateProfile->user_id
            && $resume->status->value === 'draft';
    }
}
