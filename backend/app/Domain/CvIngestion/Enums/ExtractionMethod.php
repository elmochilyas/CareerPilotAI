<?php

namespace App\Domain\CvIngestion\Enums;

enum ExtractionMethod: string
{
    case AiExtraction = 'ai_extraction';
    case DeterministicParser = 'deterministic_parser';
}
