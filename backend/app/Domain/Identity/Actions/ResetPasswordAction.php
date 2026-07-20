<?php

namespace App\Domain\Identity\Actions;

use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class ResetPasswordAction
{
    public function execute(string $email, string $token, string $password): string
    {
        return Password::broker()->reset(
            [
                'email' => $email,
                'token' => $token,
                'password' => $password,
                'password_confirmation' => $password,
            ],
            function ($user, $password): void {
                $user->forceFill([
                    'password' => $password,
                ])->setRememberToken(Str::random(60));

                $user->save();
            }
        );
    }
}
