<?php
namespace AssistedAutocataloguing\Ocr;

/**
 * Python-based OCR Bridge
 */
class PythonOcrBridge implements OcrEngineInterface
{
    private string $pythonBinary;
    private string $scriptPath;

    public function __construct(string $pythonBinary = 'python', ?string $scriptPath = null)
    {
        $this->pythonBinary = $pythonBinary;
        $this->scriptPath = $scriptPath ?? dirname(__DIR__, 2) . '/scripts/ocr_helper.py';
    }

    public function getName(): string
    {
        return 'python_bridge';
    }

    public function isAvailable(): bool
    {
        if (!file_exists($this->scriptPath)) {
            return false;
        }

        $cmd = escapeshellarg($this->pythonBinary) . ' --version 2>&1';
        $output = @shell_exec($cmd);
        return !empty($output) && stripos($output, 'Python') !== false;
    }

    public function extractText(string $imagePath, string $languages = 'ind+eng'): array
    {
        if (!file_exists($imagePath)) {
            return [
                'success' => false,
                'text' => '',
                'engine' => $this->getName(),
                'error' => 'File gambar tidak ditemukan',
            ];
        }

        if (!$this->isAvailable()) {
            return [
                'success' => false,
                'text' => '',
                'engine' => $this->getName(),
                'error' => 'Python script bridge atau Python runtime tidak tersedia',
            ];
        }

        $cmd = escapeshellarg($this->pythonBinary) . ' ' .
            escapeshellarg($this->scriptPath) . ' ' .
            escapeshellarg($imagePath) . ' ' .
            escapeshellarg($languages) . ' 2>&1';

        $output = @shell_exec($cmd);
        $json = @json_decode($output, true);

        if (is_array($json) && isset($json['text'])) {
            return [
                'success' => !empty($json['text']),
                'text' => trim($json['text']),
                'engine' => $this->getName(),
                'error' => $json['error'] ?? null,
            ];
        }

        return [
            'success' => !empty(trim($output ?: '')),
            'text' => trim($output ?: ''),
            'engine' => $this->getName(),
            'error' => empty($output) ? 'Python OCR tidak menghasilkan output' : null,
        ];
    }
}
