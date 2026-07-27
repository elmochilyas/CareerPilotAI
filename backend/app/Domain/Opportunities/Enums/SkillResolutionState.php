<?php

namespace App\Domain\Opportunities\Enums;

enum SkillResolutionState: string
{
    case Exact = 'exact';
    case Alias = 'alias';
    case Ambiguous = 'ambiguous';
    case Unknown = 'unknown';
    case CandidateResolved = 'candidate_resolved';
}
