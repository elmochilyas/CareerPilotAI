<?php

namespace App\Models;

use App\Domain\Identity\Enums\UserAccountStatus;
use App\Domain\Identity\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['full_name', 'email', 'password', 'role', 'account_status', 'timezone'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * @return array{
     *     email_verified_at: 'datetime',
     *     password: 'hashed',
     *     role: 'App\\Domain\\Identity\\Enums\\UserRole',
     *     account_status: 'App\\Domain\\Identity\\Enums\\UserAccountStatus',
     * }
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'account_status' => UserAccountStatus::class,
        ];
    }

    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new ResetPassword($token));
    }
}
