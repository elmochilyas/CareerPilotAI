<?php

namespace App\Models;

use App\Domain\Matching\Enums\MatchCategory;
use Database\Factories\MatchScoreFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $match_analysis_id
 * @property MatchCategory $category
 * @property string $weight
 * @property int $score
 * @property string $achieved_points
 * @property string $total_points
 * @property bool $has_candidate_data
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read MatchAnalysis $matchAnalysis
 *
 * @method static \Database\Factories\MatchScoreFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MatchScore query()
 *
 * @mixin \Eloquent
 */
class MatchScore extends Model
{
    /** @use HasFactory<MatchScoreFactory> */
    use HasFactory;

    protected $fillable = [
        'match_analysis_id',
        'category',
        'weight',
        'score',
        'achieved_points',
        'total_points',
        'has_candidate_data',
    ];

    protected function casts(): array
    {
        return [
            'category' => MatchCategory::class,
            'weight' => 'decimal:3',
            'score' => 'integer',
            'achieved_points' => 'decimal:2',
            'total_points' => 'decimal:2',
            'has_candidate_data' => 'boolean',
        ];
    }

    /** @return BelongsTo<MatchAnalysis, $this> */
    public function matchAnalysis(): BelongsTo
    {
        return $this->belongsTo(MatchAnalysis::class);
    }
}
