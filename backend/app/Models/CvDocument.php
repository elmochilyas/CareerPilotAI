<?php

namespace App\Models;

use App\Domain\CvIngestion\Enums\CvDocumentStatus;
use Database\Factories\CvDocumentFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $original_name
 * @property string $stored_path
 * @property string $stored_name
 * @property string $mime_type
 * @property int $size
 * @property string|null $checksum
 * @property CvDocumentStatus $status
 * @property string|null $failure_reason
 * @property string|null $failure_code
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Collection<int, CvProcessingRun> $processingRuns
 * @property-read Collection<int, CvSuggestion> $suggestions
 * @property-read Collection<int, CvImportBatch> $importBatches
 * @property-read CvProcessingRun|null $latestRun
 * @property-read int|null $import_batches_count
 * @property-read int|null $processing_runs_count
 * @property-read int|null $suggestions_count
 *
 * @method static \Database\Factories\CvDocumentFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CvDocument newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CvDocument newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CvDocument query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CvDocument whereChecksum($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CvDocument whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CvDocument whereFailureCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CvDocument whereFailureReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CvDocument whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CvDocument whereMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CvDocument whereMimeType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CvDocument whereOriginalName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CvDocument whereSize($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CvDocument whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CvDocument whereStoredName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CvDocument whereStoredPath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CvDocument whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CvDocument whereUserId($value)
 *
 * @mixin \Eloquent
 */
class CvDocument extends Model
{
    /** @use HasFactory<CvDocumentFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'original_name',
        'stored_path',
        'stored_name',
        'mime_type',
        'size',
        'checksum',
        'status',
        'failure_reason',
        'failure_code',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => CvDocumentStatus::class,
            'metadata' => 'array',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<CvProcessingRun, $this> */
    public function processingRuns(): HasMany
    {
        return $this->hasMany(CvProcessingRun::class);
    }

    /** @return HasMany<CvSuggestion, $this> */
    public function suggestions(): HasMany
    {
        return $this->hasMany(CvSuggestion::class);
    }

    /** @return HasMany<CvImportBatch, $this> */
    public function importBatches(): HasMany
    {
        return $this->hasMany(CvImportBatch::class);
    }

    /** @return HasOne<CvProcessingRun, $this> */
    public function latestRun(): HasOne
    {
        return $this->hasOne(CvProcessingRun::class)->latestOfMany();
    }
}
