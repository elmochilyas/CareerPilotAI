<?php

namespace App\Domain\Resumes\Actions;

use App\Domain\Resumes\Enums\ResumeStatus;
use App\Exceptions\Api\ConflictException;
use App\Models\Resume;

final readonly class DeleteResumeAction
{
    public function execute(Resume $resume): bool
    {
        if ($resume->status !== ResumeStatus::Draft) {
            throw new ConflictException(
                'Only draft resumes can be deleted.',
                'not_draft',
            );
        }

        return $resume->delete();
    }
}
