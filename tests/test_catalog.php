<?php
require_once __DIR__ . '/../src/Core/Application.php';
\AssistedAutocataloguing\Core\Application::registerAutoloader(dirname(__DIR__));

use AssistedAutocataloguing\Catalog\PerpusnasOneSearchAdapter;
use AssistedAutocataloguing\Catalog\IsbnValidator;

echo "--- Testing ISBN Validator ---\n";
$testIsbn = "978-602-03-0562-2";
$clean = IsbnValidator::clean($testIsbn);
$valid = IsbnValidator::isValid($testIsbn);
echo "Raw: $testIsbn\nClean: $clean\nValid: " . ($valid ? 'YES' : 'NO') . "\n\n";

echo "--- Testing Perpusnas OneSearch Adapter ---\n";
$adapter = new PerpusnasOneSearchAdapter();
$result = $adapter->searchByIsbn($testIsbn);

print_r($result);
