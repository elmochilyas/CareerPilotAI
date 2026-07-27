<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobOpportunity extends Model
{
    use HasFactory;

    protected $fillable = [
        'candidate_profile_id',
        'ingestion_id',
        'company_id',
        'title',
        'company_name',
        'department',
        'external_reference',
        'summary',
        'application_url',
        'personal_label',
        'source_url',
        'city',
        'region',
        'country',
        'work_mode',
        'contract_type',
        'seniority_level',
        'working_hours',
        'travel_required',
        'relocation_required',
        'salary_min',
        'salary_max',
        'salary_currency',
        'salary_period',
        'compensation_text',
        'benefits',
        'publication_date',
        'application_deadline',
        'expected_start_date',
        'employment_duration',
        'additional_requirements',
        'source_hash',
        'saved_at',
    ];

    protected $casts = [
        'benefits' => 'array',
        'additional_requirements' => 'array',
        'travel_required' => 'boolean',
        'relocation_required' => 'boolean',
        'salary_min' => 'decimal:2',
        'salary_max' => 'decimal:2',
        'publication_date' => 'date',
        'application_deadline' => 'date',
        'expected_start_date' => 'date',
        'saved_at' => 'datetime',
    ];

    public function candidateProfile(): BelongsTo
    {
        return $this->belongsTo(CandidateProfile::class);
    }

    public function ingestion(): BelongsTo
    {
        return $this->belongsTo(JobOpportunityIngestion::class, 'ingestion_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(JobRequirement::class, 'job_opportunity_id');
    }

    public function skills(): HasMany
    {
        return $this->hasMany(JobOpportunitySkill::class, 'job_opportunity_id');
    }
}
