<?php

namespace App\Domain\Resumes\Actions;

use App\Models\Resume;

final readonly class ShowResumeAction
{
    public function execute(Resume $resume): Resume
    {
        return $resume->load(['candidateProfile', 'opportunity', 'proposals']);
    }
}
