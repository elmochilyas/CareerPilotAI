<?php

namespace App\Domain\CvIngestion\Services;

use App\Domain\CvIngestion\Data\TextExtractionResult;
use App\Domain\CvIngestion\Services\Contracts\TextExtractor;
use Smalot\PdfParser\Parser;

class PdfTextExtractor implements TextExtractor
{
    public function extract(string $filePath): TextExtractionResult
    {
        $parser = new Parser;
        $pdf = $parser->parseFile($filePath);
        $pages = $pdf->getPages();
        $texts = [];

        foreach ($pages as $page) {
            $texts[] = $page->getText();
        }

        $fullText = implode("\n\n--- Page Break ---\n\n", $texts);
        $pageCount = count($pages);
        $warnings = [];

        if (trim($fullText) === '') {
            $warnings[] = 'No extractable text found. The PDF may be image-based.';
        }

        $metadata = [];

        try {
            $metadata = $pdf->getDetails();
        } catch (\Throwable) {
            // metadata is optional
        }

        return new TextExtractionResult(
            text: $fullText,
            pageCount: $pageCount,
            metadata: $metadata,
            warnings: $warnings,
        );
    }
}
