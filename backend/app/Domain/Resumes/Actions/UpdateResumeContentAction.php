<?php

namespace App\Domain\Resumes\Actions;

use App\Domain\Matching\Services\FingerprintService;
use App\Domain\Matching\Services\OpportunitySnapshot;
use App\Domain\Matching\Services\ProfileSnapshot;
use App\Domain\Resumes\Data\ResumeContentData;
use App\Domain\Resumes\Data\ResumeItemData;
use App\Domain\Resumes\Data\ResumeSectionData;
use App\Domain\Resumes\Enums\ResumeStatus;
use App\Exceptions\Api\ConflictException;
use App\Models\Resume;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class UpdateResumeContentAction
{
    public function __construct(
        private readonly FingerprintService $fingerprints,
    ) {}

    public function execute(Resume $resume, array $content, array $proposalDecisions = []): Resume
    {
        if ($resume->status !== ResumeStatus::Draft) {
            throw new ConflictException('Only draft resumes can be updated.', 'not_draft');
        }

        $firstItem = $content['sections'][0]['items'][0] ?? [];
        if (isset($firstItem['source_type'])) {
            return app(UpdateResumeAction::class)->execute($resume, $this->buildLegacyContent($content));
        }

        return DB::transaction(function () use ($resume, $content, $proposalDecisions): Resume {
            $stored = $resume->content ?? [];
            $updates = collect($content['sections'] ?? [])->flatMap(fn (array $section) => $section['items'] ?? [])->keyBy('source_ref');

            foreach ($stored as &$section) {
                foreach ($section['items'] ?? [] as &$item) {
                    $sourceRef = ($item['source_type'] ?? 'unknown').':'.($item['source_id'] ?? 0);
                    $update = $updates->get($sourceRef);
                    if (! $update) {
                        continue;
                    }

                    $metadata = is_array($item['metadata'] ?? null) ? $item['metadata'] : [];
                    $metadata['selected'] = (bool) ($update['metadata']['selected'] ?? true);
                    $item['metadata'] = $metadata;
                }
                unset($item);
            }
            unset($section);

            foreach ($proposalDecisions as $decision) {
                $proposal = $resume->proposals()->find($decision['id']);
                if (! $proposal) {
                    throw ValidationException::withMessages(['proposal_decisions' => ['A proposal does not belong to this resume.']]);
                }

                $proposal->update([
                    'status' => $decision['status'],
                    'edited_text' => $decision['edited_text'] ?? null,
                    'accepted_at' => $decision['status'] === 'accepted' ? now() : null,
                ]);
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

            $freshOpportunitySnapshot = $opportunity !== null ? [
                'fingerprint' => $this->fingerprints->opportunity(OpportunitySnapshot::fromJobOpportunity($opportunity)),
                'data' => OpportunitySnapshot::fromJobOpportunity($opportunity)->toCanonicalArray(),
                'snapshot_at' => now()->toIso8601String(),
            ] : $resume->opportunity_snapshot;

            $resume->update([
                'content' => $stored,
                'version_no' => $resume->version_no + 1,
                'profile_snapshot' => $freshProfileSnapshot,
                'opportunity_snapshot' => $freshOpportunitySnapshot,
            ]);

            return $resume->fresh()->load('proposals');
        });
    }

    private function buildLegacyContent(array $content): ResumeContentData
    {
        $sections = [];
        foreach ($content['sections'] ?? [] as $sectionOrder => $section) {
            $items = [];
            foreach ($section['items'] ?? [] as $itemOrder => $item) {
                $items[] = new ResumeItemData((int) $item['source_id'], $item['source_type'], $item['tailored_text'], $itemOrder, ['relevance' => $item['relevance']]);
            }
            $sections[] = new ResumeSectionData($section['type'], $section['title'], $items, $sectionOrder);
        }

        return new ResumeContentData($sections);
    }
}
