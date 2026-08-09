<?php

namespace App\Models;

use App\Domain\Skills\Enums\ProficiencyLevel;
use App\Domain\Skills\Enums\SkillState;
use Database\Factories\CandidateSkillFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $candidate_profile_id
 * @property int|null $skill_id
 * @property string|null $custom_skill_name
 * @property SkillState $state
 * @property ProficiencyLevel|null $proficiency_level
 * @property float|null $years_experience
 * @property Carbon|null $last_used_at
 * @property array|null $evidence
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CandidateProfile $candidateProfile
 * @property-read Skill|null $skill
 *
 * @method static \Database\Factories\CandidateSkillFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateSkill newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateSkill newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateSkill query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateSkill whereCandidateProfileId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateSkill whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateSkill whereCustomSkillName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateSkill whereEvidence($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateSkill whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateSkill whereLastUsedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateSkill whereProficiencyLevel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateSkill whereSkillId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateSkill whereState($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateSkill whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CandidateSkill whereYearsExperience($value)
 *
 * @mixin \Eloquent
 */
class CandidateSkill extends Model
{
    /** @use HasFactory<CandidateSkillFactory> */
    use HasFactory;

    protected $fillable = [
        'candidate_profile_id',
        'skill_id',
        'custom_skill_name',
        'state',
        'proficiency_level',
        'years_experience',
        'last_used_at',
        'evidence',
    ];

    protected function casts(): array
    {
        return [
            'state' => SkillState::class,
            'proficiency_level' => ProficiencyLevel::class,
            'years_experience' => 'decimal:1',
            'last_used_at' => 'date',
            'evidence' => 'array',
        ];
    }

    /** @return BelongsTo<CandidateProfile, $this> */
    public function candidateProfile(): BelongsTo
    {
        return $this->belongsTo(CandidateProfile::class);
    }

    /** @return BelongsTo<Skill, $this> */
    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }
}
