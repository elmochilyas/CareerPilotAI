<?php

namespace App\Domain\CvIngestion\Services\Contracts;

use App\Domain\CvIngestion\Data\CvAnalysisResult;

interface CvAnalyzer
{
    public function analyze(string $extractedText): CvAnalysisResult;
}
