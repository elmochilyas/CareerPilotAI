<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobOpportunitySkill extends Model
{
    protected $fillable = [
        'job_opportunity_id',
        'skill_id',
        'original_label',
        'classification',
        'proficiency',
        'years_experience',
        'source_evidence',
        'display_order',
    ];

    protected $casts = [
        'years_experience' => 'decimal:1',
    ];

    public function jobOpportunity(): BelongsTo
    {
        return $this->belongsTo(JobOpportunity::class, 'job_opportunity_id');
    }

    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }
}
