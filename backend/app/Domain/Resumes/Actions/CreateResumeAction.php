<?php

namespace App\Domain\Resumes\Actions;

use App\Domain\Matching\Services\FingerprintService;
use App\Domain\Matching\Services\OpportunitySnapshot;
use App\Domain\Matching\Services\ProfileSnapshot;
use App\Domain\Resumes\Enums\ResumeStatus;
use App\Exceptions\Api\ConflictException;
use App\Exceptions\Api\NotFoundException;
use App\Exceptions\Api\UnprocessableEntityException;
use App\Models\JobOpportunity;
use App\Models\MatchAnalysis;
use App\Models\Resume;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class CreateResumeAction
{
    public function __construct(
        private FingerprintService $fingerprintService,
    ) {}

    public function execute(
        User $user,
        int $opportunityId,
        ?string $title = null,
    ): Resume {
        $profile = $user->candidateProfile;
        $opportunity = JobOpportunity::findOrFail($opportunityId);

        if (! $opportunity->saved_at) {
            throw new NotFoundException('Opportunity not found', 'resume_not_found');
        }

        $hasCompletedMatch = MatchAnalysis::query()
            ->where('candidate_profile_id', $profile->id)
            ->where('job_opportunity_id', $opportunity->id)
            ->where('status', 'completed')
            ->exists();

        if (! $hasCompletedMatch) {
            throw new UnprocessableEntityException(
                'A completed match analysis is required before creating a tailored resume.',
                'tailoring_prerequisites_not_met',
            );
        }

        return DB::transaction(function () use ($profile, $opportunity, $title): Resume {
            $existingDraft = Resume::query()
                ->where('candidate_profile_id', $profile->id)
                ->where('job_opportunity_id', $opportunity->id)
                ->where('status', ResumeStatus::Draft)
                ->lockForUpdate()
                ->first();

            if ($existingDraft !== null) {
                throw new ConflictException(
                    'A draft resume already exists for this profile and opportunity.',
                    'existing_draft',
                );
            }

            $profileFingerprint = $this->fingerprintService->profile(
                ProfileSnapshot::fromCandidateProfile($profile),
            );

            $opportunityFingerprint = $this->fingerprintService->opportunity(
                OpportunitySnapshot::fromJobOpportunity($opportunity),
            );

            $profile->loadMissing('items', 'candidateSkills.skill');
            $opportunity->loadMissing('requirements', 'skills.skill');

            return Resume::query()->create([
                'candidate_profile_id' => $profile->id,
                'opportunity_id' => $opportunity->id,
                'title' => $title ?? "Resume for {$opportunity->title}",
                'template_key' => RenderResumeDocumentAction::TEMPLATE_KEY,
                'status' => ResumeStatus::Draft,
                'generated_by' => 'manual',
                'version_no' => 1,
                'content' => [],
                'profile_snapshot' => [
                    'fingerprint' => $profileFingerprint,
                    'data' => ProfileSnapshot::fromCandidateProfile($profile)->toCanonicalArray(),
                    'snapshot_at' => now()->toIso8601String(),
                ],
                'opportunity_snapshot' => [
                    'fingerprint' => $opportunityFingerprint,
                    'data' => OpportunitySnapshot::fromJobOpportunity($opportunity)->toCanonicalArray(),
                    'snapshot_at' => now()->toIso8601String(),
                ],
                'match_snapshot' => null,
                'ai_metadata' => null,
            ]);
        }, attempts: 3);
    }
}
