<?php

namespace App\Domain\Identity\Actions;

use App\Models\User;

class ResendVerificationNotificationAction
{
    public function execute(User $user): void
    {
        $user->sendEmailVerificationNotification();
    }
}
