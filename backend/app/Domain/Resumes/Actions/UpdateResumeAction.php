<?php

namespace App\Domain\Resumes\Actions;

use App\Domain\Resumes\Data\ResumeContentData;
use App\Domain\Resumes\Enums\ResumeStatus;
use App\Exceptions\Api\ConflictException;
use App\Models\Resume;

final readonly class UpdateResumeAction
{
    public function execute(Resume $resume, ResumeContentData $content): Resume
    {
        if ($resume->status !== ResumeStatus::Draft) {
            throw new ConflictException(
                'Only draft resumes can be updated.',
                'not_draft',
            );
        }

        $resume->update([
            'content' => $this->serializeContent($content),
            'version_no' => $resume->version_no + 1,
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
