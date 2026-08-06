<?php

namespace App\Domain\Matching\Enums;

enum RequirementSourceType: string
{
    case JobRequirement = 'job_requirement';
    case JobOpportunitySkill = 'job_opportunity_skill';
}
