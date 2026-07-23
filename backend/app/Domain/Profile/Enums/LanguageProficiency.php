<?php

namespace App\Domain\Profile\Enums;

enum LanguageProficiency: string
{
    case Native = 'native';
    case Fluent = 'fluent';
    case Advanced = 'advanced';
    case Intermediate = 'intermediate';
    case Basic = 'basic';
}
