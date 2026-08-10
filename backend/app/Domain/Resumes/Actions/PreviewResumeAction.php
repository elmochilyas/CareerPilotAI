<?php

namespace App\Domain\Resumes\Actions;

use App\Domain\Resumes\Services\ResumeWorkspacePresenter;
use App\Models\Resume;

final readonly class PreviewResumeAction
{
    public function __construct(private ResumeWorkspacePresenter $presenter) {}

    /** @return array{headline: string|null, summary: string|null, sections: array<int, array{type: string, title: string, items: array<int, array{source_ref: string, original_text: string, current_text: string, ai_proposals: array<int, mixed>, metadata: array|null}>, has_changes: bool}>} */
    public function execute(Resume $resume): array
    {
        return $this->presenter->present($resume, selectedOnly: true);
    }
}
