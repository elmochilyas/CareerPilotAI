<?php

namespace App\Domain\CvIngestion\Enums;

enum CvImportBatchStatus: string
{
    case Pending = 'pending';
    case Applied = 'applied';
    case Failed = 'failed';
    case PartiallyApplied = 'partially_applied';
}
