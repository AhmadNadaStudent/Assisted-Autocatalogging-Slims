<?php
require_once __DIR__ . '/../src/Core/Application.php';
\AssistedAutocataloguing\Core\Application::registerAutoloader(dirname(__DIR__));

use AssistedAutocataloguing\Core\Application;
use AssistedAutocataloguing\Ai\AiService;
use AssistedAutocataloguing\Ai\PromptTemplates;
use AssistedAutocataloguing\Ocr\OcrService;

echo "--- Testing Vision AI & Prompt Building ---" . PHP_EOL;

$app = Application::getInstance(dirname(__DIR__));
$aiService = new AiService($app);
echo "Active AI Model: " . $aiService->getActiveModelName() . PHP_EOL;

$sysPrompt = PromptTemplates::getSystemPrompt();
assert(!empty($sysPrompt), "System prompt should not be empty");

$evidence = [
    [
        'evidence_id' => 'evidence-test-1',
        'page_type' => 'title_page',
        'ocr_text' => 'Machine Learning Terapan',
    ],
    [
        'evidence_id' => 'evidence-test-2',
        'page_type' => 'copyright_page',
        'ocr_text' => 'Hak Cipta (c) 2024 PT Gramedia',
    ],
];

$userPrompt = PromptTemplates::buildUserPrompt($evidence, ['title' => 'Machine Learning Terapan']);
assert(str_contains($userPrompt, 'Machine Learning Terapan'), "User prompt must contain title");
assert(str_contains($userPrompt, 'Hak Cipta (c) 2024'), "User prompt must contain copyright");
echo "Prompt generation: PASS" . PHP_EOL;

$ocrService = new OcrService($app);
$status = $ocrService->getEngineStatus();
echo "OCR engines checked: " . implode(', ', array_keys($status)) . PHP_EOL;

echo "--- All Vision & Engine Tests PASSED ---" . PHP_EOL;
