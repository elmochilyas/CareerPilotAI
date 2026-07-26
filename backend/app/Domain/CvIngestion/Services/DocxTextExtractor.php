<?php

namespace App\Domain\CvIngestion\Services;

use App\Domain\CvIngestion\Data\TextExtractionResult;
use App\Domain\CvIngestion\Services\Contracts\TextExtractor;
use PhpOffice\PhpWord\IOFactory;

class DocxTextExtractor implements TextExtractor
{
    public function extract(string $filePath): TextExtractionResult
    {
        $phpWord = IOFactory::load($filePath);
        $fullText = '';
        $pageCount = null;
        $warnings = [];

        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if (method_exists($element, 'getText')) {
                    $fullText .= $element->getText()."\n";
                } elseif (method_exists($element, 'getElements')) {
                    foreach ($element->getElements() as $child) {
                        if (method_exists($child, 'getText')) {
                            $fullText .= $child->getText()."\n";
                        }
                    }
                }
            }
            $fullText .= "\n";
        }

        $fullText = trim($fullText);

        if ($fullText === '') {
            $warnings[] = 'No extractable text found in the document.';
        }

        return new TextExtractionResult(
            text: $fullText,
            pageCount: $pageCount,
            metadata: [],
            warnings: $warnings,
        );
    }
}
