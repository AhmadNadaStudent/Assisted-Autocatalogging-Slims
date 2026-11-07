<?php
/**
 * Assisted Autocataloguing - AJAX API Endpoint
 */

// Initialize SLiMS session & configuration
if (!defined('INDEX_AUTH')) {
    define('INDEX_AUTH', '1');
}

// Locate SLiMS sysconfig if running inside SLiMS
$slimsRoot = defined('SB') ? SB : (file_exists('D:/laragon/www/slims9_bulian-9.8.0/sysconfig.inc.php') ? 'D:/laragon/www/slims9_bulian-9.8.0' : dirname(__DIR__, 2));
if (!defined('SB') && file_exists(rtrim($slimsRoot, '/\\') . '/sysconfig.inc.php')) {
    require_once rtrim($slimsRoot, '/\\') . '/sysconfig.inc.php';
}

if (defined('SB')) {
    $slimsRoot = SB;
    if (file_exists(SB . 'admin/default/session.inc.php')) {
        require_once SB . 'admin/default/session.inc.php';
    }
} else {
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
}

require_once __DIR__ . '/src/Core/Application.php';
\AssistedAutocataloguing\Core\Application::registerAutoloader(__DIR__);

use AssistedAutocataloguing\Core\Application;
use AssistedAutocataloguing\Core\Request;
use AssistedAutocataloguing\Core\Response;
use AssistedAutocataloguing\Core\Security;
use AssistedAutocataloguing\Catalog\CatalogManager;
use AssistedAutocataloguing\Catalog\IsbnValidator;
use AssistedAutocataloguing\Ocr\OcrService;
use AssistedAutocataloguing\Ai\AiService;
use AssistedAutocataloguing\Metadata\InternalBiblioModel;
use AssistedAutocataloguing\Metadata\Reconciler;
use AssistedAutocataloguing\Slims\MasterDataResolver;
use AssistedAutocataloguing\Slims\SlimsBibliographicMapper;
use AssistedAutocataloguing\Slims\SlimsPersistenceService;

$app = Application::getInstance(__DIR__);
$request = Request::createFromGlobals();

// Privilege check
if (!Security::checkReadPermission()) {
    Response::error('Akses ditolak. Anda tidak memiliki izin untuk modul bibliografi.', 403);
}

// Initialize session state
if (!isset($_SESSION['aac_state'])) {
    $_SESSION['aac_state'] = [
        'session_id' => bin2hex(random_bytes(8)),
        'isbn' => '',
        'external_metadata' => null,
        'evidence' => [],
        'ocr_metadata' => null,
        'model' => (new InternalBiblioModel())->toArray(),
    ];
}

$action = $request->get('action') ?? $request->post('action');

switch ($action) {
    case 'status':
        $catalogMgr = new CatalogManager($app);
        $ocrService = new OcrService($app);
        $aiService = new AiService($app);

        $aiStatus = $aiService->testConnection();
        $aiModel = $aiService->getActiveModelName();

        Response::success('Status sistem berhasil dimuat', [
            'catalog' => $catalogMgr->testProviders(),
            'ocr' => $ocrService->getEngineStatus(),
            'ai_mode' => $app->getConfig('ai.mode', 'local_only'),
            'ai_status' => $aiStatus,
            'ai_model' => $aiModel,
            'call_number_template' => $app->getConfig('slims.call_number_template', '{class} {author} {title}'),
            'classification_sources' => $app->getConfig('classification.context_sources', ['title', 'abstract', 'toc', 'subjects']),
            'session_id' => $_SESSION['aac_state']['session_id'],
            'evidence_count' => count($_SESSION['aac_state']['evidence']),
            'csrf_token' => Security::getCsrfToken(),
        ]);
        break;

    case 'search_isbn':
        $isbn = $request->post('isbn') ?? $request->get('isbn');
        if (empty($isbn)) {
            Response::error('ISBN tidak boleh kosong');
        }

        $cleanIsbn = IsbnValidator::clean($isbn);
        if (!IsbnValidator::isValid($cleanIsbn)) {
            Response::error('Format ISBN tidak valid (harus 10 atau 13 digit angka valid)');
        }

        $catalogMgr = new CatalogManager($app);
        $result = $catalogMgr->search($cleanIsbn);

        if (!$result['found']) {
            Response::error($result['error'] ?: 'Data tidak ditemukan di katalog Perpusnas', 404, [
                'source' => $result['source'] ?? 'Perpusnas',
            ]);
        }

        // Fetch Google Books preview & cover data
        $slimsDocsDir = (defined('IMGBS') ? IMGBS : (rtrim($slimsRoot, '/\\') . '/images/')) . 'docs';
        if (!is_dir($slimsDocsDir) && file_exists('D:/laragon/www/slims9_bulian-9.8.0/images/docs')) {
            $slimsDocsDir = 'D:/laragon/www/slims9_bulian-9.8.0/images/docs';
        }

        $gbooksService = new \AssistedAutocataloguing\Catalog\GoogleBooksService();
        $gbooksData = $gbooksService->getBookData($cleanIsbn, $slimsDocsDir);
        if (!empty($gbooksData['cover_file'])) {
            $_SESSION['aac_state']['cover_image'] = $gbooksData['cover_file'];
            $result['metadata']['cover_image'] = $gbooksData['cover_file'];
            $result['metadata']['image'] = $gbooksData['cover_file'];
        }
        if (!empty($gbooksData['description']) && empty($result['metadata']['notes'])) {
            $result['metadata']['notes'] = $gbooksData['description'];
        }
        $_SESSION['aac_state']['google_books'] = $gbooksData;

        $_SESSION['aac_state']['isbn'] = $cleanIsbn;
        $_SESSION['aac_state']['external_metadata'] = $result;

        // Run reconciliation with existing model & evidence
        $reconciler = new Reconciler();
        $model = new InternalBiblioModel($_SESSION['aac_state']['session_id']);
        // Restore field states
        foreach ($_SESSION['aac_state']['model']['fields'] as $k => $f) {
            $model->setFieldData($k, $f);
        }

        $reconciled = $reconciler->reconcile($result, $_SESSION['aac_state']['ocr_metadata'], $model);
        $_SESSION['aac_state']['model'] = $reconciled->toArray();

        Response::success('Metadata eksternal Perpusnas berhasil ditemukan', [
            'search_result' => $result,
            'google_books' => $gbooksData,
            'model' => $reconciled->toArray(),
            'summary' => $reconciler->getSummary($reconciled),
        ]);
        break;

    case 'upload_image':
        $file = $request->file('image');
        $pageType = $request->post('page_type', 'title_page');

        if (!$file) {
            Response::error('Tidak ada file gambar yang diunggah');
        }

        $ocrService = new OcrService($app);
        $uploadResult = $ocrService->processUpload($file, $pageType);

        if (!$uploadResult['success']) {
            Response::error($uploadResult['error'] ?: 'Gagal memproses unggahan gambar');
        }

        // Add to session evidence
        $_SESSION['aac_state']['evidence'][] = $uploadResult;

        Response::success('Gambar berhasil diunggah dan dianalisis OCR', [
            'evidence' => $uploadResult,
            'all_evidence' => $_SESSION['aac_state']['evidence'],
        ]);
        break;

    case 'run_ai_extraction':
        $evidence = $_SESSION['aac_state']['evidence'] ?? [];
        if (empty($evidence)) {
            Response::error('Belum ada gambar bukti fisik buku yang diunggah untuk diekstraksi');
        }

        $aiService = new AiService($app);
        $externalData = $_SESSION['aac_state']['external_metadata'] ?? null;

        $extraction = $aiService->extractMetadata($evidence, $externalData);
        $_SESSION['aac_state']['ocr_metadata'] = $extraction;

        // Reconcile
        $reconciler = new Reconciler();
        $model = new InternalBiblioModel($_SESSION['aac_state']['session_id']);
        foreach ($_SESSION['aac_state']['model']['fields'] as $k => $f) {
            $model->setFieldData($k, $f);
        }

        $reconciled = $reconciler->reconcile($externalData, $extraction, $model);
        $_SESSION['aac_state']['model'] = $reconciled->toArray();

        Response::success('Ekstraksi metadata dari bukti fisik selesai', [
            'extraction' => $extraction,
            'model' => $reconciled->toArray(),
            'summary' => $reconciler->getSummary($reconciled),
        ]);
        break;

    case 'ai_classify':
        $aiService = new AiService($app);
        $currentModel = $_SESSION['aac_state']['model'] ?? null;
        $fields = $currentModel['fields'] ?? [];
        $ext = $_SESSION['aac_state']['external_metadata']['metadata'] ?? [];

        $metaToClassify = [
            'title' => $fields['title']['selected_value'] ?? ($fields['title']['external_value'] ?? ($ext['title'] ?? '')),
            'authors' => $fields['authors']['selected_value'] ?? ($fields['authors']['external_value'] ?? ($ext['authors'] ?? [])),
            'notes' => $fields['notes']['selected_value'] ?? ($fields['notes']['external_value'] ?? ($ext['notes'] ?? '')),
            'subjects' => $fields['subjects']['selected_value'] ?? ($fields['subjects']['external_value'] ?? ($ext['subjects'] ?? [])),
            'publisher' => $fields['publisher']['selected_value'] ?? ($fields['publisher']['external_value'] ?? ($ext['publisher'] ?? '')),
            'publish_year' => $fields['publish_year']['selected_value'] ?? ($fields['publish_year']['external_value'] ?? ($ext['publish_year'] ?? '')),
        ];

        $res = $aiService->classifyFromMetadata($metaToClassify);
        if (!$res['success']) {
            Response::error($res['error'] ?: 'Gagal menganalisis klasifikasi dengan AI');
        }

        // Attach candidates to session model
        if (!empty($res['candidates'])) {
            $existingCandidates = $_SESSION['aac_state']['model']['fields']['classification']['candidates'] ?? [];
            foreach ($res['candidates'] as $nc) {
                $found = false;
                foreach ($existingCandidates as $ec) {
                    if ($ec['class'] === $nc['class']) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $existingCandidates[] = $nc;
                }
            }
            $_SESSION['aac_state']['model']['fields']['classification']['candidates'] = $existingCandidates;
            if (empty($_SESSION['aac_state']['model']['fields']['classification']['selected_value']) && !empty($res['classification'])) {
                $_SESSION['aac_state']['model']['fields']['classification']['selected_value'] = $res['classification'];
                $_SESSION['aac_state']['model']['fields']['classification']['status'] = 'candidate';
            }
            if (empty($_SESSION['aac_state']['model']['fields']['call_number']['selected_value']) && !empty($res['call_number'])) {
                $_SESSION['aac_state']['model']['fields']['call_number']['selected_value'] = $res['call_number'];
                $_SESSION['aac_state']['model']['fields']['call_number']['status'] = 'candidate';
            }
        }

        // Recompute summary
        $reconciler = new Reconciler();
        $model = new InternalBiblioModel($_SESSION['aac_state']['session_id']);
        foreach ($_SESSION['aac_state']['model']['fields'] as $k => $f) {
            $model->setFieldData($k, $f);
        }

        Response::success('Klasifikasi AI berhasil dianalisis', [
            'result' => $res,
            'model' => $_SESSION['aac_state']['model'],
            'summary' => $reconciler->getSummary($model),
        ]);
        break;

    case 'update_field':
        $key = $request->post('field');
        $value = $request->post('value');
        $status = $request->post('status', 'verified');

        if (empty($key) || !isset($_SESSION['aac_state']['model']['fields'][$key])) {
            Response::error('Field tidak ditemukan');
        }

        $fields = &$_SESSION['aac_state']['model']['fields'];
        $fields[$key]['selected_value'] = $value;
        $fields[$key]['status'] = $status;
        $fields[$key]['user_modified'] = true;

        if ($status === 'verified') {
            $fields[$key]['conflict_reason'] = null;
        }

        // Recompute summary
        $model = new InternalBiblioModel($_SESSION['aac_state']['session_id']);
        foreach ($fields as $k => $f) {
            $model->setFieldData($k, $f);
        }
        $reconciler = new Reconciler();

        Response::success('Field berhasil diperbarui oleh pustakawan', [
            'field' => $fields[$key],
            'summary' => $reconciler->getSummary($model),
        ]);
        break;

    case 'save_to_slims':
        if (!Security::checkWritePermission()) {
            Response::error('Anda tidak memiliki izin untuk menyimpan data bibliografi', 403);
        }

        // Validate CSRF
        $token = $request->post('csrf_token') ?? $request->getHeader('X-CSRF-Token');
        if (!Security::validateCsrfToken($token)) {
            Response::error('Validasi token CSRF gagal. Silakan muat ulang halaman.', 403);
        }

        // Get DB connection from SLiMS
        $pdo = null;
        if (class_exists('\\SLiMS\\DB')) {
            $pdo = \SLiMS\DB::getInstance('pdo');
        } else {
            // Standalone fallback
            $dbCfg = require $slimsRoot . '/config/database.php';
            $node = $dbCfg['nodes'][$dbCfg['default_profile']];
            $dsn = "mysql:host={$node['host']};port={$node['port']};dbname={$node['database']};charset=utf8mb4";
            $pdo = new PDO($dsn, $node['username'], $node['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
        }

        $dbs = $GLOBALS['dbs'] ?? null;

        $resolver = new MasterDataResolver($pdo);
        $mapper = new SlimsBibliographicMapper($resolver, $app->getConfig('slims', []));

        $model = new InternalBiblioModel($_SESSION['aac_state']['session_id']);
        foreach ($_SESSION['aac_state']['model']['fields'] as $k => $f) {
            $model->setFieldData($k, $f);
        }

        $mapped = $mapper->map($model, $_SESSION['uid'] ?? 1);
        if (!$mapped['valid']) {
            Response::error($mapped['error']);
        }

        // Attach seamless cover image if biblio image is empty
        if (empty($mapped['biblio']['image']) && !empty($_SESSION['aac_state']['cover_image'])) {
            $mapped['biblio']['image'] = $_SESSION['aac_state']['cover_image'];
        }

        $persistence = new SlimsPersistenceService($pdo, $dbs);
        $saveResult = $persistence->save($mapped, $app->getConfig('slims.duplicate_check_by_isbn', true));

        if (!$saveResult['success']) {
            Response::error($saveResult['error']);
        }

        // Clear session on successful save
        unset($_SESSION['aac_state']);

        Response::success($saveResult['message'], [
            'biblio_id' => $saveResult['biblio_id'],
            'title' => $saveResult['title'],
        ]);
        break;

    case 'reset_session':
        unset($_SESSION['aac_state']);
        Response::success('Sesi katalogisasi berhasil di-reset');
        break;

    case 'get_settings':
        $aiCfg = $app->getConfig('ai', []);
        $ocrCfg = $app->getConfig('ocr', []);
        $catalogCfg = $app->getConfig('catalog', []);

        $rawKey = $aiCfg['providers']['openai_compatible']['api_key'] ?? '';
        $maskedKey = !empty($rawKey) ? (substr($rawKey, 0, 4) . '••••••••' . substr($rawKey, -4)) : '';

        Response::success('Pengaturan sistem', [
            'ai' => [
                'mode' => $aiCfg['mode'] ?? 'local_only',
                'default_provider' => $aiCfg['default_provider'] ?? 'ollama',
                'ollama' => [
                    'base_url' => $aiCfg['providers']['ollama']['base_url'] ?? 'http://localhost:11434',
                    'model' => $aiCfg['providers']['ollama']['model'] ?? 'llama3.2',
                    'timeout' => $aiCfg['providers']['ollama']['timeout'] ?? 60,
                ],
                'openai_compatible' => [
                    'base_url' => $aiCfg['providers']['openai_compatible']['base_url'] ?? 'https://api.openai.com/v1',
                    'api_key' => $maskedKey,
                    'has_key' => !empty($rawKey),
                    'model' => $aiCfg['providers']['openai_compatible']['model'] ?? 'gpt-4o-mini',
                    'timeout' => $aiCfg['providers']['openai_compatible']['timeout'] ?? 45,
                ],
            ],
            'ocr' => [
                'driver' => $ocrCfg['driver'] ?? 'auto',
                'tesseract_binary' => $ocrCfg['tesseract_binary'] ?? 'tesseract',
                'python_binary' => $ocrCfg['python_binary'] ?? 'python',
                'languages' => $ocrCfg['languages'] ?? 'ind+eng',
            ],
            'catalog' => [
                'default_provider' => $catalogCfg['default_provider'] ?? 'onesearch',
            ],
            'slims' => [
                'call_number_template' => $app->getConfig('slims.call_number_template', '{class} {author} {title}'),
            ],
            'classification' => [
                'context_sources' => $app->getConfig('classification.context_sources', ['title', 'abstract', 'toc', 'subjects']),
            ],
        ]);
        break;

    case 'save_settings':
        if (!Security::checkWritePermission()) {
            Response::error('Anda tidak memiliki izin untuk mengubah konfigurasi', 403);
        }

        $token = $request->post('csrf_token') ?? $request->getHeader('X-CSRF-Token');
        if (!Security::validateCsrfToken($token)) {
            Response::error('Validasi token CSRF gagal. Silakan muat ulang halaman.', 403);
        }

        $configPath = $app->getBasePath() . '/config/config.php';
        $currentConfig = [];
        if (file_exists($configPath)) {
            $currentConfig = require $configPath;
        }

        $aiMode = $request->post('ai_mode', 'local_only');
        $aiProvider = $request->post('ai_default_provider', 'ollama');

        $ollamaUrl = $request->post('ollama_base_url', 'http://localhost:11434');
        $ollamaModel = $request->post('ollama_model', 'llama3.2');
        $ollamaTimeout = (int)$request->post('ollama_timeout', 60);

        $cloudUrl = $request->post('cloud_base_url', 'https://api.openai.com/v1');
        $cloudKeyInput = $request->post('cloud_api_key', '');
        $cloudModel = $request->post('cloud_model', 'gpt-4o-mini');
        $cloudTimeout = (int)$request->post('cloud_timeout', 45);

        $oldKey = $currentConfig['ai']['providers']['openai_compatible']['api_key'] ?? $app->getConfig('ai.providers.openai_compatible.api_key', '');
        $finalKey = (empty($cloudKeyInput) || str_contains($cloudKeyInput, '••••')) ? $oldKey : trim($cloudKeyInput);

        $ocrDriver = $request->post('ocr_driver', 'auto');
        $tesseractBin = $request->post('ocr_tesseract_binary', 'tesseract');
        $pythonBin = $request->post('ocr_python_binary', 'python');
        $languages = $request->post('ocr_languages', 'ind+eng');

        $catalogProvider = $request->post('catalog_default_provider', 'onesearch');
        $callNumberTemplate = $request->post('call_number_template', '{class} {author} {title}');

        $rawClassSources = $request->post('classification_sources');
        $classSources = ['title', 'abstract', 'toc', 'subjects'];
        if (is_array($rawClassSources)) {
            $classSources = array_values(array_filter($rawClassSources));
        } elseif (is_string($rawClassSources)) {
            $classSources = array_values(array_filter(array_map('trim', explode(',', $rawClassSources))));
        }
        if (empty($classSources)) {
            $classSources = ['title', 'abstract', 'toc', 'subjects'];
        }

        $newConfig = array_replace_recursive($currentConfig, [
            'ai' => [
                'mode' => $aiMode,
                'default_provider' => $aiProvider,
                'providers' => [
                    'ollama' => [
                        'name' => 'Ollama (Lokal)',
                        'base_url' => trim($ollamaUrl),
                        'model' => trim($ollamaModel),
                        'timeout' => $ollamaTimeout ?: 60,
                    ],
                    'openai_compatible' => [
                        'name' => 'Cloud AI (OpenAI / OpenRouter / Gemini API)',
                        'base_url' => trim($cloudUrl),
                        'api_key' => $finalKey,
                        'model' => trim($cloudModel),
                        'timeout' => $cloudTimeout ?: 45,
                    ],
                ],
            ],
            'ocr' => [
                'driver' => $ocrDriver,
                'tesseract_binary' => trim($tesseractBin),
                'python_binary' => trim($pythonBin),
                'languages' => trim($languages),
            ],
            'catalog' => [
                'default_provider' => $catalogProvider,
            ],
            'slims' => [
                'call_number_template' => trim($callNumberTemplate) ?: '{class} {author} {title}',
            ],
            'classification' => [
                'context_sources' => $classSources,
            ],
        ]);

        $exported = "<?php\n/**\n * Assisted Autocataloguing - Active Configuration\n * Generated automatically from Settings UI.\n */\n\nreturn " . var_export($newConfig, true) . ";\n";
        
        $saved = @file_put_contents($configPath, $exported);
        if ($saved === false) {
            Response::error('Gagal menulis file konfigurasi config/config.php. Pastikan folder memiliki izin tulis.');
        }

        Response::success('Pengaturan berhasil disimpan!', [
            'config' => $newConfig,
        ]);
        break;

    case 'test_ai':
        $providerType = $request->post('provider', 'ollama');

        if ($providerType === 'ollama') {
            $baseUrl = $request->post('base_url', 'http://localhost:11434');
            $adapter = new \AssistedAutocataloguing\Ai\OllamaAdapter($baseUrl);
            $testRes = $adapter->testConnection();
            Response::success('Hasil tes Ollama', $testRes);
        } else {
            $baseUrl = $request->post('base_url', 'https://api.openai.com/v1');
            $apiKey = $request->post('api_key', '');
            if (empty($apiKey) || str_contains($apiKey, '••••')) {
                $apiKey = $app->getConfig('ai.providers.openai_compatible.api_key', '');
            }
            $model = $request->post('model', 'gpt-4o-mini');
            $adapter = new \AssistedAutocataloguing\Ai\OpenAiCompatibleAdapter($baseUrl, $apiKey, $model);
            $testRes = $adapter->testConnection();
            Response::success('Hasil tes Cloud AI', $testRes);
        }
        break;

    case 'delete_evidence':
        $evidenceId = $request->post('evidence_id');
        if (empty($evidenceId) || empty($_SESSION['aac_state']['evidence'])) {
            Response::error('ID bukti tidak valid');
        }

        $allEvidence = &$_SESSION['aac_state']['evidence'];
        $found = false;
        $uploadDir = $app->getStoragePath('uploads');
        $thumbDir = $app->getStoragePath('uploads/thumbs');

        foreach ($allEvidence as $idx => $ev) {
            if (($ev['evidence_id'] ?? '') === $evidenceId) {
                if (!empty($ev['file_name']) && file_exists($uploadDir . '/' . $ev['file_name'])) {
                    @unlink($uploadDir . '/' . $ev['file_name']);
                }
                if (!empty($ev['thumb_name']) && file_exists($thumbDir . '/' . $ev['thumb_name'])) {
                    @unlink($thumbDir . '/' . $ev['thumb_name']);
                }
                unset($allEvidence[$idx]);
                $found = true;
                break;
            }
        }
        $allEvidence = array_values($allEvidence);

        if (!$found) {
            Response::error('Bukti fisik tidak ditemukan dalam sesi');
        }

        Response::success('Bukti fisik berhasil dihapus', [
            'all_evidence' => $allEvidence,
        ]);
        break;

    default:
        Response::error('Aksi tidak dikenal', 400);
}
