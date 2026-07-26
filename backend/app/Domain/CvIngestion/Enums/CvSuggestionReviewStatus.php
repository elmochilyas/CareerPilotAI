<?php

namespace App\Domain\CvIngestion\Enums;

enum CvSuggestionReviewStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Edited = 'edited';
    case Rejected = 'rejected';
    case KeepExisting = 'keep_existing';
    case CreateNew = 'create_new';
    case UpdateExisting = 'update_existing';
    case Imported = 'imported';
    case ImportFailed = 'import_failed';
}
