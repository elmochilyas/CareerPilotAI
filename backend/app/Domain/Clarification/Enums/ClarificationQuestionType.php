<?php

namespace App\Domain\Clarification\Enums;

enum ClarificationQuestionType: string
{
    case YesNo = 'yes_no';
    case YesNoWithDetails = 'yes_no_with_details';
    case Text = 'text';
    case Select = 'select';
    case Number = 'number';
}
