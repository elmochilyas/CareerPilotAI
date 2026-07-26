<?php

namespace App\Domain\CvIngestion\Enums;

enum CvSuggestionType: string
{
    case BasicInformation = 'basic_information';
    case Headline = 'headline';
    case Summary = 'summary';
    case SocialLink = 'social_link';
    case Experience = 'experience';
    case Education = 'education';
    case Project = 'project';
    case Certification = 'certification';
    case Language = 'language';
    case Skill = 'skill';
}
