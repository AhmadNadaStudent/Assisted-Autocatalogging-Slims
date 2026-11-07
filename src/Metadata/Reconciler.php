<?php
namespace AssistedAutocataloguing\Metadata;

/**
 * Reconciles External Metadata Candidates with Physical Book Evidence
 */
class Reconciler
{
    /**
     * Reconcile external catalog metadata and OCR/AI metadata into an InternalBiblioModel
     */
    public function reconcile(?array $externalMetadata, ?array $ocrAiMetadata, InternalBiblioModel $model): InternalBiblioModel
    {
        $fields = $model->getAllFields();

        foreach ($fields as $key => $fieldInfo) {
            $extVal = $this->extractValue($externalMetadata['metadata'] ?? [], $key);
            $ocrItem = $ocrAiMetadata['fields'][$key] ?? null;
            $ocrVal = is_array($ocrItem) ? ($ocrItem['value'] ?? null) : null;
            $ocrEvidenceIds = is_array($ocrItem) ? ($ocrItem['evidence_ids'] ?? []) : [];
            $ocrConfidence = is_array($ocrItem) ? ($ocrItem['confidence'] ?? 0.0) : 0.0;

            $status = 'not_checked';
            $conflictReason = null;
            $confidence = 0.0;
            $selectedValue = null;

            $hasExt = !empty($extVal);
            $hasOcr = !empty($ocrVal);

            if ($hasExt && $hasOcr) {
                // Both available -> Compare
                if ($this->valuesMatch($key, $extVal, $ocrVal)) {
                    $status = 'candidate';
                    $confidence = 0.95;
                    $selectedValue = $ocrVal; // Physical book is primary proof
                } else {
                    $status = 'conflict';
                    $confidence = 0.5;
                    $conflictReason = 'Terdapat perbedaan: Sumber Eksternal menyajikan "' .
                        $this->formatValueForDisplay($extVal) . '", sedangkan Bukti Buku menyajikan "' .
                        $this->formatValueForDisplay($ocrVal) . '"';
                    // Default selected value to OCR/physical book, but flag conflict for librarian
                    $selectedValue = $ocrVal;
                }
            } elseif ($hasOcr && !$hasExt) {
                // Only on physical book
                $status = 'candidate';
                $confidence = $ocrConfidence ?: 0.85;
                $selectedValue = $ocrVal;
            } elseif ($hasExt && !$hasOcr) {
                // Only from external catalog
                $status = 'external_only_unverified';
                $confidence = 0.6;
                $selectedValue = $extVal;
                $conflictReason = 'Hanya ditemukan pada katalog eksternal, belum terverifikasi dari bukti fisik buku.';
            } else {
                // Neither available
                $status = 'not_stated_in_examined_sources';
                $confidence = 0.0;
                $selectedValue = null;
            }

            $candidates = [];
            if ($key === 'classification') {
                if (!empty($extVal)) {
                    $candidates[] = [
                        'class' => trim((string)$extVal),
                        'label' => 'Katalog Perpusnas',
                        'reasoning' => 'Nomor klasifikasi terdaftar resmi di basis data Perpusnas',
                        'source' => 'external',
                    ];
                }
                $aiCandidates = is_array($ocrItem) ? ($ocrItem['candidates'] ?? []) : [];
                foreach ($aiCandidates as $cand) {
                    $cNum = trim((string)($cand['class'] ?? ''));
                    if (empty($cNum)) continue;
                    // check uniqueness
                    $exists = false;
                    foreach ($candidates as $existing) {
                        if ($existing['class'] === $cNum) {
                            $exists = true;
                            break;
                        }
                    }
                    if (!$exists) {
                        $candidates[] = [
                            'class' => $cNum,
                            'label' => trim((string)($cand['label'] ?? 'Rekomendasi AI')),
                            'reasoning' => trim((string)($cand['reasoning'] ?? '')),
                            'source' => 'ai',
                            'confidence' => $cand['confidence'] ?? 0.8,
                        ];
                    }
                }
            }

            $model->setFieldData($key, [
                'external_value' => $extVal,
                'ocr_value' => $ocrVal,
                'selected_value' => $selectedValue,
                'candidates' => $candidates,
                'status' => $status,
                'confidence' => $confidence,
                'evidence_ids' => $ocrEvidenceIds,
                'conflict_reason' => $conflictReason,
                'user_modified' => false,
            ]);
        }

        return $model;
    }

    private function extractValue(array $data, string $key): mixed
    {
        return $data[$key] ?? null;
    }

    private function valuesMatch(string $key, mixed $val1, mixed $val2): bool
    {
        if (is_array($val1) && is_array($val2)) {
            $clean1 = array_map('mb_strtolower', array_map('trim', $val1));
            $clean2 = array_map('mb_strtolower', array_map('trim', $val2));
            sort($clean1);
            sort($clean2);
            return $clean1 === $clean2;
        }

        $str1 = mb_strtolower(preg_replace('/[^\p{L}\p{N}]+/u', ' ', (string)$val1));
        $str2 = mb_strtolower(preg_replace('/[^\p{L}\p{N}]+/u', ' ', (string)$val2));

        $str1 = trim($str1);
        $str2 = trim($str2);

        if ($str1 === $str2) {
            return true;
        }

        // Check substring containment if long enough
        if (strlen($str1) > 10 && strlen($str2) > 10) {
            similar_text($str1, $str2, $percent);
            return $percent > 85;
        }

        return false;
    }

    private function formatValueForDisplay(mixed $val): string
    {
        if (is_array($val)) {
            return implode('; ', $val);
        }
        return (string)$val;
    }

    /**
     * Generate summary statistics of the model
     */
    public function getSummary(InternalBiblioModel $model): array
    {
        $fields = $model->getAllFields();
        $counts = [
            'total' => count($fields),
            'verified' => 0,
            'candidate' => 0,
            'conflict' => 0,
            'external_only_unverified' => 0,
            'not_stated_in_examined_sources' => 0,
            'not_checked' => 0,
            'rejected' => 0,
        ];

        foreach ($fields as $f) {
            $st = $f['status'];
            if (isset($counts[$st])) {
                $counts[$st]++;
            }
        }

        return $counts;
    }
}
