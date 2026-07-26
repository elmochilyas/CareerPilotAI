<?php

namespace App\Models;

use App\Domain\Profile\Enums\ProfileItemType;
use Database\Factories\ProfileItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $candidate_profile_id
 * @property ProfileItemType $type
 * @property string $title
 * @property string|null $organization
 * @property string|null $location
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 * @property string|null $description
 * @property array<string, mixed>|null $metadata
 * @property int $display_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CandidateProfile $candidateProfile
 *
 * @method static \Database\Factories\ProfileItemFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProfileItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProfileItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProfileItem query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProfileItem whereCandidateProfileId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProfileItem whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProfileItem whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProfileItem whereDisplayOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProfileItem whereEndDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProfileItem whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProfileItem whereLocation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProfileItem whereMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProfileItem whereOrganization($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProfileItem whereStartDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProfileItem whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProfileItem whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProfileItem whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
#[Fillable([
    'candidate_profile_id', 'type', 'title', 'organization', 'location',
    'start_date', 'end_date', 'description', 'metadata', 'display_order',
])]
class ProfileItem extends Model
{
    /** @use HasFactory<ProfileItemFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => ProfileItemType::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'metadata' => 'array',
            'display_order' => 'integer',
        ];
    }

    /** @return BelongsTo<CandidateProfile, $this> */
    public function candidateProfile(): BelongsTo
    {
        return $this->belongsTo(CandidateProfile::class);
    }
}
