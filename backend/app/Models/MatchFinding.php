<?php

namespace App\Models;

use App\Domain\Matching\Enums\MatchImportance;
use App\Domain\Matching\Enums\MatchState;
use App\Domain\Matching\Enums\RequirementSourceType;
use Database\Factories\MatchFindingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $match_analysis_id
 * @property RequirementSourceType $source_type
 * @property int $source_id
 * @property string $requirement_text
 * @property string|null $requirement_label
 * @property MatchImportance $importance
 * @property string|null $category
 * @property MatchState $match_state
 * @property string $factor
 * @property int|null $matched_candidate_skill_id
 * @property array|null $evidence_refs
 * @property string|null $justification
 * @property string|null $confidence
 * @property string|null $classifier_source
 * @property int $display_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read MatchAnalysis $matchAnalysis
 * @property-read CandidateSkill|null $matchedCandidateSkill
 *
 * @method static \Database\Factories\MatchFindingFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MatchFinding query()
 *
 * @mixin \Eloquent
 */
class MatchFinding extends Model
{
    /** @use HasFactory<MatchFindingFactory> */
    use HasFactory;

    protected $fillable = [
        'match_analysis_id',
        'source_type',
        'source_id',
        'requirement_text',
        'requirement_label',
        'importance',
        'category',
        'match_state',
        'factor',
        'matched_candidate_skill_id',
        'evidence_refs',
        'justification',
        'confidence',
        'classifier_source',
        'display_order',
        'tailoring_relevance',
    ];

    protected function casts(): array
    {
        return [
            'source_type' => RequirementSourceType::class,
            'importance' => MatchImportance::class,
            'match_state' => MatchState::class,
            'factor' => 'decimal:2',
            'matched_candidate_skill_id' => 'integer',
            'evidence_refs' => 'array',
            'display_order' => 'integer',
        ];
    }

    public function computeTailoringRelevance(): ?string
    {
        return match (true) {
            $this->match_state === MatchState::Matched && $this->importance === MatchImportance::Required => 'high',
            $this->match_state === MatchState::Matched && $this->importance === MatchImportance::Preferred => 'medium',
            $this->match_state === MatchState::Partial => 'medium',
            $this->match_state === MatchState::Gap => null,
            default => null,
        };
    }

    /** @return BelongsTo<MatchAnalysis, $this> */
    public function matchAnalysis(): BelongsTo
    {
        return $this->belongsTo(MatchAnalysis::class);
    }

    /** @return BelongsTo<CandidateSkill, $this> */
    public function matchedCandidateSkill(): BelongsTo
    {
        return $this->belongsTo(CandidateSkill::class, 'matched_candidate_skill_id');
    }
}
