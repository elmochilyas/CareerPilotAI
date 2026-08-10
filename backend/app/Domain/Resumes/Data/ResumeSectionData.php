<?php

namespace App\Domain\Resumes\Data;

final readonly class ResumeSectionData
{
    /**
     * @param  array<int, ResumeItemData>  $items
     */
    public function __construct(
        public string $key,
        public string $title,
        public array $items,
        public int $displayOrder,
    ) {}
}
