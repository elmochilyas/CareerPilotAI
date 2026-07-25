<?php

namespace App\Domain\Skills\Enums;

enum ProficiencyLevel: string
{
    case Beginner = 'beginner';
    case Elementary = 'elementary';
    case Intermediate = 'intermediate';
    case Advanced = 'advanced';
    case Expert = 'expert';
}
