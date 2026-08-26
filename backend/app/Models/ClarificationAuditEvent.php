<?php

namespace App\Models;

use App\Domain\Clarification\Enums\ClarificationTargetType;
use Database\Factories\ClarificationAuditEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $answer_id
 * @property int|null $proposal_id
 * @property int $user_id
 * @property int|null $match_analysis_id
 * @property ClarificationTargetType|null $target_type
 * @property int|null $target_id
 * @property string|null $event
 * @property string|null $field
 * @property array|null $before_value
 * @property array|null $after_value
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ClarificationAnswer|null $answer
 * @property-read ClarificationProposal|null $proposal
 * @property-read User $user
 * @property-read MatchAnalysis|null $matchAnalysis
 *
 * @method static \Database\Factories\ClarificationAuditEventFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClarificationAuditEvent query()
 *
 * @mixin \Eloquent
 */
class ClarificationAuditEvent extends Model
{
    /** @use HasFactory<ClarificationAuditEventFactory> */
    use HasFactory;

    protected $fillable = [
        'event',
        'answer_id',
        'proposal_id',
        'user_id',
        'match_analysis_id',
        'target_type',
        'target_id',
        'field',
        'before_value',
        'after_value',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'target_type' => ClarificationTargetType::class,
            'target_id' => 'integer',
            'before_value' => 'array',
            'after_value' => 'array',
            'metadata' => 'array',
        ];
    }

    /** @return BelongsTo<ClarificationAnswer, $this> */
    public function answer(): BelongsTo
    {
        return $this->belongsTo(ClarificationAnswer::class);
    }

    /** @return BelongsTo<ClarificationProposal, $this> */
    public function proposal(): BelongsTo
    {
        return $this->belongsTo(ClarificationProposal::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<MatchAnalysis, $this> */
    public function matchAnalysis(): BelongsTo
    {
        return $this->belongsTo(MatchAnalysis::class);
    }
}
