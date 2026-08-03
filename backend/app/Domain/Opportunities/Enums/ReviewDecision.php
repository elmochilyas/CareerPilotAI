<?php

namespace App\Domain\Opportunities\Enums;

enum ReviewDecision: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Edited = 'edited';
    case Rejected = 'rejected';
    case KeepBlank = 'keep_blank';
    case Resolved = 'resolved';
}
