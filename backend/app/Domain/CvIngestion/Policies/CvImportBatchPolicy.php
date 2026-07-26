<?php

namespace App\Domain\CvIngestion\Policies;

use App\Models\CvImportBatch;
use App\Models\User;

class CvImportBatchPolicy
{
    public function view(User $user, CvImportBatch $batch): bool
    {
        return $batch->user_id === $user->id;
    }
}
