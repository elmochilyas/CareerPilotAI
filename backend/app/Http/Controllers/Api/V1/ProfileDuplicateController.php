<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Profile\Actions\DeduplicateLanguagesAction;
use App\Domain\Profile\Actions\MergeCandidateSkillsAction;
use App\Domain\Profile\Actions\MergeProfileItemsAction;
use App\Domain\Profile\Services\ProfileDuplicateDetector;
use App\Http\Controllers\Controller;
use App\Models\CandidateProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileDuplicateController extends Controller
{
    public function index(Request $request, ProfileDuplicateDetector $detector): JsonResponse
    {
        $profile = CandidateProfile::where('user_id', $request->user()->id)->with(['items', 'candidateSkills.skill'])->first();

        if (! $profile) {
            return response()->json(['data' => ['groups' => [], 'summary' => ['exact' => 0, 'possible' => 0, 'legitimate' => 0]]]);
        }

        $report = $detector->detectForProfile($profile);

        return response()->json(['data' => $report]);
    }

    public function cleanupItems(Request $request, MergeProfileItemsAction $action): JsonResponse
    {
        $validated = $request->validate([
            'keep_id' => ['required', 'integer', 'exists:profile_items,id'],
            'duplicate_ids' => ['required', 'array', 'min:1'],
            'duplicate_ids.*' => ['integer', 'exists:profile_items,id'],
            'allow_possible' => ['sometimes', 'boolean'],
        ]);

        $profile = CandidateProfile::where('user_id', $request->user()->id)->firstOrFail();

        $result = $action->execute($profile, $validated['keep_id'], $validated['duplicate_ids'], (bool) ($validated['allow_possible'] ?? false));

        return response()->json(['data' => $result]);
    }

    public function cleanupSkills(Request $request, MergeCandidateSkillsAction $action): JsonResponse
    {
        $validated = $request->validate([
            'keep_id' => ['required', 'integer', 'exists:candidate_skills,id'],
            'duplicate_ids' => ['required', 'array', 'min:1'],
            'duplicate_ids.*' => ['integer', 'exists:candidate_skills,id'],
        ]);

        $profile = CandidateProfile::where('user_id', $request->user()->id)->firstOrFail();

        $result = $action->execute($profile, $validated['keep_id'], $validated['duplicate_ids']);

        return response()->json(['data' => $result]);
    }

    public function cleanupLanguages(Request $request, DeduplicateLanguagesAction $action): JsonResponse
    {
        $validated = $request->validate([
            'keep_language' => ['sometimes', 'string', 'max:50'],
            'duplicate_languages' => ['sometimes', 'array'],
            'duplicate_languages.*' => ['string'],
        ]);

        $profile = CandidateProfile::where('user_id', $request->user()->id)->firstOrFail();

        if (isset($validated['keep_language']) && isset($validated['duplicate_languages'])) {
            $result = $action->mergeSpecific($profile, $validated['keep_language'], $validated['duplicate_languages']);
        } else {
            $result = $action->execute($profile);
        }

        return response()->json(['data' => $result]);
    }
}
