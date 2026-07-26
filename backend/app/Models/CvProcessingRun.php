<?php

namespace App\Models;

use App\Domain\CvIngestion\Enums\CvProcessingRunStatus;
use Database\Factories\CvProcessingRunFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $cv_document_id
 * @property CvProcessingRunStatus $status
 * @property string $pipeline_version
 * @property string $idempotency_key
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property string|null $failure_reason
 * @property string|null $failure_code
 * @property string|null $ai_provider
 * @property string|null $ai_model
 * @property string|null $ai_prompt_version
 * @property int|null $ai_latency_ms
 * @property int|null $ai_tokens_prompt
 * @property int|null $ai_tokens_completion
 * @property string|null $ai_cost_estimate
 * @property string|null $ai_response_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CvDocument $cvDocument
 * @property-read Collection<int, CvSuggestion> $suggestions
 * @property-read int|null $suggestions_count
 *
 * @method static \Database\Factories\CvProcessingRunFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CvProcessingRun newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CvProcessingRun newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CvProcessingRun query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CvProcessingRun whereAiCostEstimate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CvProcessingRun whereAiLatencyMs($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CvProcessingRun whereAiModel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CvProcessingRun whereAiPromptVersion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CvProcessingRun whereAiProvider($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CvProcessingRun whereAiResponseId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CvProcessingRun whereAiTokensCompletion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CvProcessingRun whereAiTokensPrompt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CvProcessingRun whereCompletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CvProcessingRun whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CvProcessingRun whereCvDocumentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CvProcessingRun whereFailureCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CvProcessingRun whereFailureReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CvProcessingRun whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CvProcessingRun whereIdempotencyKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CvProcessingRun wherePipelineVersion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CvProcessingRun whereStartedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CvProcessingRun whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CvProcessingRun whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class CvProcessingRun extends Model
{
    /** @use HasFactory<CvProcessingRunFactory> */
    use HasFactory;

    protected $fillable = [
        'cv_document_id',
        'status',
        'pipeline_version',
        'idempotency_key',
        'started_at',
        'completed_at',
        'failure_reason',
        'failure_code',
        'ai_provider',
        'ai_model',
        'ai_prompt_version',
        'ai_latency_ms',
        'ai_tokens_prompt',
        'ai_tokens_completion',
        'ai_cost_estimate',
        'ai_response_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => CvProcessingRunStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<CvDocument, $this> */
    public function cvDocument(): BelongsTo
    {
        return $this->belongsTo(CvDocument::class);
    }

    /** @return HasMany<CvSuggestion, $this> */
    public function suggestions(): HasMany
    {
        return $this->hasMany(CvSuggestion::class);
    }
}
