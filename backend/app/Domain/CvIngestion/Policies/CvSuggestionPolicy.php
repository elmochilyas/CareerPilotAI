<?php

namespace App\Domain\CvIngestion\Policies;

use App\Models\CvSuggestion;
use App\Models\User;

class CvSuggestionPolicy
{
    public function view(User $user, CvSuggestion $suggestion): bool
    {
        return $suggestion->cvDocument->user_id === $user->id;
    }

    public function update(User $user, CvSuggestion $suggestion): bool
    {
        return $this->view($user, $suggestion);
    }
}
