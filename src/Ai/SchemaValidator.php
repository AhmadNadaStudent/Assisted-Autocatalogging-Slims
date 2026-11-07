<?php
namespace AssistedAutocataloguing\Ai;

/**
 * Validates and Normalizes AI Output Against Internal Schema
 */
class SchemaValidator
{
    private static array $allowedFields = [
        'title', 'sor', 'authors', 'publisher', 'publish_place',
        'publish_year', 'edition', 'isbn', 'collation', 'series_title', 'subjects',
        'notes', 'classification', 'call_number', 'image',
    ];

    public static function validateAndNormalize(array $aiJson, array $validEvidenceIds): array
    {
        $normalized = [];
        $rawFields = (!empty($aiJson['fields']) && is_array($aiJson['fields'])) ? $aiJson['fields'] : $aiJson;

        foreach (self::$allowedFields as $field) {
            if ($field === 'authors') {
                $authorList = [];
                $authorsRaw = $rawFields['authors'] ?? [];
                if (is_array($authorsRaw)) {
                    foreach ($authorsRaw as $a) {
                        if (is_string($a) && !empty(trim($a))) {
                            $authorList[] = trim($a);
                        } elseif (is_array($a) && !empty($a['name'])) {
                            $authorList[] = trim($a['name']);
                        }
                    }
                }
                $hasValue = !empty($authorList);
                $normalized['authors'] = [
                    'value' => $authorList,
                    'status' => $hasValue ? 'candidate' : 'not_stated_in_examined_sources',
                    'confidence' => $hasValue ? 0.85 : 0.0,
                    'source_type' => 'ocr_ai',
                    'evidence_ids' => $validEvidenceIds,
                    'notes' => $hasValue ? 'Ekstraksi nama pengarang dari bukti OCR' : 'Tidak ditemukan nama pengarang',
                ];
                continue;
            }

            if ($field === 'subjects') {
                $subjList = [];
                $subjRaw = $rawFields['subjects'] ?? [];
                if (is_array($subjRaw)) {
                    foreach ($subjRaw as $s) {
                        if (is_string($s) && !empty(trim($s))) {
                            $subjList[] = trim($s);
                        }
                    }
                }
                $hasValue = !empty($subjList);
                $normalized['subjects'] = [
                    'value' => $subjList,
                    'status' => $hasValue ? 'candidate' : 'not_stated_in_examined_sources',
                    'confidence' => $hasValue ? 0.8 : 0.0,
                    'source_type' => 'ocr_ai',
                    'evidence_ids' => $validEvidenceIds,
                    'notes' => $hasValue ? 'Ekstraksi subjek dari bukti OCR' : 'Subjek tidak tercantum pada bukti',
                ];
                continue;
            }

            $fieldData = $rawFields[$field] ?? null;
            $val = null;
            $confidence = 0.0;
            $snippet = null;

            if (is_array($fieldData)) {
                $val = !empty($fieldData['value']) ? trim((string)$fieldData['value']) : null;
                $confidence = isset($fieldData['confidence']) ? (float)$fieldData['confidence'] : 0.8;
                $snippet = $fieldData['snippet'] ?? null;
            } elseif (is_string($fieldData) && !empty(trim($fieldData))) {
                $val = trim($fieldData);
                $confidence = 0.8;
            } elseif (is_numeric($fieldData)) {
                $val = (string)$fieldData;
                $confidence = 0.8;
            }

            $hasValue = !empty($val);
            $fieldInfo = [
                'value' => $val,
                'status' => $hasValue ? 'candidate' : 'not_stated_in_examined_sources',
                'confidence' => $hasValue ? min(max($confidence, 0.1), 0.99) : 0.0,
                'source_type' => 'ocr_ai',
                'evidence_ids' => $validEvidenceIds,
                'snippet' => $snippet,
                'notes' => $hasValue ? ('Teks bukti: ' . ($snippet ?: 'ditemukan di OCR')) : 'Tidak ditemukan pada halaman bukti',
            ];

            // Normalize multiple classification candidates
            if ($field === 'classification') {
                $candidates = [];
                $rawCandidates = $rawFields['classification_candidates'] ?? ($fieldData['candidates'] ?? []);
                if (is_array($rawCandidates)) {
                    foreach ($rawCandidates as $cand) {
                        if (is_string($cand) && !empty(trim($cand))) {
                            $candidates[] = ['class' => trim($cand), 'label' => '', 'reasoning' => '', 'confidence' => 0.8];
                        } elseif (is_array($cand) && !empty($cand['class'])) {
                            $candidates[] = [
                                'class' => trim((string)$cand['class']),
                                'label' => trim((string)($cand['label'] ?? '')),
                                'reasoning' => trim((string)($cand['reasoning'] ?? '')),
                                'confidence' => isset($cand['confidence']) ? (float)$cand['confidence'] : 0.8,
                            ];
                        }
                    }
                }
                // If primary val is set, make sure it is represented in candidates
                if (!empty($val)) {
                    $found = false;
                    foreach ($candidates as $c) {
                        if ($c['class'] === $val) {
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) {
                        array_unshift($candidates, ['class' => $val, 'label' => 'Rekomendasi Utama AI', 'reasoning' => 'Kandidat utama hasil inferensi AI', 'confidence' => $confidence ?: 0.9]);
                    }
                } elseif (!empty($candidates[0]['class'])) {
                    $fieldInfo['value'] = $candidates[0]['class'];
                    $fieldInfo['status'] = 'candidate';
                    $fieldInfo['confidence'] = $candidates[0]['confidence'] ?? 0.85;
                }
                $fieldInfo['candidates'] = $candidates;
            }

            $normalized[$field] = $fieldInfo;
        }

        return [
            'fields' => $normalized,
            'notes' => $aiJson['notes'] ?? 'Ekstraksi AI selesai',
        ];
    }
}
