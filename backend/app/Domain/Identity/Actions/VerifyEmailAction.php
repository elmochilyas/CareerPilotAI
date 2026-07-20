<?php

namespace App\Domain\Identity\Actions;

use App\Models\User;
use Illuminate\Auth\AuthenticationException;

class VerifyEmailAction
{
    public function execute(User $user, string $hash): void
    {
        if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            throw new AuthenticationException('Invalid verification hash.');
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }
    }
}
