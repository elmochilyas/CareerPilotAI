<?php

namespace App\Domain\Clarification\Enums;

enum ClarificationAnswerType: string
{
    case Yes = 'yes';
    case No = 'no';
    case NoWithAck = 'no_with_ack';
    case Text = 'text';
    case SelectOption = 'select_option';
    case Number = 'number';
}
