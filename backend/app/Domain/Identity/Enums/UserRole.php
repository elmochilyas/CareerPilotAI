<?php

namespace App\Domain\Identity\Enums;

enum UserRole: string
{
    case Candidate = 'candidate';
    case Admin = 'admin';
}
