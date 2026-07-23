<?php

namespace App\Domain\Profile\Enums;

enum AvailabilityStatus: string
{
    case Immediately = 'immediately';
    case WithinTwoWeeks = 'within_2_weeks';
    case WithinMonth = 'within_month';
    case NotLooking = 'not_looking';
}
