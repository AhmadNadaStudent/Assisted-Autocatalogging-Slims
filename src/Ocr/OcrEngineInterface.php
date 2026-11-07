<?php
namespace AssistedAutocataloguing\Ocr;

/**
 * Interface for OCR Engines
 */
interface OcrEngineInterface
{
    public function getName(): string;
    public function isAvailable(): bool;

    /**
     * Perform OCR on an image file
     *
     * @param string $imagePath Absolute path to image
     * @param string $languages Language codes (e.g. 'ind+eng')
     * @return array [ 'success' => bool, 'text' => string, 'engine' => string, 'error' => ?string ]
     */
    public function extractText(string $imagePath, string $languages = 'ind+eng'): array;
}
