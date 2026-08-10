<?php

namespace App\Domain\Resumes\Enums;

enum ResumeStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
}
