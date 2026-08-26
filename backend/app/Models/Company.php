<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'website',
        'industry',
        'location',
        'size_band',
        'research',
        'researched_at',
        'research_status',
        'research_version',
        'research_failure_code',
        'name_normalized',
        'website_canonical',
    ];

    protected $casts = [
        'research' => 'array',
        'researched_at' => 'datetime',
        'research_version' => 'integer',
    ];
}
