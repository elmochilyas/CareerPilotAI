<?php

namespace App\Domain\Opportunities\Enums;

enum SuggestionType: string
{
    case JobTitle = 'job_title';
    case Company = 'company';
    case Department = 'department';
    case ExternalReference = 'external_reference';
    case Summary = 'summary';
    case ApplicationUrl = 'application_url';
    case City = 'city';
    case Region = 'region';
    case Country = 'country';
    case WorkMode = 'work_mode';
    case ContractType = 'contract_type';
    case SeniorityLevel = 'seniority_level';
    case WorkingHours = 'working_hours';
    case TravelRequired = 'travel_required';
    case RelocationRequired = 'relocation_required';
    case Responsibility = 'responsibility';
    case RequiredExperience = 'required_experience';
    case PreferredExperience = 'preferred_experience';
    case Education = 'education';
    case RequiredSkill = 'required_skill';
    case PreferredSkill = 'preferred_skill';
    case Language = 'language';
    case Certification = 'certification';
    case Compensation = 'compensation';
    case Benefit = 'benefit';
    case PublicationDate = 'publication_date';
    case ApplicationDeadline = 'application_deadline';
    case ExpectedStartDate = 'expected_start_date';
    case EmploymentDuration = 'employment_duration';
    case AdditionalRequirement = 'additional_requirement';
}
