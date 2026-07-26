<?php

namespace App\Models;

use Database\Factories\SkillAliasFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $skill_id
 * @property string $alias
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Skill $skill
 *
 * @method static \Database\Factories\SkillAliasFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SkillAlias newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SkillAlias newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SkillAlias query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SkillAlias whereAlias($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SkillAlias whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SkillAlias whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SkillAlias whereSkillId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SkillAlias whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class SkillAlias extends Model
{
    /** @use HasFactory<SkillAliasFactory> */
    use HasFactory;

    protected $fillable = [
        'skill_id',
        'alias',
    ];

    /** @return BelongsTo<Skill, $this> */
    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }
}
