<?php

namespace App\Domain\Resumes\Actions;

use App\Domain\Matching\Services\FingerprintService;
use App\Domain\Matching\Services\OpportunitySnapshot;
use App\Domain\Matching\Services\ProfileSnapshot;
use App\Domain\Resumes\Data\ResumeContentData;
use App\Domain\Resumes\Enums\ResumeStatus;
use App\Exceptions\Api\ConflictException;
use App\Models\Resume;

final readonly class UpdateResumeAction
{
    public function __construct(
        private readonly FingerprintService $fingerprints,
    ) {}

    public function execute(Resume $resume, ResumeContentData $content): Resume
    {
        if ($resume->status !== ResumeStatus::Draft) {
            throw new ConflictException(
                'Only draft resumes can be updated.',
                'not_draft',
            );
        }

        $resume->loadMissing(['candidateProfile', 'opportunity']);
        $profile = $resume->candidateProfile;
        $opportunity = $resume->opportunity;

        $profile->loadMissing('items', 'candidateSkills.skill');
        $opportunity?->loadMissing('requirements', 'skills.skill');

        $freshProfileSnapshot = [
            'fingerprint' => $this->fingerprints->profile(ProfileSnapshot::fromCandidateProfile($profile)),
            'data' => ProfileSnapshot::fromCandidateProfile($profile)->toCanonicalArray(),
            'snapshot_at' => now()->toIso8601String(),
        ];

        $freshOpportunitySnapshot = $resume->opportunity_snapshot;

        if ($opportunity !== null) {
            $freshOpportunitySnapshot = [
                'fingerprint' => $this->fingerprints->opportunity(OpportunitySnapshot::fromJobOpportunity($opportunity)),
                'data' => OpportunitySnapshot::fromJobOpportunity($opportunity)->toCanonicalArray(),
                'snapshot_at' => now()->toIso8601String(),
            ];
        }

        $resume->update([
            'content' => $this->serializeContent($content),
            'version_no' => $resume->version_no + 1,
            'profile_snapshot' => $freshProfileSnapshot,
            'opportunity_snapshot' => $freshOpportunitySnapshot,
        ]);

        return $resume->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeContent(ResumeContentData $content): array
    {
        $sections = [];

        foreach ($content->sections as $section) {
            $items = [];

            foreach ($section->items as $item) {
                $items[] = [
                    'source_id' => $item->sourceId,
                    'source_type' => $item->sourceType,
                    'text' => $item->text,
                    'display_order' => $item->displayOrder,
                    'metadata' => $item->metadata,
                ];
            }

            $sections[$section->key] = [
                'title' => $section->title,
                'items' => $items,
                'display_order' => $section->displayOrder,
            ];
        }

        return $sections;
    }
}
