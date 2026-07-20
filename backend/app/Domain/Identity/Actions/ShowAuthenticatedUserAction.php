<?php

namespace App\Domain\Identity\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

class ShowAuthenticatedUserAction
{
    public function execute(): ?User
    {
        return Auth::user();
    }
}
