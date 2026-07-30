<?php

namespace App\Models;

use App\Domain\Opportunities\Enums\ReviewDecision;
use App\Domain\Opportunities\Enums\SkillResolutionState;
use App\Domain\Opportunities\Enums\SuggestionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobOpportunitySuggestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'ingestion_id',
        'type',
        'group_key',
        'field',
        'extracted_value',
        'edited_value',
        'review_decision',
        'source_evidence',
        'schema_version',
        'reviewed_at',
        'resolution',
        'resolved_skill_id',
        'version',
    ];

    protected $casts = [
        'type' => SuggestionType::class,
        'review_decision' => ReviewDecision::class,
        'resolution' => SkillResolutionState::class,
        'extracted_value' => 'array',
        'edited_value' => 'array',
        'version' => 'integer',
        'reviewed_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<JobOpportunityIngestion, $this>
     */
    public function ingestion(): BelongsTo
    {
        return $this->belongsTo(JobOpportunityIngestion::class, 'ingestion_id');
    }

    /**
     * @return BelongsTo<Skill, $this>
     */
    public function resolvedSkill(): BelongsTo
    {
        return $this->belongsTo(Skill::class, 'resolved_skill_id');
    }
}
