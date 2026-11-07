<?php
/**
 * End-to-End Automated Pipeline Test
 */

require_once __DIR__ . '/../src/Core/Application.php';
\AssistedAutocataloguing\Core\Application::registerAutoloader(dirname(__DIR__));

use AssistedAutocataloguing\Catalog\IsbnValidator;
use AssistedAutocataloguing\Catalog\PerpusnasOneSearchAdapter;
use AssistedAutocataloguing\Metadata\InternalBiblioModel;
use AssistedAutocataloguing\Metadata\Reconciler;
use AssistedAutocataloguing\Slims\MasterDataResolver;
use AssistedAutocataloguing\Slims\SlimsBibliographicMapper;
use AssistedAutocataloguing\Slims\SlimsPersistenceService;

echo "========================================================\n";
echo "   ASSISTED AUTOCATALOGUING - FULL INTEGRATION TEST\n";
echo "========================================================\n\n";

// 1. Test ISBN Validator
echo "[1] Testing ISBN Validator...\n";
$validIsbn10 = "6020305627";
$validIsbn13 = "9786020305622";
assert(IsbnValidator::isValid($validIsbn10), "ISBN-10 should be valid");
assert(IsbnValidator::isValid($validIsbn13), "ISBN-13 should be valid");
assert(!IsbnValidator::isValid("9781234567890"), "Invalid ISBN check digit should fail");
echo " -> PASS: ISBN-10 and ISBN-13 validation & checksum working correctly.\n\n";

// 2. Test Catalog Adapter
echo "[2] Testing Perpusnas OneSearch Adapter...\n";
$adapter = new PerpusnasOneSearchAdapter();
$catalogResult = $adapter->searchByIsbn($validIsbn13);
assert($catalogResult['found'] === true, "Perpusnas OneSearch should find the book");
assert(!empty($catalogResult['metadata']['title']), "Title should not be empty");
echo " -> PASS: Found book: '{$catalogResult['metadata']['title']}'\n";
echo "    Publisher: {$catalogResult['metadata']['publisher']}, Year: {$catalogResult['metadata']['publish_year']}\n\n";

// 3. Test Metadata Reconciliation
echo "[3] Testing Reconciler & Conflict Detection...\n";
$model = new InternalBiblioModel("test-session-001");
$reconciler = new Reconciler();

// Simulated OCR/AI metadata with a conflict in publication year (e.g. reprint in 2016)
$simulatedOcr = [
    'fields' => [
        'title' => [
            'value' => 'Belajar Bahasa Mandarin Seri Tingkat Pemula',
            'confidence' => 0.95,
            'evidence_ids' => ['evidence-test-01'],
        ],
        'publish_year' => [
            'value' => '2016', // Conflict: Perpusnas says 2014, physical book says 2016
            'confidence' => 0.90,
            'evidence_ids' => ['evidence-test-02'],
        ],
        'publisher' => [
            'value' => 'Gramedia Pustaka Utama',
            'confidence' => 0.92,
            'evidence_ids' => ['evidence-test-02'],
        ],
    ]
];

$reconciled = $reconciler->reconcile($catalogResult, $simulatedOcr, $model);
$fields = $reconciled->getAllFields();
$summary = $reconciler->getSummary($reconciled);

assert($fields['title']['status'] === 'candidate', "Title should match and have candidate status");
assert($fields['publish_year']['status'] === 'conflict', "Year difference (2014 vs 2016) must trigger 'conflict' status");
echo " -> PASS: Conflict detected correctly on year: '{$fields['publish_year']['conflict_reason']}'\n";
echo "    Summary: " . json_encode($summary) . "\n\n";

// 4. Test SLiMS Database Connection & Persistence
echo "[4] Testing SLiMS Database Mapping & Persistence...\n";
$pdo = new PDO("mysql:host=localhost;dbname=slims9;charset=utf8mb4", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$resolver = new MasterDataResolver($pdo);
$mapper = new SlimsBibliographicMapper($resolver);

// Librarian approves the conflict (accepts 2016 from physical book)
$reconciled->setFieldData('publish_year', [
    'selected_value' => '2016',
    'status' => 'verified',
    'conflict_reason' => null,
]);

// Map to SLiMS format
$mapped = $mapper->map($reconciled, 1);
assert($mapped['valid'] === true, "Mapped data must be valid");
echo " -> PASS: Successfully mapped to SLiMS schema:\n";
echo "    Title: {$mapped['biblio']['title']}\n";
echo "    Call Number Auto-Gen: {$mapped['biblio']['call_number']}\n";
echo "    Authors: " . count($mapped['authors']) . " relation(s)\n";

// Persist to SLiMS
$persistence = new SlimsPersistenceService($pdo);
// Check if already in DB from prior test
$existing = $persistence->findExistingByIsbn($validIsbn13);
if ($existing) {
    echo " -> NOTE: Record with ISBN {$validIsbn13} already exists in DB (Biblio ID {$existing['biblio_id']}). Duplicate check works!\n";
} else {
    $saveResult = $persistence->save($mapped, true);
    if (!$saveResult['success']) {
        echo "Save failed error: " . $saveResult['error'] . "\n";
    }
    assert($saveResult['success'] === true, "Saving to SLiMS should succeed");
    echo " -> PASS: Successfully saved new record into SLiMS! Biblio ID: {$saveResult['biblio_id']}\n";
}

echo "\n========================================================\n";
echo "   ALL TESTS PASSED SUCCESSFULLY!\n";
echo "========================================================\n";
