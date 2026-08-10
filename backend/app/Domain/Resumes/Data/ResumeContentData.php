<?php

namespace App\Domain\Resumes\Data;

final readonly class ResumeContentData
{
    /**
     * @param  array<int, ResumeSectionData>  $sections
     */
    public function __construct(
        public array $sections,
    ) {}
}
