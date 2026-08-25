<?php

namespace App\Domain\Resumes\Actions;

use App\Domain\Resumes\Enums\ResumeStatus;
use App\Domain\Resumes\Services\TailoringAnalyzer;
use App\Exceptions\Api\ConflictException;
use App\Models\Resume;

final readonly class ApproveResumeAction
{
    public function __construct(
        private readonly TailoringAnalyzer $analyzer,
    ) {}

    public function execute(Resume $resume): Resume
    {
        if ($resume->status !== ResumeStatus::Draft) {
            throw new ConflictException(
                'Only draft resumes can be approved.',
                'not_draft',
            );
        }

        if (empty($resume->content)) {
            throw new ConflictException(
                'Cannot approve a resume with empty content.',
                'empty_content',
            );
        }

        if ($resume->proposals()->where('status', 'proposed')->exists()) {
            throw new ConflictException(
                'Review every tailoring proposal before approving this resume.',
                'review_incomplete',
            );
        }

        $resume->loadMissing(['candidateProfile', 'opportunity']);

        $opportunity = $resume->opportunity;
        if ($opportunity !== null) {
            $reason = $this->analyzer->computeStaleness($resume->candidateProfile, $opportunity);

            if ($reason !== 'fresh' && $reason !== 'unknown') {
                throw new ConflictException(
                    'Your profile or opportunity changed since this CV was generated. Regenerate the CV before approving it.',
                    'resume_stale',
                );
            }
        }

        $resume->update([
            'status' => ResumeStatus::Approved,
            'approved_at' => now(),
        ]);

        return $resume->fresh();
    }
}
