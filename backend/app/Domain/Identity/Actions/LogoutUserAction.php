<?php

namespace App\Domain\Identity\Actions;

use Illuminate\Support\Facades\Auth;

class LogoutUserAction
{
    public function execute(): void
    {
        Auth::guard('web')->logout();

        session()->invalidate();

        session()->regenerateToken();
    }
}
