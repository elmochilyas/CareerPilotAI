<?php

namespace App\Domain\Resumes\Enums;

enum TailoringChangeType: string
{
    case Reword = 'reword';
    case Reorder = 'reorder';
    case Include = 'include';
    case Exclude = 'exclude';
}
