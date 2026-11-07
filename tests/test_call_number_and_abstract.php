<?php
require_once __DIR__ . '/../src/Core/Application.php';
\AssistedAutocataloguing\Core\Application::registerAutoloader(dirname(__DIR__));

use AssistedAutocataloguing\Slims\SlimsBibliographicMapper;
use AssistedAutocataloguing\Slims\MasterDataResolver;

echo "--- Testing Call Number Generation & Perpusnas Standard ---\n";

// Mock resolver for testing mapper
$pdo = new PDO('sqlite::memory:');
$resolver = new MasterDataResolver($pdo);

// 1. Default Perpusnas Template: {class} {author} {title}
$mapper = new SlimsBibliographicMapper($resolver, [
    'call_number_template' => '{class} {author} {title}',
]);

$data = [
    'classification' => '495.1',
    'author' => 'Lily Thamrin',
    'title' => 'Belajar Bahasa Mandarin',
    'year' => '2014',
    'edition' => 'Cet. 2',
    'publisher' => 'Gramedia',
    'copy' => 'c.1',
];

$callNum = $mapper->generateCallNumber($data);
echo "Default Call Number: " . $callNum . "\n";
assert($callNum === '495.1 LIL b', "Expected '495.1 LIL b' but got '$callNum'");

// 2. Custom Library Template with copies & item_code: {class}/{author}/{title}/{year} {copy}
$customCallNum = $mapper->generateCallNumber($data, '{class}/{author}/{title}/{year} {copy}');
echo "Custom Call Number 1: " . $customCallNum . "\n";
assert($customCallNum === '495.1/LIL/b/2014 c.1', "Expected '495.1/LIL/b/2014 c.1' but got '$customCallNum'");

// 3. Custom Library Template with publisher & item_code
$dataWithItem = array_merge($data, ['item_code' => 'B000123']);
$customCallNum2 = $mapper->generateCallNumber($dataWithItem, '{class} {author} {title} {publisher} {item_code}');
echo "Custom Call Number 2: " . $customCallNum2 . "\n";
assert($customCallNum2 === '495.1 LIL b GRAM B000123', "Expected '495.1 LIL b GRAM B000123' but got '$customCallNum2'");

// 4. Test Schema Validator with classification candidates & notes
echo "\n--- Testing Schema Validator with Candidates & Notes ---\n";
use AssistedAutocataloguing\Ai\SchemaValidator;

$mockAiOutput = [
    'fields' => [
        'title' => ['value' => 'Penerapan Kecerdasan Buatan dalam Diagnostik Medis'],
        'notes' => ['value' => 'Buku ini mengkaji penerapan model deep learning untuk analisis citra radiologi klinis.'],
        'classification' => ['value' => '006.3'],
        'classification_candidates' => [
            [
                'class' => '006.3',
                'label' => 'Kecerdasan Buatan',
                'reasoning' => 'Pilihan tepat jika perpustakaan mengutamakan fondasi teknik dan algoritma machine learning.',
                'confidence' => 0.95
            ],
            [
                'class' => '610.28',
                'label' => 'Teknologi Medis',
                'reasoning' => 'Pilihan tepat jika fokus koleksi perpustakaan adalah aplikasi praktis pada dunia kedokteran.',
                'confidence' => 0.88
            ],
        ],
    ],
    'notes' => 'Ekstraksi multi-subjek berhasil',
];

$validated = SchemaValidator::validateAndNormalize($mockAiOutput, ['ev-01']);
assert(!empty($validated['fields']['notes']['value']), "Notes value should be present");
echo "Validated Notes: " . $validated['fields']['notes']['value'] . "\n";
assert(count($validated['fields']['classification']['candidates']) === 2, "Should have 2 candidates");
echo "Candidates count: " . count($validated['fields']['classification']['candidates']) . "\n";
foreach ($validated['fields']['classification']['candidates'] as $c) {
    echo "  - " . $c['class'] . " (" . $c['label'] . ")\n";
    echo "    Reasoning: " . $c['reasoning'] . "\n";
    assert(!empty($c['reasoning']), "Candidate should preserve reasoning for multi-subject");
}

// 5. Test PromptTemplates context filtering
echo "\n--- Testing Prompt Context Sources Filtering ---\n";
use AssistedAutocataloguing\Ai\PromptTemplates;

$evidence = [
    ['evidence_id' => 'ev-1', 'page_type' => 'title_page', 'ocr_text' => 'Judul Buku'],
    ['evidence_id' => 'ev-2', 'page_type' => 'toc', 'ocr_text' => 'Bab 1 Pendahuluan\nBab 2 Algoritma'],
];

// Test with TOC disabled
$promptNoToc = PromptTemplates::buildUserPrompt($evidence, null, ['title', 'abstract']);
assert(strpos($promptNoToc, 'Bab 2 Algoritma') === false, "TOC should be excluded when not in context sources");
assert(strpos($promptNoToc, 'Judul Buku & Subjudul') !== false, "Active context label should be present");

// Test with TOC enabled
$promptWithToc = PromptTemplates::buildUserPrompt($evidence, null, ['title', 'toc']);
assert(strpos($promptWithToc, 'Bab 2 Algoritma') !== false, "TOC should be included when enabled");

echo "Prompt context filtering verified successfully!\n";

echo "\nALL TESTS PASSED SUCCESSFULLY!\n";
