<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Skills\Actions\SearchSkillsAction;
use App\Domain\Skills\Actions\ShowSkillAction;
use App\Http\Requests\Api\V1\SearchSkillRequest;
use App\Http\Resources\Api\V1\SkillCollection;
use App\Http\Resources\Api\V1\SkillResource;

class SkillController
{
    public function __construct(
        private readonly SearchSkillsAction $searchSkills,
        private readonly ShowSkillAction $showSkill,
    ) {}

    public function index(SearchSkillRequest $request): SkillCollection
    {
        $skills = $this->searchSkills->execute(
            q: $request->validated('q'),
            category: $request->validated('category'),
        );

        return new SkillCollection($skills);
    }

    public function show(int $id): SkillResource
    {
        $skill = $this->showSkill->execute($id);

        return new SkillResource($skill);
    }
}
