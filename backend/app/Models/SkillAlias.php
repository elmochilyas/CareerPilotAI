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
