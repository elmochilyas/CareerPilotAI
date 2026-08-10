<?php

namespace App\Models;

use App\Domain\Resumes\Enums\TailoringProposalStatus;
use Database\Factories\TailoringProposalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $resume_id
 * @property string $source_type
 * @property int|null $source_id
 * @property string $original_text
 * @property string $proposed_text
 * @property string $change_type
 * @property TailoringProposalStatus $status
 * @property string|null $edited_text
 * @property Carbon|null $accepted_at
 * @property array|null $ai_metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Resume $resume
 *
 * @method static \Database\Factories\TailoringProposalFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TailoringProposal query()
 *
 * @mixin \Eloquent
 */
class TailoringProposal extends Model
{
    /** @use HasFactory<TailoringProposalFactory> */
    use HasFactory;

    protected $fillable = [
        'resume_id',
        'source_type',
        'source_id',
        'original_text',
        'proposed_text',
        'change_type',
        'status',
        'edited_text',
        'accepted_at',
        'ai_metadata',
    ];

    protected function casts(): array
    {
        return [
            'ai_metadata' => 'array',
            'accepted_at' => 'datetime',
            'status' => TailoringProposalStatus::class,
        ];
    }

    /** @return BelongsTo<Resume, $this> */
    public function resume(): BelongsTo
    {
        return $this->belongsTo(Resume::class);
    }
}
