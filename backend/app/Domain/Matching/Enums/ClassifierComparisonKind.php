<?php

namespace App\Domain\Matching\Enums;

enum ClassifierComparisonKind: string
{
    case Responsibility = 'responsibility';
    case Experience = 'experience';
    case Education = 'education';
    case Unstructured = 'unstructured';
}
