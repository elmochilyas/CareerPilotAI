<?php

namespace App\Domain\Clarification\Enums;

enum ClarificationTargetType: string
{
    case CandidateSkill = 'candidate_skill';
    case ProfileItem = 'profile_item';
}
