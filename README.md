# Assisted Autocataloguing Plugin untuk SLiMS 9 Bulian

Plugin copy cataloguing cerdas berbantuan AI dan OCR yang dirancang khusus untuk pustakawan SLiMS (Senayan Library Management System). Plugin ini membantu mempercepat proses katalogisasi buku fisik dengan memadukan pencarian katalog eksternal Perpustakaan Nasional RI (Perpusnas), ekstraksi teks bukti fisik halaman buku (OCR), dan rekonsiliasi metadata cerdas (AI) dengan tetap menempatkan **pustakawan sebagai penentu akhir metadata terverifikasi**.

---

## 1. Fitur Utama

- **Pencarian ISBN Otomatis ke Perpusnas**:
  - Mengintegrasikan katalog induk **Indonesia OneSearch (IOS - Perpusnas)** secara langsung tanpa hambatan captcha.
  - Normalisasi dan validasi digit pemeriksa (checksum) format ISBN-10 dan ISBN-13.
  - Adapter cadangan untuk Perpusnas OPAC SRU.
- **Unggah & Pra-pemrosesan Bukti Fisik Buku**:
  - Multi-upload gambar (Halaman Judul, Halaman Hak Cipta, Sampul Depan, Daftar Isi, dll).
  - Validasi MIME type berbasis konten biner dan pembatasan ukuran file.
  - Pra-pemrosesan citra otomatis via PHP GD: auto-orientasi EXIF, konversi grayscale kontras tinggi untuk akurasi OCR, dan pembuatan thumbnail web.
- **Dukungan Mesin OCR Fleksibel**:
  - Dukungan langsung untuk biner lokal Tesseract OCR.
  - Dukungan jembatan Python OCR (*Python bridge*).
  - Ekstraksi aturan heuristik (*rule-based fallback*) otomatis jika mesin OCR lokal belum terpasang.
- **Integrasi AI (Lokal & Cloud)**:
  - **Local-Only Mode**: Mendukung model lokal via Ollama (`llama3.2`, `qwen2.5`, dsb.) menjamin data perpustakaan tidak keluar ke internet.
  - **Cloud Mode**: Mendukung OpenAI, Groq, OpenRouter, atau Gemini API melalui antarmuka standar OpenAI-compatible.
  - **Schema Validation Ketat**: AI dilarang keras mengarang data tanpa bukti teks OCR dan tidak memiliki wewenang menandai data sebagai `verified` secara sepihak.
- **Ekstraksi Abstrak & Sinopsis Otomatis**:
  - Otomatis mengambil sinopsis/abstrak dari Google Books API (`volumeInfo.description`) atau OneSearch (`dc:description`).
  - *Fallback OCR*: Jika tidak tersedia dari ISBN, pustakawan dapat mengunggah foto sampul belakang (*back cover / blurb*) atau pendahuluan; sistem otomatis mengekstrak sinopsis ke field Abstrak (`biblio.notes`).
  - Teks abstrak menjadi acuan penting bagi AI dalam merekomendasikan klasifikasi subjek yang presisi.
- **Rekomendasi Klasifikasi Multikandidat & Multi-Subjek (DDC / 2X0)**:
  - *Penanganan Multi-Subjek Cerdas*: Ketika buku memiliki 2 atau lebih subjek berbeda / interdisipliner, AI menyajikan opsi klasifikasi untuk masing-masing sudut pandang disiplin ilmu lengkap dengan penjelasan alasan/pertimbangannya (*reasoning*).
  - *Kustomisasi Sumber Konteks di Pengaturan*: Pustakawan dapat mengatur sumber data mana saja yang diizinkan untuk diolah AI saat menentukan klasifikasi via checkbox: Judul Buku, Abstrak/Sinopsis, Daftar Isi (TOC), dan Subjek Bawaan.
  - *Antarmuka Dropdown & Kotak Info Sudut Pandang*: Dropdown interaktif memudahkan pustakawan memilih nomor klasifikasi terbaik, dan langsung menampilkan kotak info sudut pandang di bawahnya untuk membantu pengambilan keputusan. Pustakawan juga dapat mengetik manual jika memiliki nomor klasifikasi lain.
- **Pengaturan Format Nomor Panggil Kustom Sesuai Standar**:
  - Format default mengikuti standar Perpustakaan Nasional RI: `{class} {author} {title}` (contoh: `495.1 LIL b`).
  - Pengaturan template fleksibel di menu Settings dengan dukungan token: `{class}`, `{author}`, `{title}`, `{year}`, `{edition}`, `{publisher}`, `{copy}`, `{item_code}`.
  - Nomor Panggil otomatis terhitung ulang secara instan (*real-time*) setiap kali nomor klasifikasi dipilih/diubah, dengan kebebasan penyuntingan manual.
- **Matriks Rekonsiliasi & Deteksi Konflik**:
  - Membandingkan nilai Perpusnas vs bukti fisik buku secara berdampingan.
  - Status pemrosesan terpisah: `candidate`, `verified`, `conflict`, `external_only_unverified`, `not_stated_in_examined_sources`, `rejected`.
  - Tombol satu-klik untuk menerima nilai eksternal, menerima bukti buku, atau memasukkan koreksi manual.
- **Pemetaan & Penyimpanan Standar SLiMS**:
  - Otomatis memetakan ke tabel `biblio`, `mst_author`, `biblio_author`, `mst_publisher`, `mst_place`, `mst_topic`, `biblio_topic`.
  - Pembangkitan nomor panggil (*call number*) otomatis sesuai kaidah perpustakaan.
  - Pencegahan duplikasi data berdasarkan ISBN.
  - Transaksi atomik database PDO (rollback jika terjadi galat).
  - Otomatis memicu pembaruan indeks pencarian SLiMS (`biblio_indexer`) dan hook plugin SLiMS.

---

## 2. Struktur Direktori Proyek

```text
assisted_autocatalogging/
├── assisted_autocatalogging.plugin.php    # Deklarasi plugin resmi SLiMS
├── index.php                             # Halaman utama plugin di panel admin SLiMS
├── api.php                               # Endpoint AJAX terlindungi CSRF & otorisasi
├── config/
│   ├── config.default.php                # Konfigurasi default bawaan sistem
│   └── config.php                        # Konfigurasi aktif lokal (override pengguna)
├── src/
│   ├── Core/                             # Bootstrap, Request, Response, Security
│   ├── Catalog/                          # IsbnValidator, OneSearchAdapter, SruAdapter, CatalogManager
│   ├── Ocr/                              # Preprocessor, Tesseract, PythonBridge, OcrService
│   ├── Ai/                               # OllamaAdapter, OpenAiCompatibleAdapter, PromptTemplates, SchemaValidator, AiService
│   ├── Metadata/                         # InternalBiblioModel, EvidenceLedger, Reconciler
│   └── Slims/                            # MasterDataResolver, SlimsBibliographicMapper, SlimsPersistenceService
├── assets/
│   ├── css/app.css                       # Antarmuka modern, responsif, serasi dengan SLiMS
│   └── js/app.js                         # Logika kontrol antarmuka reaktif (AJAX)
├── templates/
│   └── main_layout.php                   # Layout 4 panel kerja katalogisasi
├── storage/
│   ├── uploads/                          # Direktori penyimpanan bukti foto buku (terproteksi .htaccess)
│   └── .htaccess                         # Memblokir eksekusi skrip PHP pada folder unggahan
├── tests/
│   ├── test_catalog.php                  # Pengujian koneksi katalog OneSearch
│   └── test_full_pipeline.php            # Pengujian end-to-end lengkap dari pencarian sampai database
└── README.md                             # Dokumentasi komprehensif
```

---

## 3. Persyaratan Sistem & Dependensi

- **SLiMS**: SLiMS 9 Bulian (v9.0 ke atas, diuji pada v9.8.0).
- **Web Server**: Apache atau Nginx (diuji pada Laragon).
- **PHP**: Versi 8.1, 8.2, 8.3, atau 8.4 (diuji pada PHP 8.4.21).
  - Ekstensi PHP wajib: `pdo_mysql`, `mysqli`, `curl`, `gd`, `fileinfo`, `mbstring`, `json`, `libxml`.
- **Database**: MySQL 5.7+ / MySQL 8.0+ / MariaDB 10.3+.
- **OCR (Opsional untuk pembacaan teks otomatis)**:
  - Tesseract OCR terpasang di sistem (`tesseract` biner), ATAU
  - Python 3.8+ dengan modul pendukung, ATAU
  - Fitur input manual / verifikasi visual gambar tetap dapat berjalan tanpa OCR.
- **AI (Opsional)**:
  - Ollama lokal (`http://localhost:11434`), ATAU
  - Akun API OpenAI / OpenRouter / Gemini.

---

## 4. Petunjuk Instalasi

Tentukan letak direktori instalasi SLiMS di server Anda (selanjutnya disebut sebagai `<slims_root>`, contoh: `/var/www/html/slims` di Linux, atau `C:\xampp\htdocs\slims` di Windows).

### Cara 1: Menggunakan Git Clone (Direkomendasikan untuk Server & VPS)
Buka terminal server dan jalankan perintah berikut:
```bash
# Masuk ke direktori plugins SLiMS
cd <slims_root>/plugins/

# Clone repositori ke folder assisted_autocatalogging
git clone https://github.com/AhmadNadaStudent/Assisted-Autocatalogging-Slims.git assisted_autocatalogging
```

### Cara 2: Ekstrak Berkas ZIP / Rilis
1. Unduh arsip `assisted_autocatalogging-v1.0.0.zip` dari halaman [Releases](https://github.com/AhmadNadaStudent/Assisted-Autocatalogging-Slims/releases).
2. Ekstrak berkas ZIP ke dalam direktori:
   ```text
   <slims_root>/plugins/assisted_autocatalogging/
   ```
3. Pastikan struktur berkas memiliki file utama di:
   ```text
   <slims_root>/plugins/assisted_autocatalogging/assisted_autocatalogging.plugin.php
   ```

### Cara 3: Mode Development (Symlink / Directory Junction)
Bila Anda mengembangkan plugin pada folder lokal terpisah:
- **Di Linux / macOS**:
  ```bash
  ln -s /path/ke/folder_project/ <slims_root>/plugins/assisted_autocatalogging
  ```
- **Di Windows (CMD / PowerShell sebagai Administrator)**:
  ```powershell
  cmd /c mklink /J "<slims_root>\plugins\assisted_autocatalogging" "<path_ke_folder_project>"
  ```

### Mengaktifkan Plugin di Admin SLiMS:
1. Masuk ke halaman admin SLiMS (`admin/index.php`) sebagai Administrator.
2. Buka menu **Sistem** (*System*) -> **Plugin** (*Plugins*).
3. Temukan **Assisted Autocataloguing** pada daftar plugin, lalu klik tombol **Aktifkan**.
4. Menu **Assisted Autocataloguing** akan langsung muncul di bawah modul **Bibliografi** di menu kiri admin SLiMS.
5. Pastikan folder `storage/uploads` memiliki izin tulis (*write permission*) pada server:
   ```bash
   chmod -R 775 <slims_root>/plugins/assisted_autocatalogging/storage/
   ```

---

## 5. Konfigurasi Sistem (`config/config.php`)

Salin `config/config.default.php` menjadi `config/config.php` jika ingin mengubah pengaturan lokal:

```php
<?php
return [
    // Pengaturan AI
    'ai' => [
        'mode' => 'local_only', // Pilihan: 'local_only', 'cloud', 'disabled'
        'default_provider' => 'ollama', // Pilihan: 'ollama', 'openai_compatible'
        'providers' => [
            'ollama' => [
                'base_url' => 'http://localhost:11434',
                'model' => 'llama3.2',
                'timeout' => 60,
            ],
            'openai_compatible' => [
                'base_url' => 'https://api.openai.com/v1',
                'api_key' => 'MASUKKAN_API_KEY_ANDA_DI_SINI',
                'model' => 'gpt-4o-mini',
                'timeout' => 45,
            ],
        ],
    ],

    // Pengaturan OCR
    'ocr' => [
        'tesseract_binary' => 'tesseract', // Ganti dengan path lengkap jika tidak ada di PATH sistem
        'languages' => 'ind+eng',
    ],
];
```

> [!CAUTION]
> Jangan pernah membagikan atau mengunggah `config.php` yang berisi `api_key` ke repositori publik. `config/config.default.php` disediakan tanpa kredensial rahasia.

---

## 6. Cara Menjalankan Pengujian

Pengujian integrasi mandiri dapat dijalankan langsung melalui CLI:

```bash
# Uji coba parser katalog OneSearch Perpusnas
php tests/test_catalog.php

# Uji coba end-to-end (ISBN -> Perpusnas -> Rekonsiliasi -> Konflik -> Penyimpanan Database SLiMS)
php tests/test_full_pipeline.php
```

---

## 7. Alur Kerja Pustakawan

1. **Input ISBN**: Pustakawan mengetik atau memindai barcode ISBN buku.
2. **Pencarian Katalog**: Sistem mencari rekaman bibliografi ke jejaring Perpusnas (OneSearch).
3. **Unggah Bukti Fisik**: Pustakawan mengunggah foto halaman judul atau hak cipta buku.
4. **Ekstraksi Bukti**: Sistem menjalankan OCR dan AI untuk mengekstrak informasi buku fisik.
5. **Pemeriksaan & Rekonsiliasi**:
   - Sistem menampilkan nilai Perpusnas berdampingan dengan bukti fisik buku.
   - Jika terdapat perbedaan (misal tahun terbit edisi baru), sistem menandai baris dengan status **Konflik**.
   - Pustakawan memilih nilai yang benar atau mengedit field secara manual.
6. **Persetujuan & Simpan Final**: Pustakawan menekan **Simpan Final ke SLiMS**. Data tersimpan secara relasional dan indeks pencarian SLiMS langsung diperbarui.

---

## 8. Panduan Pemecahan Masalah (Troubleshooting)

- **Masalah: Katalog Perpusnas OPAC SRU menghasilkan HTTP 403.**
  - *Penyebab*: Server OPAC Perpusnas dilindungi Cloudflare Bot Challenge.
  - *Solusi*: Sistem secara otomatis beralih ke provider primer **Indonesia OneSearch (Perpusnas)** yang aktif dan dapat diakses dengan stabil.
- **Masalah: Tesseract OCR tidak menghasilkan teks.**
  - *Penyebab*: Biner `tesseract` belum terdaftar di Environment Variable PATH Windows.
  - *Solusi*: Tentukan path lengkap file `tesseract.exe` pada `config/config.php` di bagian `['ocr']['tesseract_binary']`, atau gunakan ekstraksi AI Multimodal / pengetikan manual.
- **Masalah: Buku gagal disimpan karena ISBN sudah ada.**
  - *Penyebab*: Fitur pencegahan duplikasi mendeteksi ISBN yang sama pada database SLiMS.
  - *Solusi*: Buka data bibliografi terkait di modul bibliografi SLiMS bawaan untuk menambahkan eksemplar baru.
