<?php

namespace App\Domain\Profile\Policies;

use App\Models\ProfileItem;
use App\Models\User;

class ProfileItemPolicy
{
    public function view(User $user, ProfileItem $item): bool
    {
        return $item->candidateProfile->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->exists;
    }

    public function update(User $user, ProfileItem $item): bool
    {
        return $this->view($user, $item);
    }

    public function delete(User $user, ProfileItem $item): bool
    {
        return $this->view($user, $item);
    }
}
