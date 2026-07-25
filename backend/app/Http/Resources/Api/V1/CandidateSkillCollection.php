<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\ResourceCollection;

class CandidateSkillCollection extends ResourceCollection
{
    public $collects = CandidateSkillResource::class;
}
