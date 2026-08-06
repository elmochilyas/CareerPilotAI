<?php

namespace App\Domain\Matching\Enums;

enum MatchCategory: string
{
    case RequiredSkills = 'required_skills';
    case PreferredSkills = 'preferred_skills';
    case Evidence = 'evidence';
    case ExperienceEducation = 'experience_education';
    case LanguageSoft = 'language_soft';
}
