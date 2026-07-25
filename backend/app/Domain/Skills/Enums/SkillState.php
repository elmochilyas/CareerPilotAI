<?php

namespace App\Domain\Skills\Enums;

enum SkillState: string
{
    case Claimed = 'claimed';
    case Verified = 'verified';
    case Learning = 'learning';
    case Rejected = 'rejected';
    case Archived = 'archived';
}
