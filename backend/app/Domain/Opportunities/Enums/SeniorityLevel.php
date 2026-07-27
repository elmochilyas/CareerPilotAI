<?php

namespace App\Domain\Opportunities\Enums;

enum SeniorityLevel: string
{
    case Junior = 'junior';
    case Mid = 'mid';
    case Senior = 'senior';
    case Lead = 'lead';
    case Manager = 'manager';
    case Director = 'director';
    case Executive = 'executive';
    case Intern = 'intern';
    case Graduate = 'graduate';
}
