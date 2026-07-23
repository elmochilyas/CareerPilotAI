<?php

namespace App\Domain\Profile\Enums;

enum ContractType: string
{
    case FullTime = 'full-time';
    case PartTime = 'part-time';
    case Contract = 'contract';
    case Internship = 'internship';
    case Freelance = 'freelance';
}
