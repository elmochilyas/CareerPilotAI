<?php

namespace App\Domain\Resumes\Actions;

use App\Models\CvDocument;
use App\Models\Resume;

final readonly class RenderResumeDocumentAction
{
    public const TEMPLATE_KEY = 'source-cv-classic';

    public function __construct(private PreviewResumeAction $previewResume) {}

    public function execute(Resume $resume): string
    {
        $resume->loadMissing('candidateProfile.user');
        $profile = $resume->candidateProfile;

        $hasImportedSource = CvDocument::query()
            ->where('user_id', $profile->user_id)
            ->where('status', 'imported')
            ->exists();

        return view('resumes.document', [
            'resume' => $resume,
            'preview' => $this->previewResume->execute($resume),
            'profile' => $profile,
            'candidateName' => $profile->user->full_name,
            'templateKey' => $resume->template_key ?: ($hasImportedSource ? self::TEMPLATE_KEY : 'classic-professional'),
        ])->render();
    }
}
