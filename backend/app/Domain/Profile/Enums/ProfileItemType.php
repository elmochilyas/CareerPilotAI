<?php

namespace App\Domain\Profile\Enums;

enum ProfileItemType: string
{
    case Education = 'education';
    case Experience = 'experience';
    case Project = 'project';
    case Certification = 'certification';
}
