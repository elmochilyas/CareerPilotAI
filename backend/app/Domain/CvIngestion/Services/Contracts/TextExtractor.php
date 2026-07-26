<?php

namespace App\Domain\CvIngestion\Services\Contracts;

use App\Domain\CvIngestion\Data\TextExtractionResult;

interface TextExtractor
{
    public function extract(string $filePath): TextExtractionResult;
}
