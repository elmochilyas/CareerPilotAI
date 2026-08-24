<?php

namespace App\Domain\Resumes\Actions;

use App\Domain\Profile\Services\ProfileIdentityService;
use App\Domain\Resumes\Data\TailoringResult;
use App\Domain\Resumes\Services\TailoringAnalyzer;
use App\Domain\Resumes\Services\TailoringRewriter;
use App\Domain\Resumes\Services\TailoringSchemaValidator;
use App\Domain\Skills\Enums\SkillState;
use App\Models\Resume;
use App\Models\Skill;
use App\Models\SkillAlias;
use App\Models\TailoringProposal;
use Illuminate\Support\Facades\DB;

final readonly class TailorResumeAction
{
    public function __construct(
        private TailoringAnalyzer $analyzer,
        private TailoringRewriter $rewriter,
        private TailoringSchemaValidator $validator,
    ) {}

    public function execute(Resume $resume, bool $useAi = true): TailoringResult
    {
        $profile = $resume->candidateProfile;
        $opportunity = $resume->opportunity;

        $profile->loadMissing('items', 'candidateSkills.skill');
        $opportunity->loadMissing('requirements', 'skills.skill');

        $relevantItems = $this->analyzer->analyzeRelevance($profile, $opportunity);

        $content = $this->buildDeterministicContent($resume, $relevantItems);
        $resume->update([
            'content' => $content,
            'generated_by' => $useAi ? 'ai' : 'manual',
        ]);

        $allRelevant = [];
        foreach ($relevantItems as $items) {
            $allRelevant = array_merge($allRelevant, $items);
        }

        $opportunityContext = [
            'title' => $opportunity->title,
            'summary' => $opportunity->summary,
            'skills' => $opportunity->skills->map(fn ($s) => [
                'name' => $s->skill->name,
                'classification' => $s->classification,
            ])->toArray(),
        ];

        if (! $useAi) {
            $result = new TailoringResult(
                proposals: [],
                metadata: [
                    'provider' => null,
                    'model' => null,
                    'prompt_version' => null,
                    'latency_ms' => 0,
                    'tokens_prompt' => null,
                    'tokens_completion' => null,
                    'response_id' => null,
                    'fallback_reason' => 'ai_disabled',
                ],
            );

            $this->persistResult($resume, $result);

            return $result;
        }

        $result = $this->rewriter->rewrite($resume, $allRelevant, $opportunityContext);

        $validIds = array_column($allRelevant, 'profile_item_id');
        $validatorInput = ['proposals' => array_map(fn ($p) => [
            'source_type' => $p->sourceType,
            'source_id' => $p->sourceId,
            'original_text' => $p->originalText,
            'proposed_text' => $p->proposedText,
            'change_type' => $p->changeType,
        ], $result->proposals)];
        try {
            $this->validator->validate($validatorInput, $validIds);
        } catch (\InvalidArgumentException) {
            $result = new TailoringResult(
                proposals: [],
                metadata: array_merge($result->metadata, [
                    'fallback_reason' => 'schema_validation_failure',
                ]),
            );
        }

        $this->persistResult($resume, $result);

        return $result;
    }

    /**
     * @param  array<string, array<int, array{profile_item_id: int, relevance: string, score: float, justification: string|null, source_type: string, source_id: int}>>  $relevantItems
     * @return array<string, array{title: string, items: array<int, array{source_id: int, source_type: string, text: string, display_order: int, metadata: array<string, mixed>|null}>, display_order: int}>
     */
    private function buildDeterministicContent(Resume $resume, array $relevantItems): array
    {
        $profile = $resume->candidateProfile;
        $profile->loadMissing('items', 'candidateSkills.skill');

        $content = [];
        $sectionOrder = 0;

        if ($profile->professional_summary || $profile->headline) {
            $content['summary'] = [
                'title' => 'Professional Summary',
                'items' => [[
                    'source_id' => $profile->id,
                    'source_type' => 'candidate_profile',
                    'text' => $profile->professional_summary ?? $profile->headline,
                    'display_order' => 0,
                    'metadata' => ['headline' => $profile->headline],
                ]],
                'display_order' => $sectionOrder++,
            ];
        }

        $sectionTitles = [
            'experience' => 'Experience',
            'project' => 'Projects',
            'education' => 'Education',
            'certification' => 'Certifications',
        ];

        foreach ($sectionTitles as $type => $title) {
            $relevantKey = $type === 'project' ? 'projects' : ($type === 'certification' ? 'certifications' : $type);
            $scores = array_column($relevantItems[$relevantKey], 'score', 'profile_item_id');

            // Defense in depth: deduplicate exact-normalized duplicates even if old duplicates exist.
            $deduplicatedItems = $profile->items
                ->filter(fn ($item): bool => $item->type->value === $type)
                ->sortByDesc(fn ($item): float => (float) ($scores[$item->id] ?? 0))
                ->values();

            // Keep highest-scored per normalized identity.
            $seen = [];
            $deduplicatedItems = $deduplicatedItems->filter(function ($item) use (&$seen): bool {
                $key = ProfileIdentityService::normalize($item->type->value).'|'.ProfileIdentityService::normalize($item->title).'|'.ProfileIdentityService::normalize($item->organization ?? '');
                if (isset($seen[$key])) {
                    return false;
                }
                $seen[$key] = true;

                return true;
            })->values();

            $items = $deduplicatedItems
                ->map(fn ($item, int $index): array => [
                    'source_id' => $item->id,
                    'source_type' => 'profile_item',
                    'text' => collect([$item->title, $item->organization, $item->description])
                        ->filter()
                        ->implode(' — '),
                    'display_order' => $index,
                    'metadata' => [
                        'location' => $item->location,
                        'start_date' => $item->start_date?->toDateString(),
                        'end_date' => $item->end_date?->toDateString(),
                    ],
                ])
                ->all();

            if ($items !== []) {
                $content[$relevantKey] = [
                    'title' => $title,
                    'items' => $items,
                    'display_order' => $sectionOrder++,
                ];
            }
        }

        $skillScores = array_column($relevantItems['skills'], 'score', 'profile_item_id');
        $filteredSkills = $profile->candidateSkills
            ->filter(fn ($candidateSkill): bool => in_array($candidateSkill->state, [SkillState::Verified, SkillState::Claimed], true))
            ->sortByDesc(fn ($candidateSkill): float => (float) ($skillScores[$candidateSkill->id] ?? 0))
            ->values();

        // Defense in depth: deduplicate skills by normalized identity (alias-aware) — keep highest scored.
        $skillMaps = ProfileIdentityService::buildSkillLookupMaps(Skill::all(), SkillAlias::all());
        $seenSkills = [];
        $deduplicatedSkills = $filteredSkills->filter(function ($cs) use (&$seenSkills, $skillMaps): bool {
            $skillName = $cs->skill !== null ? $cs->skill->name : ($cs->custom_skill_name ?? '');
            $resolved = ProfileIdentityService::resolveSkill($skillName, $skillMaps['skills'], $skillMaps['aliases']);
            $key = $resolved['normalized'];
            if ($key === '') {
                return true;
            }
            if (isset($seenSkills[$key])) {
                return false;
            }
            $seenSkills[$key] = true;

            return true;
        })->values();

        $skills = $deduplicatedSkills
            ->map(fn ($candidateSkill, int $index): array => [
                'source_id' => $candidateSkill->id,
                'source_type' => 'candidate_skill',
                'text' => $candidateSkill->skill !== null ? $candidateSkill->skill->name : ($candidateSkill->custom_skill_name ?? ''),
                'display_order' => $index,
                'metadata' => [
                    'state' => $candidateSkill->state->value,
                    'proficiency_level' => $candidateSkill->proficiency_level?->value,
                ],
            ])
            ->filter(fn (array $item): bool => $item['text'] !== '')
            ->all();

        if ($skills !== []) {
            $content['skills'] = [
                'title' => 'Skills',
                'items' => array_values($skills),
                'display_order' => $sectionOrder++,
            ];
        }

        // Defense in depth: deduplicate languages by normalized language (keep first).
        $deduplicatedLanguages = ProfileIdentityService::deduplicateLanguages($profile->languages ?? []);

        $languages = collect($deduplicatedLanguages)
            ->map(fn (array $language, int $index): array => [
                'source_id' => $profile->id,
                'source_type' => 'candidate_profile_language',
                'text' => trim(($language['language']).' — '.($language['proficiency']), ' —'),
                'display_order' => $index,
                'metadata' => null,
            ])
            ->filter(fn (array $item): bool => $item['text'] !== '')
            ->all();

        if ($languages !== []) {
            $content['languages'] = [
                'title' => 'Languages',
                'items' => array_values($languages),
                'display_order' => $sectionOrder,
            ];
        }

        return $content;
    }

    private function persistResult(Resume $resume, TailoringResult $result): void
    {
        DB::transaction(function () use ($resume, $result): void {
            $resume->update(['ai_metadata' => $result->metadata]);
            $resume->proposals()->delete();

            foreach ($result->proposals as $proposal) {
                TailoringProposal::query()->create([
                    'resume_id' => $resume->id,
                    'source_type' => $proposal->sourceType,
                    'source_id' => $proposal->sourceId,
                    'original_text' => $proposal->originalText,
                    'proposed_text' => $proposal->proposedText,
                    'change_type' => $proposal->changeType,
                    'status' => 'proposed',
                    'ai_metadata' => $result->metadata,
                ]);
            }
        });
    }
}
