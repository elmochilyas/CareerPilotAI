<?php

namespace App\Domain\Skills\Enums;

enum EvidenceType: string
{
    case ProfileItem = 'profile_item';
    case Url = 'url';
    case Text = 'text';
}
