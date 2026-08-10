<?php

namespace App\Models;

use App\Domain\Resumes\Enums\ResumeStatus;
use Database\Factories\ResumeFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $candidate_profile_id
 * @property int|null $opportunity_id
 * @property int|null $file_id
 * @property string $title
 * @property string|null $template_key
 * @property array $content
 * @property ResumeStatus $status
 * @property string $generated_by
 * @property Carbon|null $approved_at
 * @property int $version_no
 * @property array|null $profile_snapshot
 * @property array|null $opportunity_snapshot
 * @property array|null $match_snapshot
 * @property array|null $ai_metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CandidateProfile $candidateProfile
 * @property-read JobOpportunity|null $opportunity
 * @property-read Collection<int, TailoringProposal> $proposals
 *
 * @method static \Database\Factories\ResumeFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resume query()
 *
 * @mixin \Eloquent
 */
class Resume extends Model
{
    /** @use HasFactory<ResumeFactory> */
    use HasFactory;

    protected $fillable = [
        'candidate_profile_id',
        'opportunity_id',
        'file_id',
        'title',
        'template_key',
        'content',
        'status',
        'generated_by',
        'approved_at',
        'version_no',
        'profile_snapshot',
        'opportunity_snapshot',
        'match_snapshot',
        'ai_metadata',
    ];

    public function getOpportunityIdAttribute(): ?int
    {
        return $this->attributes['job_opportunity_id'] ?? null;
    }

    public function setOpportunityIdAttribute(?int $value): void
    {
        $this->attributes['job_opportunity_id'] = $value;
    }

    protected function casts(): array
    {
        return [
            'content' => 'array',
            'profile_snapshot' => 'array',
            'opportunity_snapshot' => 'array',
            'match_snapshot' => 'array',
            'ai_metadata' => 'array',
            'approved_at' => 'datetime',
            'status' => ResumeStatus::class,
        ];
    }

    /** @return BelongsTo<CandidateProfile, $this> */
    public function candidateProfile(): BelongsTo
    {
        return $this->belongsTo(CandidateProfile::class);
    }

    /** @return BelongsTo<JobOpportunity, $this> */
    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(JobOpportunity::class);
    }

    /** @return HasMany<TailoringProposal, $this> */
    public function proposals(): HasMany
    {
        return $this->hasMany(TailoringProposal::class);
    }
}
