<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Enums\UserAccountStatus;
use App\Models\User;
use App\Support\ProblemDetails\ProblemDetailsException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginUserAction
{
    public function execute(string $email, string $password): User
    {
        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        if ($user->account_status === UserAccountStatus::Suspended) {
            throw new AuthorizationException(__('Account is suspended.'));
        }

        if ($user->account_status === UserAccountStatus::Disabled) {
            throw new ProblemDetailsException(
                403,
                __('Your account has been disabled and cannot sign in.'),
                'account_disabled',
            );
        }

        Auth::login($user);

        session()->regenerate();

        return $user;
    }
}
