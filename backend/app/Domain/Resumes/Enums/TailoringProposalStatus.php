<?php

namespace App\Domain\Resumes\Enums;

enum TailoringProposalStatus: string
{
    case Proposed = 'proposed';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
}
