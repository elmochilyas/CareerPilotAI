<?php

namespace App\Domain\Profile\Enums;

enum WorkMode: string
{
    case Remote = 'remote';
    case Hybrid = 'hybrid';
    case OnSite = 'on_site';
}
