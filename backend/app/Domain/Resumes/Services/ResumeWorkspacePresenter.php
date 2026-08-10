<?php

namespace App\Domain\Resumes\Services;

use App\Domain\Resumes\Enums\TailoringProposalStatus;
use App\Models\Resume;

final readonly class ResumeWorkspacePresenter
{
    /** @return array{headline: string|null, summary: string|null, sections: array<int, array<string, mixed>>, proposals: array<int, array<string, mixed>>} */
    public function present(Resume $resume, bool $selectedOnly = false): array
    {
        $resume->loadMissing('proposals');
        $proposals = $resume->proposals;
        $sections = [];

        foreach (($resume->content ?? []) as $key => $section) {
            if (! is_array($section)) {
                continue;
            }

            $items = [];
            foreach (($section['items'] ?? []) as $item) {
                $metadata = is_array($item['metadata'] ?? null) ? $item['metadata'] : [];
                if ($selectedOnly && ($metadata['selected'] ?? true) === false) {
                    continue;
                }

                $sourceRef = ($item['source_type'] ?? 'unknown').':'.($item['source_id'] ?? 0);
                $originalText = (string) ($item['text'] ?? '');
                $itemProposals = $proposals->filter(function ($proposal) use ($sourceRef, $originalText): bool {
                    $proposalRef = "{$proposal->source_type}:{$proposal->source_id}";
                    $normalizedProposalText = trim(preg_replace('/^[-*]\s*/u', '', $proposal->original_text) ?? $proposal->original_text);

                    return $proposalRef === $sourceRef || $normalizedProposalText === trim($originalText);
                });
                $accepted = $itemProposals->first(fn ($proposal): bool => $proposal->status === TailoringProposalStatus::Accepted);
                $currentText = $accepted ? (string) ($accepted->edited_text ?: $accepted->proposed_text) : $originalText;

                $items[] = [
                    'source_ref' => $sourceRef,
                    'original_text' => $originalText,
                    'current_text' => $currentText,
                    'ai_proposals' => $itemProposals->map(fn ($proposal): array => [
                        'id' => $proposal->id,
                        'source_ref' => $sourceRef,
                        'original_text' => $proposal->original_text,
                        'proposed_text' => $proposal->proposed_text,
                        'change_type' => $proposal->change_type,
                        'status' => $proposal->status->value,
                        'edited_text' => $proposal->edited_text,
                        'accepted_at' => $proposal->accepted_at?->toIso8601String(),
                    ])->values()->all(),
                    'metadata' => $metadata,
                ];
            }

            $sections[] = [
                'type' => (string) $key,
                'title' => (string) ($section['title'] ?? $key),
                'items' => $items,
                'display_order' => (int) ($section['display_order'] ?? count($sections)),
                'has_changes' => collect($items)->contains(fn (array $item): bool => $item['original_text'] !== $item['current_text']),
            ];
        }

        usort($sections, fn (array $a, array $b): int => $a['display_order'] <=> $b['display_order']);
        $summary = collect($sections)->firstWhere('type', 'summary');
        $summaryItem = $summary['items'][0] ?? null;

        return [
            'headline' => $summaryItem['metadata']['headline'] ?? null,
            'summary' => $summaryItem['current_text'] ?? null,
            'sections' => array_values(array_filter($sections, fn (array $section): bool => $section['type'] !== 'summary')),
            'proposals' => $proposals->map(fn ($proposal): array => [
                'id' => $proposal->id,
                'source_ref' => "{$proposal->source_type}:{$proposal->source_id}",
                'original_text' => $proposal->original_text,
                'proposed_text' => $proposal->proposed_text,
                'change_type' => $proposal->change_type,
                'status' => $proposal->status->value,
                'edited_text' => $proposal->edited_text,
                'accepted_at' => $proposal->accepted_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }
}
