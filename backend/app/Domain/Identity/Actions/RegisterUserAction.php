<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Enums\UserAccountStatus;
use App\Domain\Identity\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RegisterUserAction
{
    public function execute(string $fullName, string $email, string $password): User
    {
        return DB::transaction(function () use ($fullName, $email, $password): User {
            $user = User::create([
                'full_name' => $fullName,
                'email' => $email,
                'password' => Hash::make($password),
                'role' => UserRole::Candidate,
                'account_status' => UserAccountStatus::Active,
            ]);

            event(new Registered($user));

            return $user;
        });
    }
}
