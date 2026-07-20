<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Identity\Enums\UserAccountStatus;
use App\Domain\Identity\Enums\UserRole;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @mixin User
 *
 * @property-read UserRole $role
 * @property-read UserAccountStatus $account_status
 * @property-read Carbon|null $email_verified_at
 */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'role' => $this->role->value,
            'account_status' => $this->account_status->value,
            'timezone' => $this->timezone,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
