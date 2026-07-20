<?php

namespace App\Domain\Identity\Actions;

use Illuminate\Support\Facades\Password;

class SendPasswordResetLinkAction
{
    public function execute(string $email): string
    {
        return Password::broker()->sendResetLink(['email' => $email]);
    }
}
