<?php

namespace App\Models;

use App\Domain\Profile\Enums\AvailabilityStatus;
use Database\Factories\CandidateProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string|null $headline
 * @property string|null $professional_summary
 * @property string|null $phone
 * @property string|null $city
 * @property string|null $country
 * @property string|null $linkedin_url
 * @property string|null $github_url
 * @property string|null $portfolio_url
 * @property AvailabilityStatus|null $availability_status
 * @property Carbon|null $availability_date
 * @property array<int, string>|null $target_roles
 * @property array<int, string>|null $preferred_locations
 * @property array<int, string>|null $work_modes
 * @property array<int, string>|null $contract_types
 * @property string|null $salary_min
 * @property string|null $salary_max
 * @property array<int, array{language: string, proficiency: string}>|null $languages
 * @property string $profile_completion
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Collection<int, ProfileItem> $items
 */
#[Fillable([
    'user_id', 'headline', 'professional_summary', 'phone', 'city', 'country',
    'linkedin_url', 'github_url', 'portfolio_url', 'availability_status',
    'availability_date', 'target_roles', 'preferred_locations',
    'work_modes', 'contract_types', 'salary_min', 'salary_max', 'languages',
    'profile_completion',
])]
class CandidateProfile extends Model
{
    /** @use HasFactory<CandidateProfileFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'availability_status' => AvailabilityStatus::class,
            'availability_date' => 'date',
            'target_roles' => 'array',
            'preferred_locations' => 'array',
            'work_modes' => 'array',
            'contract_types' => 'array',
            'salary_min' => 'decimal:2',
            'salary_max' => 'decimal:2',
            'languages' => 'array',
            'profile_completion' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<ProfileItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(ProfileItem::class)->orderBy('display_order');
    }
}
