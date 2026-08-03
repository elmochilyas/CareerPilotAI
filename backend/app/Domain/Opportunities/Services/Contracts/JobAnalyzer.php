<?php

namespace App\Domain\Opportunities\Services\Contracts;

use App\Domain\Opportunities\Data\JobAnalysisResult;

interface JobAnalyzer
{
    public function analyze(string $description): JobAnalysisResult;
}
