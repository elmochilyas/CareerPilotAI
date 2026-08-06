<?php

namespace App\Models;

use App\Domain\Matching\Enums\MatchAnalysisStatus;
use Database\Factories\MatchAnalysisFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $candidate_profile_id
 * @property int $job_opportunity_id
 * @property MatchAnalysisStatus $status
 * @property string $operation_key
 * @property int|null $overall_score
 * @property int|null $evidence_coverage_score
 * @property int|null $required_count
 * @property int|null $preferred_count
 * @property int|null $matched_count
 * @property int|null $partial_count
 * @property int|null $gap_count
 * @property int|null $unknown_count
 * @property string $profile_fingerprint
 * @property string $opportunity_fingerprint
 * @property Carbon|null $profile_updated_at
 * @property Carbon|null $opportunity_updated_at
 * @property string $algorithm_version
 * @property string $scoring_version
 * @property string $classifier_schema_version
 * @property string|null $failure_code
 * @property string|null $failure_reason
 * @property string|null $request_id
 * @property string|null $classifier_provider
 * @property string|null $classifier_model
 * @property string|null $classifier_prompt_version
 * @property int|null $classifier_latency_ms
 * @property int|null $classifier_tokens_prompt
 * @property int|null $classifier_tokens_completion
 * @property string|null $classifier_response_id
 * @property string|null $classifier_status
 * @property Carbon|null $queued_at
 * @property Carbon|null $processing_started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $failed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CandidateProfile $candidateProfile
 * @property-read JobOpportunity $jobOpportunity
 *
 * @method static \Database\Factories\MatchAnalysisFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MatchAnalysis query()
 *
 * @mixin \Eloquent
 */
class MatchAnalysis extends Model
{
    /** @use HasFactory<MatchAnalysisFactory> */
    use HasFactory;

    protected $fillable = [
        'candidate_profile_id',
        'job_opportunity_id',
        'status',
        'operation_key',
        'overall_score',
        'evidence_coverage_score',
        'required_count',
        'preferred_count',
        'matched_count',
        'partial_count',
        'gap_count',
        'unknown_count',
        'profile_fingerprint',
        'opportunity_fingerprint',
        'profile_updated_at',
        'opportunity_updated_at',
        'algorithm_version',
        'scoring_version',
        'classifier_schema_version',
        'failure_code',
        'failure_reason',
        'request_id',
        'classifier_provider',
        'classifier_model',
        'classifier_prompt_version',
        'classifier_latency_ms',
        'classifier_tokens_prompt',
        'classifier_tokens_completion',
        'classifier_response_id',
        'classifier_status',
        'queued_at',
        'processing_started_at',
        'completed_at',
        'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => MatchAnalysisStatus::class,
            'overall_score' => 'integer',
            'evidence_coverage_score' => 'integer',
            'required_count' => 'integer',
            'preferred_count' => 'integer',
            'matched_count' => 'integer',
            'partial_count' => 'integer',
            'gap_count' => 'integer',
            'unknown_count' => 'integer',
            'classifier_latency_ms' => 'integer',
            'classifier_tokens_prompt' => 'integer',
            'classifier_tokens_completion' => 'integer',
            'profile_updated_at' => 'datetime',
            'opportunity_updated_at' => 'datetime',
            'queued_at' => 'datetime',
            'processing_started_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<CandidateProfile, $this> */
    public function candidateProfile(): BelongsTo
    {
        return $this->belongsTo(CandidateProfile::class);
    }

    /** @return BelongsTo<JobOpportunity, $this> */
    public function jobOpportunity(): BelongsTo
    {
        return $this->belongsTo(JobOpportunity::class);
    }

    /** @return HasMany<MatchScore, $this> */
    public function scores(): HasMany
    {
        return $this->hasMany(MatchScore::class, 'match_analysis_id');
    }

    /** @return HasMany<MatchFinding, $this> */
    public function findings(): HasMany
    {
        return $this->hasMany(MatchFinding::class, 'match_analysis_id');
    }
}
