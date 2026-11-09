<?php
namespace AssistedAutocataloguing\Ai;

/**
 * Standardized Prompt Templates for Bibliographic Extraction
 */
class PromptTemplates
{
    public static function getSystemPrompt(): string
    {
        return <<<PROMPT
Anda adalah asisten ahli katalogisasi perpustakaan berstandar AACR2 / RDA untuk sistem SLiMS (Senayan Library Management System).
Tugas Anda adalah mengekstraksi informasi bibliografi buku dari bukti fisik (gambar foto halaman buku dan/atau teks hasil OCR) yang diberikan.

ATURAN KETAT:
1. DILARANG MENGARANG ATAU MENAMBAHKAN DATA YANG TIDAK DITEMUKAN PADA GAMBAR ATAU TEKS BUKTI.
2. Jika suatu field informasi tidak ada pada bukti fisik, isi nilai field tersebut dengan null.
3. Anda TIDAK MEMILIKI WEWENANG untuk memvalidasi atau memverifikasi data. Anda hanya menghasilkan nilai KANDIDAT dari bukti fisik buku.
4. Identifikasi:
   - title (Judul utama buku)
   - sor (Pernyataan tanggung jawab / Statement of Responsibility, misal: "oleh John Doe ; disunting oleh Budi")
   - authors (Array nama pengarang / kontributor utama)
   - publisher (Nama penerbit)
   - publish_place (Kota tempat terbit)
   - publish_year (Tahun terbit / hak cipta 4 digit)
   - edition (Edisi / cetakan, misal: "Cetakan ke-2", "Edisi Revisi")
   - isbn (Nomor ISBN jika terbaca)
   - collation (Deskripsi fisik / jumlah halaman / ukuran cm jika terbaca)
   - series_title (Judul seri jika ada)
   - subjects (Topik atau subjek bahasan utama buku)
   - notes (Abstrak / Sinopsis / ringkasan isi buku jika ditemukan pada teks bukti seperti sampul belakang, halaman pengantar, atau blurb buku)
   - classification (Rekomendasi Nomor Klasifikasi DDC / Dewey Decimal Classification utama)
   - classification_candidates (Daftar 2 hingga 5 kandidat klasifikasi DDC yang relevan beserta label dan reasoning sudut pandangnya)
   - call_number (Rekomendasi Nomor Panggil standar perpustakaan Perpusnas dengan format: [Klasifikasi DDC] [3 huruf kapital pengarang] [1 huruf kecil judul], contoh: "495.1 LIL b" atau "2X5.1 FAZ a")
5. ATURAN ANALISIS KLASIFIKASI & MULTI-SUBJEK:
   - Jika buku membahas 2 atau lebih subjek/disiplin yang berbeda atau bersifat interdisipliner (misal: "Penerapan AI dalam Kedokteran" -> Ilmu Komputer vs Kedokteran; "Ekonomi Islam" -> Teori Ekonomi vs Agama Islam):
   - Jangan hanya memberi 1 nomor klasifikasi! Berikan kandidat klasifikasi untuk masing-masing sudut pandang disiplin ilmu pada `classification_candidates`.
   - Untuk setiap kandidat pada `classification_candidates`, sertakan:
     * `class`: Nomor DDC (misal "006.3", "610.28", "2X6.3", dll).
     * `label`: Nama subjek/disiplin singkat (misal "Kecerdasan Buatan" atau "Teknologi Kedokteran").
     * `reasoning`: Penjelasan ringkas sudut pandang (misal "Pilihan tepat jika perpustakaan ingin menekankan aspek teknologi & algoritma AI" vs "Pilihan tepat jika perpustakaan ingin mengutamakan aplikasi klinis dan medis").
     * `confidence`: Angka keyakinan 0.0 - 1.0.
6. Keluaran WAJIB dalam format JSON murni tanpa markdown pembungkus.
PROMPT;
    }

    public static function buildUserPrompt(array $evidenceItems, ?array $externalMetadata = null, array $contextSources = ['title', 'abstract', 'toc', 'subjects']): string
    {
        $prompt = "Berikut adalah bukti fisik (gambar foto halaman buku dan teks OCR) dari buku yang diperiksa:\n\n";

        foreach ($evidenceItems as $item) {
            $id = $item['evidence_id'] ?? 'evidence-unknown';
            $pageType = $item['page_type'] ?? 'dokumen';
            $text = trim($item['ocr_text'] ?? '');

            // Check if TOC is excluded
            if ($pageType === 'toc' && !in_array('toc', $contextSources)) {
                continue;
            }

            $prompt .= "=== BUKTI ID: {$id} (Halaman: {$pageType}) ===\n";
            $prompt .= empty($text) ? "[Teks OCR belum terdeteksi - silakan baca langsung dari gambar terlampir jika model Anda mendukung Vision]\n\n" : "{$text}\n\n";
        }

        if (!empty($externalMetadata)) {
            $prompt .= "--- REFERENSI DARI KATALOG PERPUSNAS (Hanya sebagai konteks pembanding) ---\n";
            $prompt .= json_encode($externalMetadata, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n";
        }

        $sourceLabels = [
            'title' => 'Judul Buku & Subjudul',
            'abstract' => 'Abstrak / Sinopsis Buku',
            'toc' => 'Daftar Isi (Table of Contents)',
            'subjects' => 'Subjek / Topik Bawaan Katalog',
        ];
        $activeLabels = array_map(fn($s) => $sourceLabels[$s] ?? $s, $contextSources);
        $prompt .= "PENTING - SUMBER DATA UTAMA UNTUK PENENTUAN KLASIFIKASI OLEH PUSTAKAWAN:\n";
        $prompt .= "Pustakawan mengaktifkan sumber: " . implode(', ', $activeLabels) . ".\n";
        $prompt .= "Harap fokuskan pertimbangan nomor klasifikasi pada sumber-sumber tersebut saat menimbang sudut pandang multi-subjek.\n\n";

        $prompt .= <<<JSON_SCHEMA
Silakan ekstrak metadata dan kembalikan HANYA format JSON persis seperti skema berikut:
{
  "fields": {
    "title": {"value": null, "evidence_id": null, "confidence": 0.0, "snippet": null},
    "sor": {"value": null, "evidence_id": null, "confidence": 0.0, "snippet": null},
    "authors": [{"name": "...", "role": "author", "evidence_id": null, "confidence": 0.0}],
    "publisher": {"value": null, "evidence_id": null, "confidence": 0.0, "snippet": null},
    "publish_place": {"value": null, "evidence_id": null, "confidence": 0.0, "snippet": null},
    "publish_year": {"value": null, "evidence_id": null, "confidence": 0.0, "snippet": null},
    "edition": {"value": null, "evidence_id": null, "confidence": 0.0, "snippet": null},
    "isbn": {"value": null, "evidence_id": null, "confidence": 0.0, "snippet": null},
    "collation": {"value": null, "evidence_id": null, "confidence": 0.0, "snippet": null},
    "series_title": {"value": null, "evidence_id": null, "confidence": 0.0, "snippet": null},
    "subjects": [],
    "notes": {"value": null, "evidence_id": null, "confidence": 0.0, "snippet": null},
    "classification": {"value": null, "evidence_id": null, "confidence": 0.0, "snippet": null},
    "classification_candidates": [
      {
        "class": "006.3",
        "label": "Kecerdasan Buatan (AI)",
        "reasoning": "Menekankan pada fondasi teknik, algoritma, dan teknologi komputasi AI.",
        "confidence": 0.90
      },
      {
        "class": "610.28",
        "label": "Teknologi Medis & Biomedika",
        "reasoning": "Menekankan pada aplikasi praktis di bidang klinis dan kedokteran.",
        "confidence": 0.85
      }
    ],
    "call_number": {"value": null, "evidence_id": null, "confidence": 0.0, "snippet": null}
  },
  "notes": "Catatan singkat observasi ekstraksi"
}
JSON_SCHEMA;

        return $prompt;
    }
}
