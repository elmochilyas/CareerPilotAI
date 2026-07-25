<?php

namespace App\Domain\Skills\Actions;

use App\Models\Skill;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SearchSkillsAction
{
    public function execute(?string $q = null, ?string $category = null, int $perPage = 20): LengthAwarePaginator
    {
        $query = Skill::where('is_active', true);

        if ($q !== null && strlen($q) >= 2) {
            $like = '%'.$q.'%';
            $query->where(function ($q) use ($like): void {
                $q->where('name', 'like', $like)
                    ->orWhere('normalized_name', 'like', $like)
                    ->orWhereHas('aliases', fn ($a) => $a->where('alias', 'like', $like));
            });
        }

        if ($category !== null) {
            $query->where('category', $category);
        }

        return $query->with('aliases')->orderBy('name')->paginate($perPage);
    }
}
