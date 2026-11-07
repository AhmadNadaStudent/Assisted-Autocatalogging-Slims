<?php
namespace AssistedAutocataloguing\Ocr;

/**
 * Tesseract CLI OCR Engine
 */
class TesseractOcrEngine implements OcrEngineInterface
{
    private string $binaryPath;

    public function __construct(string $binaryPath = 'tesseract')
    {
        $this->binaryPath = $binaryPath;
    }

    public function getName(): string
    {
        return 'tesseract';
    }

    public function isAvailable(): bool
    {
        $cmd = escapeshellcmd($this->binaryPath) . ' --version 2>&1';
        $output = @shell_exec($cmd);
        return !empty($output) && stripos($output, 'tesseract') !== false;
    }

    public function extractText(string $imagePath, string $languages = 'ind+eng'): array
    {
        if (!file_exists($imagePath)) {
            return [
                'success' => false,
                'text' => '',
                'engine' => $this->getName(),
                'error' => 'File gambar tidak ditemukan: ' . $imagePath,
            ];
        }

        if (!$this->isAvailable()) {
            return [
                'success' => false,
                'text' => '',
                'engine' => $this->getName(),
                'error' => 'Biner Tesseract tidak ditemukan atau belum terpasang di sistem',
            ];
        }

        // Run tesseract output to stdout
        $cmd = escapeshellarg($this->binaryPath) . ' ' .
            escapeshellarg($imagePath) . ' stdout -l ' .
            escapeshellarg($languages) . ' --psm 1 2>&1';

        $output = @shell_exec($cmd);

        if ($output === null) {
            return [
                'success' => false,
                'text' => '',
                'engine' => $this->getName(),
                'error' => 'Eksekusi Tesseract gagal',
            ];
        }

        return [
            'success' => true,
            'text' => trim($output),
            'engine' => $this->getName(),
            'error' => null,
        ];
    }
}
