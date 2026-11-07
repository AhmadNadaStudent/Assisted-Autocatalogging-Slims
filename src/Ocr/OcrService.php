<?php
namespace AssistedAutocataloguing\Ocr;

use AssistedAutocataloguing\Core\Application;
use AssistedAutocataloguing\Core\Security;

/**
 * High-level OCR & Image Processing Service
 */
class OcrService
{
    private Application $app;
    private ImagePreprocessor $preprocessor;
    private array $engines = [];
    private string $uploadDir;
    private string $thumbDir;

    public function __construct(?Application $app = null)
    {
        $this->app = $app ?? Application::getInstance();
        $this->preprocessor = new ImagePreprocessor();

        $this->uploadDir = $this->app->getStoragePath('uploads');
        $this->thumbDir = $this->app->getStoragePath('uploads/thumbs');

        if (!is_dir($this->uploadDir)) {
            @mkdir($this->uploadDir, 0755, true);
        }
        if (!is_dir($this->thumbDir)) {
            @mkdir($this->thumbDir, 0755, true);
        }

        $ocrConfig = $this->app->getConfig('ocr', []);
        $tesseractBin = $ocrConfig['tesseract_binary'] ?? 'tesseract';
        $pythonBin = $ocrConfig['python_binary'] ?? 'python';

        $this->engines['tesseract'] = new TesseractOcrEngine($tesseractBin);
        $this->engines['python_bridge'] = new PythonOcrBridge($pythonBin);
    }

    public function processUpload(array $fileInfo, string $pageType = 'title_page'): array
    {
        $validation = $this->preprocessor->validateUploadedFile(
            $fileInfo,
            $this->app->getConfig('ocr.max_file_size', 10485760)
        );

        if (!$validation['valid']) {
            return [
                'success' => false,
                'error' => $validation['error'],
            ];
        }

        // Generate safe unique filename
        $ext = pathinfo($fileInfo['name'], PATHINFO_EXTENSION);
        $safeBase = Security::sanitizeFileName(pathinfo($fileInfo['name'], PATHINFO_FILENAME));
        $uniqueId = bin2hex(random_bytes(8));
        $savedFileName = 'page_' . date('Ymd_His') . '_' . $uniqueId . '.' . strtolower($ext ?: 'jpg');
        $savedFilePath = $this->uploadDir . '/' . $savedFileName;

        // Move uploaded file to uploads directory
        if (!move_uploaded_file($fileInfo['tmp_name'], $savedFilePath)) {
            return [
                'success' => false,
                'error' => 'Gagal memindahkan file ke direktori penyimpanan',
            ];
        }

        // Create thumbnail for UI
        $thumbFileName = 'thumb_' . $uniqueId . '.jpg';
        $thumbFilePath = $this->thumbDir . '/' . $thumbFileName;
        $this->preprocessor->createThumbnail($savedFilePath, $thumbFilePath, 360);

        // Preprocess image for OCR (high contrast grayscale)
        $ocrReadyPath = $this->uploadDir . '/ocr_' . $uniqueId . '.jpg';
        $this->preprocessor->preprocessForOcr($savedFilePath, $ocrReadyPath);

        // Perform OCR
        $evidenceId = 'evidence-' . $uniqueId;
        $ocrResult = $this->runOcr($ocrReadyPath);

        return [
            'success' => true,
            'evidence_id' => $evidenceId,
            'original_name' => $fileInfo['name'],
            'file_name' => $savedFileName,
            'file_path' => $savedFilePath,
            'thumb_name' => $thumbFileName,
            'page_type' => $pageType,
            'ocr_text' => $ocrResult['text'],
            'ocr_status' => $ocrResult['success'] ? 'completed' : 'empty',
            'engine' => $ocrResult['engine'],
            'error' => $ocrResult['error'],
            'timestamp' => date('Y-m-d H:i:s'),
        ];
    }

    public function runOcr(string $imagePath): array
    {
        $languages = $this->app->getConfig('ocr.languages', 'ind+eng');

        // 1. Try Tesseract first
        if ($this->engines['tesseract']->isAvailable()) {
            $res = $this->engines['tesseract']->extractText($imagePath, $languages);
            if ($res['success']) {
                return $res;
            }
        }

        // 2. Try Python Bridge
        if ($this->engines['python_bridge']->isAvailable()) {
            $res = $this->engines['python_bridge']->extractText($imagePath, $languages);
            if ($res['success']) {
                return $res;
            }
        }

        return [
            'success' => false,
            'text' => '',
            'engine' => 'none',
            'error' => 'Mesin OCR lokal (Tesseract/Python) belum terpasang atau tidak merespons. Anda dapat mengetik/mengoreksi teks bukti secara manual atau menggunakan provider AI.',
        ];
    }

    public function getEngineStatus(): array
    {
        return [
            'tesseract' => [
                'name' => 'Tesseract OCR',
                'available' => $this->engines['tesseract']->isAvailable(),
            ],
            'python_bridge' => [
                'name' => 'Python Bridge',
                'available' => $this->engines['python_bridge']->isAvailable(),
            ],
        ];
    }
}
