<?php

namespace App\Models;

use App\Domain\CvIngestion\Enums\CvImportBatchStatus;
use Database\Factories\CvImportBatchFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $cv_document_id
 * @property int $user_id
 * @property CvImportBatchStatus $status
 * @property string $idempotency_key
 * @property Carbon|null $imported_at
 * @property string|null $failure_reason
 * @property string|null $failure_code
 * @property array|null $summary
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CvDocument $cvDocument
 * @property-read User $user
 * @property-read Collection<int, CvSuggestion> $suggestions
 * @property-read int|null $suggestions_count
 *
 * @method static \Database\Factories\CvImportBatchFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CvImportBatch newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CvImportBatch newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CvImportBatch query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CvImportBatch whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CvImportBatch whereCvDocumentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CvImportBatch whereFailureCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CvImportBatch whereFailureReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CvImportBatch whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CvImportBatch whereIdempotencyKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CvImportBatch whereImportedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CvImportBatch whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CvImportBatch whereSummary($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CvImportBatch whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CvImportBatch whereUserId($value)
 *
 * @mixin \Eloquent
 */
class CvImportBatch extends Model
{
    /** @use HasFactory<CvImportBatchFactory> */
    use HasFactory;

    protected $fillable = [
        'cv_document_id',
        'user_id',
        'status',
        'idempotency_key',
        'imported_at',
        'failure_reason',
        'failure_code',
        'summary',
    ];

    protected function casts(): array
    {
        return [
            'status' => CvImportBatchStatus::class,
            'summary' => 'array',
            'imported_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<CvDocument, $this> */
    public function cvDocument(): BelongsTo
    {
        return $this->belongsTo(CvDocument::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<CvSuggestion, $this> */
    public function suggestions(): HasMany
    {
        return $this->hasMany(CvSuggestion::class);
    }
}
