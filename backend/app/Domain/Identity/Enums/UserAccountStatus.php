<?php

namespace App\Domain\Identity\Enums;

enum UserAccountStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Disabled = 'disabled';
}
