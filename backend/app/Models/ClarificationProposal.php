<?php

namespace App\Models;

use App\Domain\Clarification\Enums\ClarificationProposalStatus;
use App\Domain\Clarification\Enums\ClarificationTargetType;
use Database\Factories\ClarificationProposalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $answer_id
 * @property ClarificationTargetType $target_type
 * @property int|null $target_id
 * @property string $field
 * @property array|null $before_value
 * @property array|null $after_value
 * @property ClarificationProposalStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ClarificationAnswer $answer
 *
 * @method static \Database\Factories\ClarificationProposalFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClarificationProposal query()
 *
 * @mixin \Eloquent
 */
class ClarificationProposal extends Model
{
    /** @use HasFactory<ClarificationProposalFactory> */
    use HasFactory;

    protected $fillable = [
        'answer_id',
        'target_type',
        'target_id',
        'field',
        'before_value',
        'after_value',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'target_type' => ClarificationTargetType::class,
            'target_id' => 'integer',
            'before_value' => 'array',
            'after_value' => 'array',
            'status' => ClarificationProposalStatus::class,
        ];
    }

    /** @return BelongsTo<ClarificationAnswer, $this> */
    public function answer(): BelongsTo
    {
        return $this->belongsTo(ClarificationAnswer::class);
    }
}
