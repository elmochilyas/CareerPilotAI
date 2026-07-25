<?php

namespace App\Domain\Skills\Actions;

use App\Models\Skill;

class ShowSkillAction
{
    public function execute(int $id): Skill
    {
        return Skill::where('is_active', true)->with('aliases')->findOrFail($id);
    }
}
