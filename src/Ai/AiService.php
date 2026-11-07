<?php
namespace AssistedAutocataloguing\Ai;

use AssistedAutocataloguing\Catalog\IsbnValidator;
use AssistedAutocataloguing\Core\Application;

/**
 * AI Orchestrator & Privacy Guard
 */
class AiService
{
    private Application $app;
    private array $providers = [];
    private string $mode;
    private string $defaultProvider;

    public function __construct(?Application $app = null)
    {
        $this->app = $app ?? Application::getInstance();
        $aiConfig = $this->app->getConfig('ai', []);

        $this->mode = $aiConfig['mode'] ?? 'local_only';
        $this->defaultProvider = $aiConfig['default_provider'] ?? 'ollama';

        // Initialize Ollama
        $ollamaCfg = $aiConfig['providers']['ollama'] ?? [];
        $this->providers['ollama'] = new OllamaAdapter(
            $ollamaCfg['base_url'] ?? 'http://localhost:11434',
            $ollamaCfg['model'] ?? 'llama3.2',
            $ollamaCfg['timeout'] ?? 60
        );

        // Initialize OpenAI-compatible
        $cloudCfg = $aiConfig['providers']['openai_compatible'] ?? [];
        $this->providers['openai_compatible'] = new OpenAiCompatibleAdapter(
            $cloudCfg['base_url'] ?? 'https://api.openai.com/v1',
            $cloudCfg['api_key'] ?? '',
            $cloudCfg['model'] ?? 'gpt-4o-mini',
            $cloudCfg['timeout'] ?? 45
        );
    }

    public function extractMetadata(array $evidenceItems, ?array $externalMetadata = null): array
    {
        $validEvidenceIds = array_column($evidenceItems, 'evidence_id');

        // Check if AI is completely disabled
        if ($this->mode === 'disabled') {
            return $this->extractViaRuleBasedFallback($evidenceItems, $validEvidenceIds);
        }

        $providerKey = $this->defaultProvider;

        // Privacy check: If mode is local_only, never allow cloud provider
        if ($this->mode === 'local_only' && $providerKey !== 'ollama') {
            $providerKey = 'ollama';
        }

        $provider = $this->providers[$providerKey] ?? $this->providers['ollama'];

        // If trying to use cloud in local_only mode, block it
        if ($this->mode === 'local_only' && !$provider->isLocal()) {
            return [
                'success' => false,
                'error' => 'Mode privasi disetel ke "local_only". Data tidak diizinkan dikirim ke provider AI cloud.',
                'result' => $this->extractViaRuleBasedFallback($evidenceItems, $validEvidenceIds),
            ];
        }

        $contextSources = $this->app->getConfig('classification.context_sources', ['title', 'abstract', 'toc', 'subjects']);
        $systemPrompt = PromptTemplates::getSystemPrompt();
        $userPrompt = PromptTemplates::buildUserPrompt($evidenceItems, $externalMetadata, $contextSources);

        $completion = $provider->complete($systemPrompt, $userPrompt);

        if ($completion['success'] && is_array($completion['parsed_json'])) {
            $normalized = SchemaValidator::validateAndNormalize($completion['parsed_json'], $validEvidenceIds);
            return [
                'success' => true,
                'provider' => $provider->getName(),
                'fields' => $normalized['fields'],
                'notes' => $normalized['notes'],
                'error' => null,
            ];
        }

        // Fallback to rule-based parser if AI inference fails
        $fallback = $this->extractViaRuleBasedFallback($evidenceItems, $validEvidenceIds);
        $fallback['notes'] .= ' (AI ' . $provider->getLabel() . ' tidak merespons: ' . ($completion['error'] ?? 'Gagal') . ')';

        return [
            'success' => true,
            'provider' => 'rule_based_fallback',
            'fields' => $fallback['fields'],
            'notes' => $fallback['notes'],
            'error' => $completion['error'],
        ];
    }

    /**
     * Rule-based extraction using regular expressions and heuristics
     */
    public function extractViaRuleBasedFallback(array $evidenceItems, array $evidenceIds): array
    {
        $combinedText = '';
        foreach ($evidenceItems as $item) {
            $combinedText .= "\n" . ($item['ocr_text'] ?? '');
        }

        $fields = [];

        // 1. ISBN extraction
        $isbn = null;
        if (preg_match('/(?:ISBN(?:-1[03])?:?\s*)([0-9Xx\-]{10,17})/', $combinedText, $m)) {
            $cand = IsbnValidator::clean($m[1]);
            if (IsbnValidator::isValid($cand)) {
                $isbn = $cand;
            }
        }
        $fields['isbn'] = [
            'value' => $isbn,
            'status' => $isbn ? 'candidate' : 'not_stated_in_examined_sources',
            'confidence' => $isbn ? 0.9 : 0.0,
            'source_type' => 'ocr',
            'evidence_ids' => $evidenceIds,
            'notes' => $isbn ? 'Ditemukan nomor ISBN pada teks OCR' : 'ISBN tidak terbaca pada gambar',
        ];

        // 2. Year extraction (look for (c) or Hak Cipta or Cetakan followed by 19xx/20xx)
        $year = null;
        if (preg_match('/(?:copyright|\(c\)|hak cipta|tahun terbit|cetakan[^0-9\n]*)\s*([12][0-9]{3})/i', $combinedText, $m)) {
            $year = $m[1];
        } elseif (preg_match('/\b(20[0-2][0-9]|19[89][0-9])\b/', $combinedText, $m)) {
            $year = $m[1];
        }
        $fields['publish_year'] = [
            'value' => $year,
            'status' => $year ? 'candidate' : 'not_stated_in_examined_sources',
            'confidence' => $year ? 0.8 : 0.0,
            'source_type' => 'ocr',
            'evidence_ids' => $evidenceIds,
            'notes' => $year ? 'Ditemukan tahun terbit pada teks' : 'Tahun terbit tidak terdeteksi',
        ];

        // 3. Collation / Pagination (e.g. "xx, 250 hlm" or "230 hlm")
        $collation = null;
        if (preg_match('/([0-9ivxlcdm\s,]+\s*hlm(?:[^\n\.,]*\bcm\b)?)/i', $combinedText, $m)) {
            $collation = trim($m[1]);
        }
        $fields['collation'] = [
            'value' => $collation,
            'status' => $collation ? 'candidate' : 'not_stated_in_examined_sources',
            'confidence' => $collation ? 0.8 : 0.0,
            'source_type' => 'ocr',
            'evidence_ids' => $evidenceIds,
            'notes' => $collation ? 'Keterangan halaman terdeteksi' : 'Keterangan halaman tidak ditemukan',
        ];

        // 4. Abstract / Synopsis extraction (especially if page is back_cover / synopsis or contains keywords)
        $extractedNotes = null;
        foreach ($evidenceItems as $item) {
            $pageType = $item['page_type'] ?? '';
            $ocr = trim($item['ocr_text'] ?? '');
            if (($pageType === 'back_cover' || $pageType === 'synopsis') && !empty($ocr)) {
                $extractedNotes = $ocr;
                break;
            }
        }
        if (!$extractedNotes && preg_match('/(?:sinopsis|ringkasan|tentang buku ini)\s*[:\n\-]+(.*?)(?:\n\n[A-Z]{3,}|\z)/is', $combinedText, $m)) {
            $extractedNotes = trim($m[1]);
        }
        $fields['notes'] = [
            'value' => $extractedNotes,
            'status' => $extractedNotes ? 'candidate' : 'not_stated_in_examined_sources',
            'confidence' => $extractedNotes ? 0.75 : 0.0,
            'source_type' => 'ocr',
            'evidence_ids' => $evidenceIds,
            'notes' => $extractedNotes ? 'Ditemukan ringkasan/sinopsis dari teks OCR' : 'Sinopsis tidak ditemukan pada gambar',
        ];

        // Default empty for other fields to prevent guessing
        $blankFields = ['title', 'sor', 'publisher', 'publish_place', 'edition', 'series_title', 'call_number'];
        foreach ($blankFields as $bf) {
            $fields[$bf] = [
                'value' => null,
                'status' => 'not_stated_in_examined_sources',
                'confidence' => 0.0,
                'source_type' => 'ocr',
                'evidence_ids' => $evidenceIds,
                'notes' => 'Memerlukan pemeriksaan manual pustakawan',
            ];
        }

        $fields['classification'] = [
            'value' => null,
            'candidates' => [],
            'status' => 'not_stated_in_examined_sources',
            'confidence' => 0.0,
            'source_type' => 'ocr',
            'evidence_ids' => $evidenceIds,
            'notes' => 'Nomor klasifikasi belum ditentukan',
        ];

        $fields['authors'] = [
            'value' => [],
            'status' => 'not_stated_in_examined_sources',
            'confidence' => 0.0,
            'source_type' => 'ocr',
            'evidence_ids' => $evidenceIds,
            'notes' => 'Nama pengarang belum diekstrak',
        ];

        $fields['subjects'] = [
            'value' => [],
            'status' => 'not_stated_in_examined_sources',
            'confidence' => 0.0,
            'source_type' => 'ocr',
            'evidence_ids' => $evidenceIds,
            'notes' => 'Subjek belum ditentukan',
        ];

        return [
            'fields' => $fields,
            'notes' => 'Ekstraksi berbasis aturan heuristik dari teks OCR',
        ];
    }

    public function testConnection(): array
    {
        if ($this->mode === 'disabled') {
            return ['ok' => false, 'message' => 'AI dinonaktifkan di pengaturan'];
        }
        $providerKey = ($this->mode === 'local_only') ? 'ollama' : $this->defaultProvider;
        $provider = $this->providers[$providerKey] ?? $this->providers['ollama'];
        return $provider->testConnection();
    }

    public function getActiveModelName(): string
    {
        if ($this->mode === 'disabled') {
            return 'Disabled';
        }
        $providerKey = ($this->mode === 'local_only') ? 'ollama' : $this->defaultProvider;
        if ($providerKey === 'ollama') {
            return $this->app->getConfig('ai.providers.ollama.model', 'llama3.2');
        } else {
            return $this->app->getConfig('ai.providers.openai_compatible.model', 'gpt-4o-mini');
        }
    }

    public function classifyFromMetadata(array $metadata): array
    {
        if ($this->mode === 'disabled') {
            return [
                'success' => false,
                'error' => 'AI dinonaktifkan di pengaturan',
                'classification' => null,
                'candidates' => [],
            ];
        }

        $providerKey = ($this->mode === 'local_only') ? 'ollama' : $this->defaultProvider;
        $provider = $this->providers[$providerKey] ?? $this->providers['ollama'];

        $contextSources = $this->app->getConfig('classification.context_sources', ['title', 'abstract', 'toc', 'subjects']);

        // Build focused metadata payload based on allowed context sources
        $payload = [];
        if (in_array('title', $contextSources) && !empty($metadata['title'])) {
            $payload['title'] = $metadata['title'];
        }
        if (!empty($metadata['authors'])) {
            $payload['authors'] = $metadata['authors'];
        }
        if (in_array('abstract', $contextSources) && !empty($metadata['notes'])) {
            $payload['abstract'] = $metadata['notes'];
        }
        if (in_array('subjects', $contextSources) && !empty($metadata['subjects'])) {
            $payload['subjects'] = $metadata['subjects'];
        }
        if (!empty($metadata['publisher'])) {
            $payload['publisher'] = $metadata['publisher'];
        }
        if (!empty($metadata['publish_year'])) {
            $payload['publish_year'] = $metadata['publish_year'];
        }

        $systemPrompt = <<<SYS
Anda adalah ahli klasifikasi perpustakaan berstandar DDC (Dewey Decimal Classification) dan Perpustakaan Nasional RI (Perpusnas).
Tugas Anda adalah menganalisis data bibliografi buku dan menentukan nomor klasifikasi DDC yang paling tepat serta nomor panggil perpustakaan.

ATURAN KLASIFIKASI & MULTI-SUBJEK:
1. Analisis judul, pengarang, subjek, dan terutama abstrak/sinopsis buku.
2. Jika buku membahas 2 atau lebih subjek/disiplin yang berbeda atau interdisipliner:
   - Wajib berikan opsi kandidat klasifikasi untuk masing-masing sudut pandang disiplin ilmu pada `classification_candidates`.
   - Sertakan `reasoning` (alasan mengapa pustakawan memilih kelas tersebut).
3. Hasilkan nomor panggil standar Perpusnas: [Nomor DDC] [3 huruf kapital pengarang] [1 huruf kecil judul].
4. Output WAJIB dalam format JSON murni:
{
  "classification": "006.3",
  "classification_candidates": [
    {
      "class": "006.3",
      "label": "Kecerdasan Buatan (AI)",
      "reasoning": "Pilihan tepat jika perpustakaan ingin menekankan aspek teknologi, metode, dan algoritma AI.",
      "confidence": 0.95
    },
    {
      "class": "610.28",
      "label": "Teknologi Medis & Biomedika",
      "reasoning": "Pilihan tepat jika fokus koleksi adalah aplikasi praktis pada dunia kedokteran dan klinis.",
      "confidence": 0.88
    }
  ],
  "call_number": "006.3 FAZ a"
}
SYS;

        $userPrompt = "Analisis buku berikut dan tentukan klasifikasi DDC:\n" . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $completion = $provider->complete($systemPrompt, $userPrompt);

        if ($completion['success'] && is_array($completion['parsed_json'])) {
            $json = $completion['parsed_json'];
            $class = trim((string)($json['classification'] ?? ''));
            $candidates = [];
            $rawCand = $json['classification_candidates'] ?? [];
            if (is_array($rawCand)) {
                foreach ($rawCand as $c) {
                    if (is_array($c) && !empty($c['class'])) {
                        $candidates[] = [
                            'class' => trim((string)$c['class']),
                            'label' => trim((string)($c['label'] ?? '')),
                            'reasoning' => trim((string)($c['reasoning'] ?? '')),
                            'source' => 'ai',
                            'confidence' => isset($c['confidence']) ? (float)$c['confidence'] : 0.85,
                        ];
                    }
                }
            }
            if (!empty($class) && empty($candidates)) {
                $candidates[] = [
                    'class' => $class,
                    'label' => 'Rekomendasi Utama AI',
                    'reasoning' => 'Hasil analisis metadata buku',
                    'source' => 'ai',
                    'confidence' => 0.9,
                ];
            }

            return [
                'success' => true,
                'classification' => $class ?: ($candidates[0]['class'] ?? null),
                'candidates' => $candidates,
                'call_number' => $json['call_number'] ?? null,
                'error' => null,
            ];
        }

        return [
            'success' => false,
            'classification' => null,
            'candidates' => [],
            'call_number' => null,
            'error' => $completion['error'] ?? 'Gagal memproses klasifikasi AI',
        ];
    }
}
