<?php

namespace App\Domain\CompanyResearch\Enums;

enum ResearchStatus: string
{
    case NotResearched = 'not_researched';
    case Processing = 'processing';
    case Completed = 'completed';
    case Limited = 'limited';
    case Failed = 'failed';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Limited, self::Failed], true);
    }

    public function isProcessing(): bool
    {
        return $this === self::Processing;
    }
}
