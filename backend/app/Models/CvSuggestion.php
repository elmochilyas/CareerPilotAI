<?php

namespace App\Models;

use App\Domain\CvIngestion\Enums\CvSuggestionReviewStatus;
use App\Domain\CvIngestion\Enums\CvSuggestionType;
use App\Domain\CvIngestion\Enums\ExtractionMethod;
use Database\Factories\CvSuggestionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $cv_document_id
 * @property int $cv_processing_run_id
 * @property CvSuggestionType $type
 * @property string|null $category
 * @property string|null $field_name
 * @property array|null $current_value
 * @property array $suggested_value
 * @property int|null $source_page
 * @property string|null $source_text
 * @property ExtractionMethod $extraction_method
 * @property string $schema_version
 * @property float|null $confidence
 * @property CvSuggestionReviewStatus $review_status
 * @property array|null $reviewed_decision
 * @property Carbon|null $reviewed_at
 * @property int|null $import_batch_id
 * @property string|null $import_status
 * @property int|null $applied_profile_id
 * @property int|null $applied_skill_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CvDocument $cvDocument
 * @property-read CvProcessingRun $cvProcessingRun
 * @property-read CvImportBatch|null $importBatch
 *
 * @method static \Database\Factories\CvSuggestionFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CvSuggestion newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CvSuggestion newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CvSuggestion query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CvSuggestion whereAppliedProfileId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CvSuggestion whereAppliedSkillId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CvSuggestion whereCategory($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CvSuggestion whereConfidence($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CvSuggestion whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CvSuggestion whereCurrentValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CvSuggestion whereCvDocumentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CvSuggestion whereCvProcessingRunId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CvSuggestion whereExtractionMethod($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CvSuggestion whereFieldName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CvSuggestion whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CvSuggestion whereImportBatchId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CvSuggestion whereImportStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CvSuggestion whereReviewStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CvSuggestion whereReviewedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CvSuggestion whereReviewedDecision($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CvSuggestion whereSchemaVersion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CvSuggestion whereSourcePage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CvSuggestion whereSourceText($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CvSuggestion whereSuggestedValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CvSuggestion whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CvSuggestion whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class CvSuggestion extends Model
{
    /** @use HasFactory<CvSuggestionFactory> */
    use HasFactory;

    protected $fillable = [
        'cv_document_id',
        'cv_processing_run_id',
        'type',
        'category',
        'field_name',
        'current_value',
        'suggested_value',
        'source_page',
        'source_text',
        'extraction_method',
        'schema_version',
        'confidence',
        'review_status',
        'reviewed_decision',
        'reviewed_at',
        'import_batch_id',
        'import_status',
        'applied_profile_id',
        'applied_skill_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => CvSuggestionType::class,
            'review_status' => CvSuggestionReviewStatus::class,
            'extraction_method' => ExtractionMethod::class,
            'current_value' => 'array',
            'suggested_value' => 'array',
            'reviewed_decision' => 'array',
            'confidence' => 'decimal:2',
            'reviewed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<CvDocument, $this> */
    public function cvDocument(): BelongsTo
    {
        return $this->belongsTo(CvDocument::class);
    }

    /** @return BelongsTo<CvProcessingRun, $this> */
    public function cvProcessingRun(): BelongsTo
    {
        return $this->belongsTo(CvProcessingRun::class);
    }

    /** @return BelongsTo<CvImportBatch, $this> */
    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(CvImportBatch::class);
    }
}
