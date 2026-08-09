<?php

namespace App\Models;

use App\Domain\Clarification\Enums\ClarificationQuestionStatus;
use App\Domain\Clarification\Enums\ClarificationQuestionType;
use Database\Factories\ClarificationQuestionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $match_analysis_id
 * @property int|null $match_finding_id
 * @property int $question_no
 * @property ClarificationQuestionType $question_type
 * @property string $prompt
 * @property string|null $detail
 * @property string $template_key
 * @property array|null $options_json
 * @property string|null $unit
 * @property ClarificationQuestionStatus $status
 * @property array|null $ai_metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read MatchAnalysis $matchAnalysis
 * @property-read MatchFinding|null $matchFinding
 * @property-read ClarificationAnswer|null $answer
 *
 * @method static \Database\Factories\ClarificationQuestionFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClarificationQuestion query()
 *
 * @mixin \Eloquent
 */
class ClarificationQuestion extends Model
{
    /** @use HasFactory<ClarificationQuestionFactory> */
    use HasFactory;

    protected $fillable = [
        'match_analysis_id',
        'match_finding_id',
        'question_no',
        'question_type',
        'prompt',
        'detail',
        'template_key',
        'options_json',
        'unit',
        'status',
        'ai_metadata',
    ];

    protected function casts(): array
    {
        return [
            'question_type' => ClarificationQuestionType::class,
            'status' => ClarificationQuestionStatus::class,
            'question_no' => 'integer',
            'options_json' => 'array',
            'ai_metadata' => 'array',
        ];
    }

    /** @return BelongsTo<MatchAnalysis, $this> */
    public function matchAnalysis(): BelongsTo
    {
        return $this->belongsTo(MatchAnalysis::class);
    }

    /** @return BelongsTo<MatchFinding, $this> */
    public function matchFinding(): BelongsTo
    {
        return $this->belongsTo(MatchFinding::class);
    }

    /** @return HasOne<ClarificationAnswer, $this> */
    public function answer(): HasOne
    {
        return $this->hasOne(ClarificationAnswer::class, 'question_id');
    }
}
