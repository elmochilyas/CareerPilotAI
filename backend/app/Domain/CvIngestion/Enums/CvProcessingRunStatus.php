<?php

namespace App\Domain\CvIngestion\Enums;

enum CvProcessingRunStatus: string
{
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}
