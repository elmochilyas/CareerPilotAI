<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobRequirement extends Model
{
    protected $fillable = [
        'job_opportunity_id',
        'category',
        'content',
        'classification',
        'language',
        'language_proficiency',
        'display_order',
    ];

    public function jobOpportunity(): BelongsTo
    {
        return $this->belongsTo(JobOpportunity::class, 'job_opportunity_id');
    }
}
