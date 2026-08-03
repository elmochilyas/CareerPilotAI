<?php

namespace App\Models;

use App\Domain\Opportunities\Enums\JobIngestionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class JobOpportunityIngestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'source_description',
        'source_url',
        'content_hash',
        'personal_label',
        'status',
        'failure_reason',
        'failure_code',
        'retry_count',
        'last_retry_at',
        'confirmed_at',
        'version',
    ];

    protected $casts = [
        'status' => JobIngestionStatus::class,
        'version' => 'integer',
        'retry_count' => 'integer',
        'last_retry_at' => 'datetime',
        'confirmed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<JobOpportunitySuggestion, $this>
     */
    public function suggestions(): HasMany
    {
        return $this->hasMany(JobOpportunitySuggestion::class, 'ingestion_id');
    }

    /**
     * @return HasOne<JobOpportunity, $this>
     */
    public function opportunity(): HasOne
    {
        return $this->hasOne(JobOpportunity::class, 'ingestion_id');
    }
}
