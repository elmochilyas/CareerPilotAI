<?php

namespace App\Models;

use App\Domain\Clarification\Enums\ClarificationAnswerStatus;
use App\Domain\Clarification\Enums\ClarificationAnswerType;
use Database\Factories\ClarificationAnswerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $question_id
 * @property ClarificationAnswerType $answer_type
 * @property string $value
 * @property bool $acknowledged_no_evidence
 * @property ClarificationAnswerStatus $status
 * @property int|null $proposal_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read ClarificationQuestion $question
 * @property-read ClarificationProposal|null $proposal
 *
 * @method static \Database\Factories\ClarificationAnswerFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClarificationAnswer query()
 *
 * @mixin \Eloquent
 */
class ClarificationAnswer extends Model
{
    /** @use HasFactory<ClarificationAnswerFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'question_id',
        'answer_type',
        'value',
        'acknowledged_no_evidence',
        'status',
        'proposal_id',
    ];

    protected function casts(): array
    {
        return [
            'answer_type' => ClarificationAnswerType::class,
            'status' => ClarificationAnswerStatus::class,
            'acknowledged_no_evidence' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<ClarificationQuestion, $this> */
    public function question(): BelongsTo
    {
        return $this->belongsTo(ClarificationQuestion::class);
    }

    /** @return BelongsTo<ClarificationProposal, $this> */
    public function proposal(): BelongsTo
    {
        return $this->belongsTo(ClarificationProposal::class);
    }
}
