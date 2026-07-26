<?php

namespace App\Models;

use Database\Factories\SkillFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $normalized_name
 * @property string|null $category
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, SkillAlias> $aliases
 * @property-read int|null $aliases_count
 *
 * @method static \Database\Factories\SkillFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Skill newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Skill newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Skill query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Skill whereCategory($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Skill whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Skill whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Skill whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Skill whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Skill whereNormalizedName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Skill whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Skill extends Model
{
    /** @use HasFactory<SkillFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'normalized_name',
        'category',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<SkillAlias, $this> */
    public function aliases(): HasMany
    {
        return $this->hasMany(SkillAlias::class);
    }
}
