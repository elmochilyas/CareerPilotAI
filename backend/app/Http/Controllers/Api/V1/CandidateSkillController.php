<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Skills\Actions\AddEvidenceAction;
use App\Domain\Skills\Actions\ArchiveCandidateSkillAction;
use App\Domain\Skills\Actions\CreateCandidateSkillAction;
use App\Domain\Skills\Actions\DeleteCandidateSkillAction;
use App\Domain\Skills\Actions\ListCandidateSkillsAction;
use App\Domain\Skills\Actions\RemoveEvidenceAction;
use App\Domain\Skills\Actions\RestoreCandidateSkillAction;
use App\Domain\Skills\Actions\ShowCandidateSkillAction;
use App\Domain\Skills\Actions\UpdateCandidateSkillAction;
use App\Domain\Skills\Actions\UpdateEvidenceAction;
use App\Domain\Skills\Data\CandidateSkillData;
use App\Domain\Skills\Data\EvidenceData;
use App\Domain\Skills\Enums\ProficiencyLevel;
use App\Domain\Skills\Enums\SkillState;
use App\Http\Requests\Api\V1\ArchiveCandidateSkillRequest;
use App\Http\Requests\Api\V1\CandidateSkill\ListCandidateSkillsRequest;
use App\Http\Requests\Api\V1\DeleteCandidateSkillRequest;
use App\Http\Requests\Api\V1\DeleteEvidenceRequest;
use App\Http\Requests\Api\V1\RestoreCandidateSkillRequest;
use App\Http\Requests\Api\V1\StoreCandidateSkillRequest;
use App\Http\Requests\Api\V1\StoreEvidenceRequest;
use App\Http\Requests\Api\V1\UpdateCandidateSkillRequest;
use App\Http\Requests\Api\V1\UpdateEvidenceRequest;
use App\Http\Resources\Api\V1\CandidateSkillCollection;
use App\Http\Resources\Api\V1\CandidateSkillResource;
use App\Models\CandidateProfile;
use App\Models\CandidateSkill;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class CandidateSkillController
{
    public function __construct(
        private readonly ListCandidateSkillsAction $listAction,
        private readonly CreateCandidateSkillAction $createAction,
        private readonly ShowCandidateSkillAction $showAction,
        private readonly UpdateCandidateSkillAction $updateAction,
        private readonly ArchiveCandidateSkillAction $archiveAction,
        private readonly RestoreCandidateSkillAction $restoreAction,
        private readonly DeleteCandidateSkillAction $deleteAction,
        private readonly AddEvidenceAction $addEvidenceAction,
        private readonly UpdateEvidenceAction $updateEvidenceAction,
        private readonly RemoveEvidenceAction $removeEvidenceAction,
    ) {}

    public function index(ListCandidateSkillsRequest $request): CandidateSkillCollection
    {
        Gate::authorize('viewAny', CandidateSkill::class);

        $profile = $request->user()->candidateProfile()->first();

        if ($profile === null) {
            return new CandidateSkillCollection(collect());
        }

        $state = $request->validated('state') !== null ? SkillState::tryFrom($request->validated('state')) : null;

        $skills = $this->listAction->execute($profile, $state);

        return new CandidateSkillCollection($skills);
    }

    public function store(StoreCandidateSkillRequest $request): CandidateSkillResource
    {
        Gate::authorize('create', CandidateSkill::class);

        $profile = $this->getProfile($request);

        $data = new CandidateSkillData(
            skillId: $request->validated('skill_id'),
            customSkillName: $request->validated('custom_skill_name'),
            state: SkillState::from($request->validated('state')),
            proficiencyLevel: ProficiencyLevel::from($request->validated('proficiency_level')),
            yearsExperience: $request->validated('years_experience'),
            lastUsedAt: $request->validated('last_used_at') ? CarbonImmutable::parse($request->validated('last_used_at')) : null,
            evidence: $request->validated('skill_id') === null ? [['key' => Str::uuid(), 'type' => 'text', 'value' => $request->validated('custom_skill_name'), 'label' => 'Custom skill name']] : null,
        );

        $skill = $this->createAction->execute($profile, $data);

        return new CandidateSkillResource($skill);
    }

    public function show(Request $request, int $id): CandidateSkillResource
    {
        $profile = $this->getProfile($request);
        $skill = $this->resolveOwnedSkill($profile, $id);
        Gate::authorize('view', $skill);

        $skill = $this->showAction->execute($profile, $id);

        return new CandidateSkillResource($skill);
    }

    public function update(UpdateCandidateSkillRequest $request, int $id): CandidateSkillResource
    {
        $profile = $this->getProfile($request);
        $skill = $this->resolveOwnedSkill($profile, $id);
        Gate::authorize('update', $skill);

        $skill = $this->updateAction->execute(
            profile: $profile,
            id: $id,
            newState: $request->validated('state') ? SkillState::tryFrom($request->validated('state')) : null,
            proficiencyLevel: $request->validated('proficiency_level') ? ProficiencyLevel::from($request->validated('proficiency_level')) : null,
            yearsExperience: $request->validated('years_experience'),
            lastUsedAt: $request->validated('last_used_at'),
            expectedUpdatedAt: $request->validated('updated_at'),
        );

        return new CandidateSkillResource($skill);
    }

    public function archive(ArchiveCandidateSkillRequest $request, int $id): CandidateSkillResource
    {
        $profile = $this->getProfile($request);
        $skill = $this->resolveOwnedSkill($profile, $id);
        Gate::authorize('archive', $skill);

        $skill = $this->archiveAction->execute($profile, $id, $request->validated('updated_at'));

        return new CandidateSkillResource($skill);
    }

    public function restore(RestoreCandidateSkillRequest $request, int $id): CandidateSkillResource
    {
        $profile = $this->getProfile($request);
        $skill = $this->resolveOwnedSkill($profile, $id);
        Gate::authorize('restore', $skill);

        $targetState = SkillState::from($request->validated('state'));

        $skill = $this->restoreAction->execute($profile, $id, $targetState, $request->validated('updated_at'));

        return new CandidateSkillResource($skill);
    }

    public function destroy(DeleteCandidateSkillRequest $request, int $id): JsonResponse
    {
        $profile = $this->getProfile($request);
        $skill = $this->resolveOwnedSkill($profile, $id);
        Gate::authorize('delete', $skill);

        $this->deleteAction->execute($profile, $id);

        return response()->json(null, 204);
    }

    public function storeEvidence(StoreEvidenceRequest $request, int $id): CandidateSkillResource
    {
        $profile = $this->getProfile($request);
        $skill = $this->resolveOwnedSkill($profile, $id);
        Gate::authorize('addEvidence', $skill);

        $evidenceData = new EvidenceData(
            type: $request->validated('type'),
            value: $request->validated('value'),
            label: $request->validated('label'),
        );

        $skill = $this->addEvidenceAction->execute(
            $profile,
            $id,
            $evidenceData,
            $request->validated('updated_at'),
        );

        return new CandidateSkillResource($skill);
    }

    public function updateEvidence(UpdateEvidenceRequest $request, int $id, string $evidenceKey): CandidateSkillResource
    {
        $profile = $this->getProfile($request);
        $skill = $this->resolveOwnedSkill($profile, $id);
        Gate::authorize('updateEvidence', $skill);

        $skill = $this->updateEvidenceAction->execute(
            $profile,
            $id,
            $evidenceKey,
            $request->validated(),
            $request->validated('updated_at'),
        );

        return new CandidateSkillResource($skill);
    }

    public function destroyEvidence(DeleteEvidenceRequest $request, int $id, string $evidenceKey): CandidateSkillResource
    {
        $profile = $this->getProfile($request);
        $skill = $this->resolveOwnedSkill($profile, $id);
        Gate::authorize('removeEvidence', $skill);

        $skill = $this->removeEvidenceAction->execute(
            $profile,
            $id,
            $evidenceKey,
            $request->validated('updated_at'),
        );

        return new CandidateSkillResource($skill);
    }

    /**
     * Resolve the skill scoped to the owning profile. A foreign id is
     * indistinguishable from a missing one (404), and the policy check that
     * follows guards against future call sites bypassing this scoping.
     */
    private function resolveOwnedSkill(CandidateProfile $profile, int $id): CandidateSkill
    {
        $skill = CandidateSkill::query()
            ->where('candidate_profile_id', $profile->id)
            ->find($id);

        if ($skill === null) {
            throw (new ModelNotFoundException)->setModel(CandidateSkill::class, [$id]);
        }

        return $skill;
    }

    private function getProfile(Request $request): CandidateProfile
    {
        $profile = $request->user()->candidateProfile()->first();

        if ($profile === null) {
            $profile = new CandidateProfile;
            $profile->user_id = $request->user()->id;
            $profile->save();
        }

        return $profile;
    }
}
