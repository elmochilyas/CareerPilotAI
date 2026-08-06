<?php

namespace App\Domain\Matching\Enums;

enum MatchImportance: string
{
    case Required = 'required';
    case Preferred = 'preferred';
}
