<?php
/**
 * Main Layout Template for Assisted Autocataloguing
 */
if (!defined('INDEX_AUTH')) {
    die('Akses langsung tidak diizinkan');
}

$pluginWebBase = (defined('SWB') ? rtrim(SWB, '/') : '') . '/plugins/assisted_autocatalogging/';
$adminWebBase = (defined('AWB') ? rtrim(AWB, '/') : '') . '/';
$menuHash = md5(realpath(__DIR__ . '/../index.php'));
// Use admin plugin_container URL to guarantee admin session and cookies
$apiBaseUrl = $adminWebBase . 'plugin_container.php?mod=bibliography&id=' . $menuHash;
?>
<link rel="stylesheet" href="<?= $pluginWebBase ?>assets/css/app.css?v=3.2">

<div class="aac-container">

  <!-- Header Session Bar -->
  <div class="aac-header-bar">
    <div class="aac-brand">
      <h1>
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>
        Assisted Autocataloguing
      </h1>
      <div class="aac-brand-desc">Copy Cataloguing Cerdas Berbantuan AI & Verifikasi Bukti Fisik Buku</div>
    </div>
    <div class="aac-status-badges" id="aac-status-badges">
      <span class="aac-badge aac-badge-muted">Memeriksa status...</span>
    </div>
    <div style="display:flex;gap:0.5rem;align-items:center;">
      <button type="button" class="aac-btn aac-btn-secondary aac-btn-sm" id="aac-btn-guide" onclick="AAC.openGuide()">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path></svg>
        <span>Panduan Sistem</span>
      </button>
      <button type="button" class="aac-btn aac-btn-secondary aac-btn-sm" id="aac-btn-settings" onclick="AAC.openSettings()">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
        <span>Pengaturan AI</span>
      </button>
      <button type="button" class="aac-btn aac-btn-secondary aac-btn-sm" id="aac-btn-reset">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="1 4 1 10 7 10"></polyline><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path></svg>
        Mulai Buku Baru
      </button>
    </div>
  </div>

  <!-- Global Metrics Summary Bar across top -->
  <div class="aac-stats-bar">
    <div class="aac-stat-box is-verified">
      <div class="aac-stat-info">
        <div class="aac-stat-num" id="aac-stat-verified">0</div>
        <div class="aac-stat-label">Terverifikasi</div>
      </div>
      <div class="aac-stat-icon">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
      </div>
    </div>
    <div class="aac-stat-box is-candidates">
      <div class="aac-stat-info">
        <div class="aac-stat-num" id="aac-stat-candidates">0</div>
        <div class="aac-stat-label">Kandidat</div>
      </div>
      <div class="aac-stat-icon">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
      </div>
    </div>
    <div class="aac-stat-box is-conflict">
      <div class="aac-stat-info">
        <div class="aac-stat-num" id="aac-stat-conflict">0</div>
        <div class="aac-stat-label">Konflik Data</div>
      </div>
      <div class="aac-stat-icon">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
      </div>
    </div>
    <div class="aac-stat-box is-unverified">
      <div class="aac-stat-info">
        <div class="aac-stat-num" id="aac-stat-unverified">0</div>
        <div class="aac-stat-label">Belum Terbukti</div>
      </div>
      <div class="aac-stat-icon">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>
      </div>
    </div>
  </div>

  <!-- Main Grid Layout -->
  <div class="aac-grid">

    <!-- Left Column: Input ISBN & Upload Bukti Fisik -->
    <div class="aac-col-left">

      <!-- Panel 1: ISBN Search -->
      <div class="aac-card">
        <h2 class="aac-card-title">
          <span>1. Input ISBN & Katalog Eksternal</span>
        </h2>
        <div id="aac-isbn-alert" style="display:none;"></div>
        <div class="aac-form-group">
          <label class="aac-form-label" for="aac-input-isbn">Nomor ISBN (10 atau 13 Digit)</label>
          <div class="aac-search-input-group">
            <input type="text" class="aac-input" id="aac-input-isbn" placeholder="Contoh: 9786230034435" autofocus>
            <button type="button" class="aac-btn aac-btn-primary" id="aac-btn-search-isbn">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
              <span>Cari Katalog</span>
            </button>
          </div>
          <div style="font-size:0.75rem;color:var(--aac-text-muted);margin-top:0.45rem;">
            Sumber utama: Perpustakaan Nasional RI (OneSearch / OPAC).
          </div>
        </div>
      </div>

      <!-- Panel 2: Upload Gambar Bukti Fisik -->
      <div class="aac-card">
        <h2 class="aac-card-title">
          <span>2. Bukti Fisik Buku (OCR / AI)</span>
        </h2>
        <div id="aac-upload-alert" style="display:none;"></div>

        <div class="aac-form-group">
          <label class="aac-form-label" for="aac-page-type-select">Target Halaman</label>
          <select class="aac-input" id="aac-page-type-select">
            <option value="title_page">Halaman Judul (Title Page)</option>
            <option value="copyright_page">Halaman Hak Cipta / Balik Judul</option>
            <option value="cover">Sampul Depan (Cover)</option>
            <option value="back_cover">Sampul Belakang / Sinopsis (Back Cover / Blurb)</option>
            <option value="toc">Daftar Isi (Table of Contents)</option>
            <option value="other">Halaman Lainnya</option>
          </select>
        </div>

        <div class="aac-upload-zone" id="aac-upload-zone">
          <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="var(--aac-primary)" stroke-width="2" style="margin:0 auto;display:block;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
          <div class="aac-upload-title">Klik atau Tarik Foto Halaman ke Sini</div>
          <div class="aac-upload-hint">Format JPEG, PNG, WebP (Maks. 10MB)</div>
          <input type="file" id="aac-file-input" multiple accept="image/jpeg,image/png,image/webp" style="display:none;">
        </div>

        <!-- Evidence Cards List -->
        <div class="aac-evidence-list" id="aac-evidence-list">
          <div style="color:var(--aac-text-subtle);font-size:0.78rem;text-align:center;padding:1rem;grid-column:1/-1;">Belum ada gambar bukti fisik yang diunggah.</div>
        </div>

        <div style="margin-top:1.25rem;">
          <button type="button" class="aac-btn aac-btn-primary" id="aac-btn-run-ai" style="width:100%;" disabled>
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg>
            <span>Ekstraksi Bukti Buku (AI / OCR)</span>
          </button>
        </div>
      </div>

      <!-- Panel 3: Pratinjau Buku (Google Books) -->
      <div class="aac-card" id="aac-gbooks-card">
        <h2 class="aac-card-title">
          <span>3. Pratinjau Buku (Google Books)</span>
          <span class="aac-badge aac-badge-muted" id="aac-gbooks-badge">Menunggu ISBN</span>
        </h2>

        <!-- Default State: Empty -->
        <div id="aac-gbooks-empty" style="text-align:center;padding:1.5rem 0.5rem;color:var(--aac-text-subtle);">
          <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" style="margin:0 auto 0.5rem auto;display:block;"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>
          <div style="font-size:0.8rem;font-weight:600;color:var(--aac-text);">Pratinjau Belum Dimuat</div>
          <div style="font-size:0.72rem;margin-top:0.25rem;line-height:1.4;">Cari nomor ISBN di atas untuk memuat pratinjau halaman buku dan sampul dari Google Books.</div>
        </div>

        <!-- Loaded State: Cover & Preview Link -->
        <div id="aac-gbooks-content" style="display:none;text-align:center;padding:0.4rem 0;">
          <div style="margin-bottom:0.75rem;">
            <img id="aac-gbooks-img" src="" alt="Sampul Google Books" style="max-height:210px;border-radius:8px;box-shadow:var(--aac-shadow-md);object-fit:contain;border:1px solid var(--aac-border);background:#f8fafc;display:inline-block;">
          </div>
          <div id="aac-gbooks-title" style="font-weight:700;font-size:0.84rem;color:var(--aac-text);line-height:1.35;margin-bottom:0.25rem;"></div>
          <div id="aac-gbooks-status-desc" style="font-size:0.72rem;color:var(--aac-text-muted);margin-bottom:0.75rem;"></div>
          
          <div style="display:flex;flex-direction:column;gap:0.45rem;">
            <a id="aac-gbooks-link" href="#" target="_blank" class="aac-btn aac-btn-outline-primary aac-btn-sm" style="text-decoration:none;display:flex;justify-content:center;gap:0.4rem;">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
              <span>Buka Pratinjau di Google Books</span>
            </a>
          </div>
          <div style="margin-top:0.6rem;font-size:0.72rem;color:var(--aac-success);display:flex;align-items:center;justify-content:center;gap:0.3rem;">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
            <span>Sampul otomatis tersimpan ke SLiMS</span>
          </div>
        </div>

        <!-- Not Found State -->
        <div id="aac-gbooks-notfound" style="display:none;text-align:center;padding:1.4rem 0.5rem;color:var(--aac-warning);">
          <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="margin:0 auto 0.4rem auto;display:block;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
          <div style="font-size:0.8rem;font-weight:600;">Pratinjau Tidak Tersedia</div>
          <div style="font-size:0.72rem;color:var(--aac-text-muted);margin-top:0.25rem;line-height:1.35;">Google Books tidak memiliki sampel pratinjau buku untuk ISBN ini.</div>
        </div>

      </div>

    </div>

    <!-- Right Column: Matriks Rekonsiliasi & Simpan Final -->
    <div class="aac-col-right">

      <!-- Panel 4: Matriks Komparasi Metadata -->
      <div class="aac-card">
        <div class="aac-card-title">
          <span>4. Matriks Rekonsiliasi & Pemeriksaan Metadata</span>
          <div style="display:flex;gap:0.4rem;align-items:center;">
            <button type="button" class="aac-btn aac-btn-outline-primary aac-btn-sm" id="aac-btn-ai-classify" onclick="AAC.runAiClassification()" style="display:none;" title="Gunakan AI untuk menganalisis subjek dan menghasilkan nomor klasifikasi DDC">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg>
              <span>Analisis Klasifikasi AI</span>
            </button>
            <button type="button" class="aac-btn aac-btn-outline-primary aac-btn-sm" id="aac-btn-approve-all" onclick="AAC.approveAllExternal()" style="display:none;">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="20 6 9 17 4 12"></polyline></svg>
              <span>Setujui Semua dari Perpusnas</span>
            </button>
          </div>
        </div>
        <div id="aac-metadata-alert" style="display:none;"></div>

        <div class="aac-table-wrap">
          <table class="aac-table">
            <thead>
              <tr>
                <th class="aac-field-cell">Field Metadata</th>
                <th class="aac-source-cell">Katalog Perpusnas</th>
                <th class="aac-source-cell">Bukti Fisik Buku</th>
                <th class="aac-final-cell">Nilai Final (Disetujui)</th>
              </tr>
            </thead>
            <tbody id="aac-metadata-tbody">
              <tr>
                <td colspan="4" style="text-align:center;padding:3rem 1.5rem;color:var(--aac-text-subtle);">
                  Silakan masukkan ISBN di panel kiri untuk memulai proses verifikasi katalog.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Panel 5: Penyimpanan SLiMS -->
      <div class="aac-card" style="margin-bottom:0;">
        <h2 class="aac-card-title">
          <span>5. Simpan ke Database Bibliografi SLiMS</span>
        </h2>
        <div id="aac-storage-alert" style="display:none;"></div>
        <p style="font-size:0.82rem;color:var(--aac-text-muted);margin-bottom:1rem;line-height:1.4;">
          Penyimpanan akan memetakan seluruh metadata terverifikasi ke tabel <code>biblio</code>, menghubungkan master pengarang, penerbit, tempat terbit, topik/subjek, dan memperbarui indeks pencarian SLiMS secara otomatis.
        </p>
        <div style="display:flex;justify-content:flex-end;">
          <button type="button" class="aac-btn aac-btn-success" id="aac-btn-save-slims">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
            <span>Simpan Final ke SLiMS</span>
          </button>
        </div>
      </div>

    </div>

  </div>

  <!-- Modal Settings -->
  <div class="aac-modal-overlay" id="aac-modal-settings" style="display:none;" onclick="if(event.target===this)AAC.closeSettings()">
    <div class="aac-modal aac-modal-settings">
      <div class="aac-modal-header">
        <div>
          <h3 class="aac-modal-title">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
            <span>Pengaturan & Konfigurasi Sistem</span>
          </h3>
          <p class="aac-modal-subtitle">Atur preferensi model kecerdasan buatan, OCR, katalog eksternal, dan format nomor panggil.</p>
        </div>
        <button type="button" class="aac-modal-close" onclick="AAC.closeSettings()" title="Tutup Pengaturan">&times;</button>
      </div>

      <div class="aac-modal-layout">
        <!-- Sidebar Nav Tabs -->
        <nav class="aac-settings-sidebar">
          <button type="button" class="aac-tab-btn active" id="tab-btn-ai" onclick="AAC.switchTab('tab-ai', this)">
            <div class="aac-tab-icon">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg>
            </div>
            <div class="aac-tab-text">
              <strong>Model AI & Provider</strong>
              <span>Ollama Lokal, Cloud & Mode</span>
            </div>
          </button>
          <button type="button" class="aac-tab-btn" id="tab-btn-ocr" onclick="AAC.switchTab('tab-ocr', this)">
            <div class="aac-tab-icon">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="3" y1="9" x2="21" y2="9"></line><line x1="9" y1="21" x2="9" y2="9"></line></svg>
            </div>
            <div class="aac-tab-text">
              <strong>Mesin OCR</strong>
              <span>Tesseract OS & Bahasa</span>
            </div>
          </button>
          <button type="button" class="aac-tab-btn" id="tab-btn-catalog" onclick="AAC.switchTab('tab-catalog', this)">
            <div class="aac-tab-icon">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
            </div>
            <div class="aac-tab-text">
              <strong>Katalog Eksternal</strong>
              <span>Perpusnas OneSearch</span>
            </div>
          </button>
          <button type="button" class="aac-tab-btn" id="tab-btn-slims" onclick="AAC.switchTab('tab-slims', this)">
            <div class="aac-tab-icon">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>
            </div>
            <div class="aac-tab-text">
              <strong>No. Panggil & Klasifikasi</strong>
              <span>Format DDC & Konteks AI</span>
            </div>
          </button>
        </nav>

        <!-- Right Content Area -->
        <main class="aac-settings-content">
          <div id="aac-settings-alert" style="display:none;margin-bottom:1rem;"></div>

          <!-- TAB AI -->
          <div class="aac-tab-content active" id="tab-ai">
            <!-- Global Mode & Provider Card -->
            <div class="aac-settings-card">
              <div class="aac-settings-card-header">
                <span class="aac-settings-card-title">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"></polygon></svg>
                  Mode Operasi & Provider Utama
                </span>
              </div>
              <div class="aac-form-grid-2">
                <div class="aac-form-group" style="margin-bottom:0;">
                  <label class="aac-form-label">Mode Operasi AI</label>
                  <select class="aac-input" id="cfg-ai-mode" onchange="AAC.onAiModeChange(this.value)">
                    <option value="local_only">Local-Only (Ollama Lokal Saja — Privasi Penuh)</option>
                    <option value="cloud">Cloud AI (OpenAI / 9Router / OpenRouter / Gemini)</option>
                    <option value="hybrid">Hybrid (OCR Lokal + AI Pilihan Sesuai Kebutuhan)</option>
                    <option value="disabled">Disabled (Nonaktifkan AI, Gunakan Heuristik RegEx)</option>
                  </select>
                </div>
                <div class="aac-form-group" style="margin-bottom:0;">
                  <label class="aac-form-label">Provider AI Aktif</label>
                  <select class="aac-input" id="cfg-ai-default-provider">
                    <option value="ollama">Ollama (Lokal PC)</option>
                    <option value="openai_compatible">Cloud AI / 9Router (OpenAI-Compatible)</option>
                  </select>
                </div>
              </div>
              <div class="aac-mode-banner" id="aac-mode-banner">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                <span id="aac-mode-desc">Mode Local-Only: Semua pemrosesan data dilakukan 100% di komputer lokal tanpa mengirim data keluar.</span>
              </div>
            </div>

            <!-- Section Ollama -->
            <div class="aac-settings-card" id="sec-ollama">
              <div class="aac-settings-card-header">
                <span class="aac-settings-card-title">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
                  Konfigurasi Ollama (AI Lokal)
                </span>
                <button type="button" class="aac-btn aac-btn-outline-primary aac-btn-sm" id="btn-test-ollama" onclick="AAC.testOllama()">
                  <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>
                  <span>Tes Koneksi Ollama</span>
                </button>
              </div>
              <div class="aac-form-grid-2-1">
                <div class="aac-form-group">
                  <label class="aac-form-label">Ollama Base URL</label>
                  <input type="text" class="aac-input" id="cfg-ollama-url" placeholder="http://localhost:11434">
                </div>
                <div class="aac-form-group">
                  <label class="aac-form-label">Timeout (Detik)</label>
                  <input type="number" class="aac-input" id="cfg-ollama-timeout" value="120" min="10" max="600">
                </div>
              </div>
              <div class="aac-form-group" style="margin-bottom:0;">
                <label class="aac-form-label">Model Name (Ollama)</label>
                <input type="text" class="aac-input" id="cfg-ollama-model" list="ollama-model-list" placeholder="Contoh: qwen3-vl:8b, gemma4:e4b, llama3.2">
                <datalist id="ollama-model-list"></datalist>
                <div style="font-size:0.72rem;color:var(--aac-text-muted);margin-top:0.35rem;">
                  Klik "Tes Koneksi Ollama" untuk mendeteksi otomatis seluruh model yang sudah terpasang.
                </div>
              </div>
              <div class="aac-test-result" id="res-test-ollama"></div>
            </div>

            <!-- Section Cloud AI -->
            <div class="aac-settings-card" id="sec-cloud">
              <div class="aac-settings-card-header">
                <span class="aac-settings-card-title">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 10h-1.26A8 8 0 1 0 9 20h9a5 5 0 0 0 0-10z"></path></svg>
                  Konfigurasi Cloud AI / 9Router (OpenAI-Compatible)
                </span>
                <button type="button" class="aac-btn aac-btn-outline-primary aac-btn-sm" id="btn-test-cloud" onclick="AAC.testCloudAi()">
                  <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>
                  <span>Tes Koneksi Cloud</span>
                </button>
              </div>
              <div class="aac-form-grid-2-1">
                <div class="aac-form-group">
                  <label class="aac-form-label">Base URL (Endpoint API)</label>
                  <input type="text" class="aac-input" id="cfg-cloud-url" placeholder="https://api.openai.com/v1 atau http://localhost:20128/v1">
                </div>
                <div class="aac-form-group">
                  <label class="aac-form-label">Timeout (Detik)</label>
                  <input type="number" class="aac-input" id="cfg-cloud-timeout" value="45" min="5" max="300">
                </div>
              </div>
              <div class="aac-form-grid-2">
                <div class="aac-form-group" style="margin-bottom:0;">
                  <label class="aac-form-label">API Key</label>
                  <input type="password" class="aac-input" id="cfg-cloud-key" placeholder="Masukkan API Key (atau Default Key jika 9Router)">
                </div>
                <div class="aac-form-group" style="margin-bottom:0;">
                  <label class="aac-form-label">Model Name</label>
                  <input type="text" class="aac-input" id="cfg-cloud-model" placeholder="Contoh: gpt-4o-mini, glm-5.3-flash-free">
                </div>
              </div>
              <div class="aac-test-result" id="res-test-cloud"></div>
            </div>
          </div>

          <!-- TAB OCR -->
          <div class="aac-tab-content" id="tab-ocr">
            <div class="aac-settings-card">
              <div class="aac-settings-card-header">
                <span class="aac-settings-card-title">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7V4h16v3M9 20h6M12 4v16"></path></svg>
                  Pengaturan Mesin OCR Lokal
                </span>
              </div>
              <div class="aac-form-grid-2">
                <div class="aac-form-group">
                  <label class="aac-form-label">Driver OCR Utama</label>
                  <select class="aac-input" id="cfg-ocr-driver">
                    <option value="auto">Auto (Deteksi Otomatis Tesseract OS / Python)</option>
                    <option value="tesseract">Tesseract CLI</option>
                    <option value="python">Python Bridge (RapidOCR / Script)</option>
                  </select>
                </div>
                <div class="aac-form-group">
                  <label class="aac-form-label">Bahasa OCR (Tesseract Traineddata)</label>
                  <input type="text" class="aac-input" id="cfg-ocr-lang" value="ind+eng" placeholder="ind+eng">
                </div>
              </div>
              <div class="aac-form-grid-2">
                <div class="aac-form-group" style="margin-bottom:0;">
                  <label class="aac-form-label">Tesseract Binary / Command</label>
                  <input type="text" class="aac-input" id="cfg-ocr-tesseract" placeholder="tesseract atau C:\Program Files\Tesseract-OCR\tesseract.exe">
                </div>
                <div class="aac-form-group" style="margin-bottom:0;">
                  <label class="aac-form-label">Python Binary Path (Opsional)</label>
                  <input type="text" class="aac-input" id="cfg-ocr-python" placeholder="python atau D:\laragon\bin\python\python.exe">
                </div>
              </div>
            </div>

            <div class="aac-mode-banner" style="background:#f8fafc;border-color:var(--aac-border);color:var(--aac-text);">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--aac-primary);"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
              <span>Tesseract mengekstraksi teks bukti fisik (halaman judul, copyright, daftar isi) secara lokal untuk diverifikasi pustakawan.</span>
            </div>
          </div>

          <!-- TAB KATALOG -->
          <div class="aac-tab-content" id="tab-catalog">
            <div class="aac-settings-card">
              <div class="aac-settings-card-header">
                <span class="aac-settings-card-title">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                  Integrasi Sumber Katalog Perpustakaan Nasional RI
                </span>
              </div>
              <div class="aac-form-group" style="display:none;">
                <label class="aac-form-label">Provider Utama Pencarian ISBN</label>
                <select class="aac-input" id="cfg-catalog-provider">
                  <option value="onesearch">Indonesia OneSearch (Perpusnas Union Catalog - Rekomendasi)</option>
                  <option value="perpusnas_sru">Perpusnas OPAC SRU (Bisa terblokir Cloudflare WAF)</option>
                </select>
              </div>

              <div class="aac-provider-cards">
                <div class="aac-provider-card active" id="card-prov-onesearch" onclick="AAC.selectCatalogProvider('onesearch')">
                  <div class="aac-provider-card-header">
                    <span class="aac-provider-card-title">Indonesia OneSearch (Perpusnas)</span>
                    <span class="aac-badge aac-badge-success" style="font-size:0.65rem;">Rekomendasi</span>
                  </div>
                  <div class="aac-provider-card-desc">
                    Union Catalog nasional resmi Perpustakaan Nasional RI. Berbasis REST API publik yang cepat, stabil, dan bebas dari proteksi Cloudflare WAF.
                  </div>
                </div>

                <div class="aac-provider-card" id="card-prov-sru" onclick="AAC.selectCatalogProvider('perpusnas_sru')">
                  <div class="aac-provider-card-header">
                    <span class="aac-provider-card-title">Perpusnas OPAC SRU</span>
                    <span class="aac-badge aac-badge-warning" style="font-size:0.65rem;">Protokol SRU</span>
                  </div>
                  <div class="aac-provider-card-desc">
                    Protokol baku pencarian bibliografi Z39.50 SRU/XML langsung ke pangkalan data OPAC Perpusnas (dapat terhalang jika bot challenge aktif).
                  </div>
                </div>
              </div>
            </div>

            <div class="aac-mode-banner" style="background:#eff6ff;border-color:#bfdbfe;color:#1e40af;">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><path d="M12 16v-4"></path><path d="M12 8h.01"></path></svg>
              <span>Kedua sumber ini mengunduh metadata bibliografi berstandar MARC21/ISBD untuk direkonsiliasi dengan bukti fisik buku.</span>
            </div>
          </div>

          <!-- TAB SLIMS & FORMAT NOMOR PANGGIL -->
          <div class="aac-tab-content" id="tab-slims">
            <div class="aac-settings-card">
              <div class="aac-settings-card-header">
                <span class="aac-settings-card-title">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>
                  Standar & Pola Template Nomor Panggil (Call Number)
                </span>
              </div>
              <div class="aac-form-group" style="margin-bottom:0.75rem;">
                <label class="aac-form-label">Pola Template Nomor Panggil</label>
                <input type="text" class="aac-input" id="cfg-call-number-template" style="font-family:monospace;font-size:0.95rem;font-weight:600;letter-spacing:0.5px;" placeholder="{class} {author} {title}" oninput="AAC.updateCallNumberPreview()">
                
                <!-- Live Preview -->
                <div class="aac-call-number-preview-box">
                  <span style="font-size:0.75rem;font-weight:600;color:#0369a1;">Pratinjau Format Call Number:</span>
                  <span class="aac-call-number-preview-val" id="aac-call-number-live-preview">495.1 LIL b</span>
                </div>
              </div>

              <!-- Token Pills -->
              <div class="aac-token-group">
                <div class="aac-token-group-title">Standar Baku Perpustakaan Nasional RI (Klik untuk Tambah):</div>
                <div class="aac-token-list">
                  <button type="button" class="aac-token-pill" onclick="AAC.insertCallNumberToken('{class}')">
                    <code>{class}</code> No. Klasifikasi DDC
                  </button>
                  <button type="button" class="aac-token-pill" onclick="AAC.insertCallNumberToken('{author}')">
                    <code>{author}</code> 3 Huruf Pengarang Kapital
                  </button>
                  <button type="button" class="aac-token-pill" onclick="AAC.insertCallNumberToken('{title}')">
                    <code>{title}</code> 1 Huruf Judul Kecil
                  </button>
                </div>
              </div>

              <div class="aac-token-group">
                <div class="aac-token-group-title">Variabel Ekstensi / Lanjutan:</div>
                <div class="aac-token-list">
                  <button type="button" class="aac-token-pill" onclick="AAC.insertCallNumberToken('{year}')">
                    <code>{year}</code> Tahun
                  </button>
                  <button type="button" class="aac-token-pill" onclick="AAC.insertCallNumberToken('{edition}')">
                    <code>{edition}</code> Edisi
                  </button>
                  <button type="button" class="aac-token-pill" onclick="AAC.insertCallNumberToken('{publisher}')">
                    <code>{publisher}</code> Penerbit
                  </button>
                  <button type="button" class="aac-token-pill" onclick="AAC.insertCallNumberToken('{copy}')">
                    <code>{copy}</code> Eksemplar (c.1)
                  </button>
                  <button type="button" class="aac-token-pill" onclick="AAC.insertCallNumberToken('{item_code}')">
                    <code>{item_code}</code> Barcode
                  </button>
                </div>
              </div>
            </div>

            <!-- Context Sources Card -->
            <div class="aac-settings-card">
              <div class="aac-settings-card-header">
                <span class="aac-settings-card-title">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                  Sumber Data Konteks untuk Klasifikasi AI
                </span>
              </div>
              <p style="font-size:0.75rem;color:var(--aac-text-muted);margin-bottom:0.75rem;line-height:1.45;">
                Pilih elemen bibliografi yang diolah AI saat menganalisis subjek ganda / interdisipliner untuk merumuskan kandidat DDC:
              </p>
              <div class="aac-ctx-grid">
                <label class="aac-ctx-card checked" id="label-ctx-title">
                  <input type="checkbox" id="cfg-ctx-title" value="title" checked onchange="AAC.onCtxCheckboxChange(this, 'label-ctx-title')">
                  <div class="aac-ctx-card-text">
                    <strong>Judul & Subjudul Buku</strong>
                    <span>Menentukan tema primer karya dari redaksi judul</span>
                  </div>
                </label>
                <label class="aac-ctx-card checked" id="label-ctx-abstract">
                  <input type="checkbox" id="cfg-ctx-abstract" value="abstract" checked onchange="AAC.onCtxCheckboxChange(this, 'label-ctx-abstract')">
                  <div class="aac-ctx-card-text">
                    <strong>Abstrak / Sinopsis Buku</strong>
                    <span>Menggali substansi isi & konteks bahasan secara komprehensif</span>
                  </div>
                </label>
                <label class="aac-ctx-card checked" id="label-ctx-toc">
                  <input type="checkbox" id="cfg-ctx-toc" value="toc" checked onchange="AAC.onCtxCheckboxChange(this, 'label-ctx-toc')">
                  <div class="aac-ctx-card-text">
                    <strong>Daftar Isi (TOC)</strong>
                    <span>Memetakan bab dan bobot pembahasan tiap topik</span>
                  </div>
                </label>
                <label class="aac-ctx-card checked" id="label-ctx-subjects">
                  <input type="checkbox" id="cfg-ctx-subjects" value="subjects" checked onchange="AAC.onCtxCheckboxChange(this, 'label-ctx-subjects')">
                  <div class="aac-ctx-card-text">
                    <strong>Subjek Bawaan Katalog</strong>
                    <span>Menyelaraskan tajuk subjek awal dari Perpusnas</span>
                  </div>
                </label>
              </div>
            </div>
          </div>

        </main>
      </div>

      <div class="aac-modal-footer">
        <button type="button" class="aac-btn aac-btn-secondary" onclick="AAC.closeSettings()">Batal</button>
        <button type="button" class="aac-btn aac-btn-primary" id="btn-save-settings" onclick="AAC.saveSettings()">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
          <span>Simpan Pengaturan</span>
        </button>
      </div>
    </div>
  </div>

  <!-- Modal Panduan & Dokumentasi Lengkap -->
  <div class="aac-modal-overlay" id="aac-modal-guide" style="display:none;" onclick="if(event.target===this)AAC.closeGuide()">
    <div class="aac-modal aac-modal-guide">
      <div class="aac-modal-header">
        <div>
          <h3 class="aac-modal-title">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path></svg>
            <span>Buku Panduan & Dokumentasi Sistem</span>
          </h3>
          <p class="aac-modal-subtitle">Petunjuk komprehensif alur kerja copy-cataloguing cerdas, integrasi AI lokal/cloud, OCR, dan standar bibliografi Perpusnas RI.</p>
        </div>
        <button type="button" class="aac-modal-close" onclick="AAC.closeGuide()" title="Tutup Panduan">&times;</button>
      </div>

      <div class="aac-modal-layout">
        <!-- Sidebar Navigation -->
        <nav class="aac-guide-sidebar">
          <div style="padding:0.2rem 0.2rem 0.6rem 0.2rem;">
            <div style="position:relative;">
              <input type="text" class="aac-input" id="aac-guide-search" placeholder="Cari topik panduan..." style="font-size:0.75rem;padding:0.4rem 0.6rem 0.4rem 1.8rem;border-radius:6px;" oninput="AAC.filterGuide(this.value)">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="position:absolute;left:0.6rem;top:50%;transform:translateY(-50%);color:var(--aac-text-muted);pointer-events:none;"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
            </div>
          </div>

          <button type="button" class="aac-tab-btn active" id="guide-btn-workflow" onclick="AAC.switchGuideTab('guide-ch-workflow', this)">
            <div class="aac-tab-icon">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M9 11l3 3L22 4"></path><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path></svg>
            </div>
            <div class="aac-tab-text">
              <strong>1. Alur Kerja Katalog</strong>
              <span>5 Tahap Praktis</span>
            </div>
          </button>

          <button type="button" class="aac-tab-btn" id="guide-btn-ai" onclick="AAC.switchGuideTab('guide-ch-ai', this)">
            <div class="aac-tab-icon">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg>
            </div>
            <div class="aac-tab-text">
              <strong>2. Setup Model AI</strong>
              <span>Ollama, Model & Cloud</span>
            </div>
          </button>

          <button type="button" class="aac-tab-btn" id="guide-btn-ocr" onclick="AAC.switchGuideTab('guide-ch-ocr', this)">
            <div class="aac-tab-icon">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="3" y1="9" x2="21" y2="9"></line><line x1="9" y1="21" x2="9" y2="9"></line></svg>
            </div>
            <div class="aac-tab-text">
              <strong>3. Bukti Fisik & OCR</strong>
              <span>Foto Efektif & Tesseract</span>
            </div>
          </button>

          <button type="button" class="aac-tab-btn" id="guide-btn-callnumber" onclick="AAC.switchGuideTab('guide-ch-callnumber', this)">
            <div class="aac-tab-icon">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>
            </div>
            <div class="aac-tab-text">
              <strong>4. No. Panggil & Perpusnas</strong>
              <span>Pola DDC & Aturan Baku</span>
            </div>
          </button>

          <button type="button" class="aac-tab-btn" id="guide-btn-troubleshoot" onclick="AAC.switchGuideTab('guide-ch-troubleshoot', this)">
            <div class="aac-tab-icon">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
            </div>
            <div class="aac-tab-text">
              <strong>5. Solusi Kendala (FAQ)</strong>
              <span>Timeout, 403 & Error</span>
            </div>
          </button>
        </nav>

        <!-- Guide Main Content Area -->
        <main class="aac-guide-content">
          <!-- CHAPTER 1: WORKFLOW -->
          <article class="aac-guide-chapter active" id="guide-ch-workflow">
            <h2 class="aac-guide-h2">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"></path><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path></svg>
              <span>Bab 1: Alur Kerja Copy-Cataloguing Cerdas</span>
            </h2>
            <p class="aac-guide-intro">
              Plugin ini dirancang dengan prinsip <strong>Human-in-the-Loop</strong>: AI bertindak sebagai asisten cerdas yang mengekstraksi dan merekomendasikan klasifikasi, sementara Pustakawan adalah verifikator akhir yang menentukan kepastian data sebelum disimpan ke SLiMS.
            </p>

            <div class="aac-step-card">
              <div class="aac-step-num">1</div>
              <div class="aac-step-body">
                <div class="aac-step-title">Pencarian ISBN & Penarikan Metadata Otomatis</div>
                <div class="aac-step-desc">
                  Ketik 10 atau 13 digit nomor ISBN pada <em>Panel 1</em> lalu klik <strong>Cari Katalog</strong>. Sistem otomatis menarik rekaman bibliografi resmi dari <strong>Indonesia OneSearch (Perpusnas RI)</strong> dan menyinkronkan abstrak, sinopsis, serta cover dari <strong>Google Books API</strong>.
                </div>
              </div>
            </div>

            <div class="aac-step-card">
              <div class="aac-step-num">2</div>
              <div class="aac-step-body">
                <div class="aac-step-title">Pengunggahan Foto Bukti Fisik Buku</div>
                <div class="aac-step-desc">
                  Pada <em>Panel 2</em>, pilih target halaman (Halaman Judul, Halaman Hak Cipta, atau Daftar Isi), lalu unggah atau seret foto fisik buku. Mesin OCR lokal (Tesseract / Vision AI) akan mengekstrak teks asli buku sebagai bukti otentik.
                </div>
              </div>
            </div>

            <div class="aac-step-card">
              <div class="aac-step-num">3</div>
              <div class="aac-step-body">
                <div class="aac-step-title">Pemeriksaan Matriks Rekonsiliasi (Panel 4)</div>
                <div class="aac-step-desc">
                  Sistem menyandingkan data katalog eksternal (kolom kiri) dengan hasil ekstraksi bukti fisik (kolom tengah). Pustakawan cukup menekan tombol <strong>Pilih →</strong> untuk mengadopsi data, atau klik <strong>Setujui Semua dari Perpusnas</strong> untuk mempercepat proses.
                </div>
              </div>
            </div>

            <div class="aac-step-card">
              <div class="aac-step-num">4</div>
              <div class="aac-step-body">
                <div class="aac-step-title">Analisis Klasifikasi DDC & Pilihan Sudut Pandang Multi-Subjek</div>
                <div class="aac-step-desc">
                  Untuk karya yang memiliki topik ganda atau interdisipliner, sistem AI akan menganalisis judul, abstrak, dan daftar isi untuk menyajikan beberapa kandidat nomor klasifikasi DDC beserta alasan sudut pandangnya. Pustakawan bebas menentukan nomor kelas yang paling tepat untuk perpustakaannya.
                </div>
              </div>
            </div>

            <div class="aac-step-card">
              <div class="aac-step-num">5</div>
              <div class="aac-step-body">
                <div class="aac-step-title">Penyimpanan ke Pangkalan Data SLiMS</div>
                <div class="aac-step-desc">
                  Periksa ringkasan pada <em>Panel 3</em>. Jika semua field wajib telah terverifikasi, klik tombol <strong>Simpan ke Katalog SLiMS</strong>. Rekaman bibliografi, nomor panggil, tajuk pengarang, subjek, abstrak, dan berkas cover otomatis tersimpan di pangkalan data SLiMS.
                </div>
              </div>
            </div>
          </article>

          <!-- CHAPTER 2: SETUP AI -->
          <article class="aac-guide-chapter" id="guide-ch-ai">
            <h2 class="aac-guide-h2">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg>
              <span>Bab 2: Setup & Konfigurasi Model AI</span>
            </h2>
            <p class="aac-guide-intro">
              Sistem mendukung eksekusi AI <strong>100% Lokal (Ollama)</strong> untuk privasi penuh tanpa internet, serta <strong>Cloud AI (OpenAI / 9Router / Gemini)</strong> untuk kecepatan dan akurasi tinggi.
            </p>

            <div class="aac-settings-card">
              <div class="aac-settings-card-header">
                <span class="aac-settings-card-title">A. Menggunakan Ollama (AI Lokal di Komputer Anda)</span>
                <span class="aac-badge aac-badge-success">Gratis & Privasi Penuh</span>
              </div>
              <p style="font-size:0.78rem;color:var(--aac-text-muted);line-height:1.5;margin-bottom:0.75rem;">
                1. Unduh dan pasang Ollama untuk Windows dari situs resmi <strong>https://ollama.com</strong>.<br>
                2. Buka PowerShell atau Command Prompt (CMD), lalu jalankan perintah berikut untuk mengunduh model AI:
              </p>

              <div style="font-size:0.75rem;font-weight:600;color:var(--aac-text);">1. Model Cepat & Ringan untuk Klasifikasi Metadata Teks (Sangat Direkomendasikan):</div>
              <div class="aac-code-snippet">
                <code>ollama run gemma4:e4b</code>
                <button type="button" class="aac-btn-copy-code" onclick="AAC.copyCommand('ollama run gemma4:e4b', this)">Salin</button>
              </div>

              <div style="font-size:0.75rem;font-weight:600;color:var(--aac-text);margin-top:0.6rem;">2. Alternatif Model Teks Ringan Lainnya:</div>
              <div class="aac-code-snippet">
                <code>ollama run llama3.2</code>
                <button type="button" class="aac-btn-copy-code" onclick="AAC.copyCommand('ollama run llama3.2', this)">Salin</button>
              </div>

              <div style="font-size:0.75rem;font-weight:600;color:var(--aac-text);margin-top:0.6rem;">3. Model Multimodal Vision (Membaca Teks Langsung dari Foto Buku):</div>
              <div class="aac-code-snippet">
                <code>ollama run qwen3-vl:8b</code>
                <button type="button" class="aac-btn-copy-code" onclick="AAC.copyCommand('ollama run qwen3-vl:8b', this)">Salin</button>
              </div>

              <div class="aac-mode-banner" style="background:#eff6ff;border-color:#bfdbfe;color:#1e40af;margin-top:0.75rem;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                <span><strong>Tips Performa:</strong> Model 8B lokal membutuhkan waktu inferensi 30–80 detik. Pastikan nilai Timeout cURL di Pengaturan AI disetel ke minimal <strong>120 detik</strong>.</span>
              </div>
            </div>

            <div class="aac-settings-card">
              <div class="aac-settings-card-header">
                <span class="aac-settings-card-title">B. Menggunakan Cloud AI / 9Router / OpenAI API</span>
                <span class="aac-badge aac-badge-info">Respon Sangat Cepat</span>
              </div>
              <p style="font-size:0.78rem;color:var(--aac-text-muted);line-height:1.5;margin-bottom:0.75rem;">
                Jika Anda memiliki API Key OpenAI, 9Router, atau OpenRouter, ubah Mode AI menjadi <strong>Cloud AI</strong> di menu Pengaturan:
              </p>
              <ul style="font-size:0.78rem;color:var(--aac-text-muted);line-height:1.6;margin:0 0 0.75rem 1.25rem;">
                <li><strong>Base URL:</strong> <code>https://api.openai.com/v1</code> (atau <code>http://localhost:20128/v1</code> jika memakai 9Router)</li>
                <li><strong>Model Name:</strong> <code>gpt-4o-mini</code>, <code>glm-5.3-flash-free</code>, atau <code>gemini-2.5-flash</code></li>
              </ul>
            </div>

            <div style="text-align:right;margin-top:1rem;">
              <button type="button" class="aac-btn aac-btn-primary" onclick="AAC.openSettingsFromGuide()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                <span>Buka Pengaturan AI Sekarang</span>
              </button>
            </div>
          </article>

          <!-- CHAPTER 3: OCR -->
          <article class="aac-guide-chapter" id="guide-ch-ocr">
            <h2 class="aac-guide-h2">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="3" y1="9" x2="21" y2="9"></line><line x1="9" y1="21" x2="9" y2="9"></line></svg>
              <span>Bab 3: Pengambilan Bukti Fisik & OCR Tesseract</span>
            </h2>
            <p class="aac-guide-intro">
              Kualitas hasil ekstraksi teks otomatis sangat bergantung pada ketajaman dan sudut pengambilan foto bukti fisik buku.
            </p>

            <div class="aac-settings-card">
              <div class="aac-settings-card-header">
                <span class="aac-settings-card-title">Tips Pengambilan Foto Bukti Fisik yang Optimal</span>
              </div>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.85rem;">
                <div style="border:1px solid var(--aac-border);padding:0.75rem;border-radius:8px;background:#f8fafc;">
                  <strong style="font-size:0.8rem;color:var(--aac-text);display:block;margin-bottom:0.25rem;">✓ Sudut Tegak Lurus</strong>
                  <span style="font-size:0.74rem;color:var(--aac-text-muted);line-height:1.45;display:block;">Posisikan kamera ponsel sejajar dan tegak lurus di atas permukaan kertas buku agar teks tidak terdistorsi atau melengkung.</span>
                </div>
                <div style="border:1px solid var(--aac-border);padding:0.75rem;border-radius:8px;background:#f8fafc;">
                  <strong style="font-size:0.8rem;color:var(--aac-text);display:block;margin-bottom:0.25rem;">✓ Pencahayaan Merata</strong>
                  <span style="font-size:0.74rem;color:var(--aac-text-muted);line-height:1.45;display:block;">Gunakan cahaya yang terang dan rata. Hindari bayangan tangan/ponsel dan pantulan kilau lampu (*glare*) pada kertas art paper.</span>
                </div>
                <div style="border:1px solid var(--aac-border);padding:0.75rem;border-radius:8px;background:#f8fafc;">
                  <strong style="font-size:0.8rem;color:var(--aac-text);display:block;margin-bottom:0.25rem;">✓ Halaman Hak Cipta</strong>
                  <span style="font-size:0.74rem;color:var(--aac-text-muted);line-height:1.45;display:block;">Halaman balik judul (Katalog Dalam Terbitan / KDT) memuat data paling lengkap: ISBN, Tahun Terbit, Kota Terbit, dan Nama Penerbit.</span>
                </div>
                <div style="border:1px solid var(--aac-border);padding:0.75rem;border-radius:8px;background:#f8fafc;">
                  <strong style="font-size:0.8rem;color:var(--aac-text);display:block;margin-bottom:0.25rem;">✓ Daftar Isi / Sinopsis</strong>
                  <span style="font-size:0.74rem;color:var(--aac-text-muted);line-height:1.45;display:block;">Unggah foto daftar isi atau sampul belakang (blurb) agar AI dapat membaca abstrak serta topik bahasan buku secara akurat.</span>
                </div>
              </div>
            </div>

            <div class="aac-settings-card">
              <div class="aac-settings-card-header">
                <span class="aac-settings-card-title">Konfigurasi Tesseract OCR di Windows</span>
              </div>
              <p style="font-size:0.78rem;color:var(--aac-text-muted);line-height:1.5;">
                Plugin ini secara bawaan mendeteksi instalasi Tesseract di lingkungan Windows / Laragon.<br>
                Jika Anda menginstal Tesseract di direktori kustom, buka tab <strong>Mesin OCR</strong> di Pengaturan dan masukkan path lengkap binary, contoh:<br>
                <code>C:\Program Files\Tesseract-OCR\tesseract.exe</code>
              </p>
            </div>
          </article>

          <!-- CHAPTER 4: CALL NUMBER & PERPUSNAS -->
          <article class="aac-guide-chapter" id="guide-ch-callnumber">
            <h2 class="aac-guide-h2">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>
              <span>Bab 4: Format Nomor Panggil & Standar Perpusnas RI</span>
            </h2>
            <p class="aac-guide-intro">
              Panduan lengkap aturan baku penulisan nomor panggil (<em>call number</em>) koleksi menurut pedoman Perpustakaan Nasional RI dan cara kustomisasi polanya di SLiMS.
            </p>

            <div class="aac-settings-card">
              <div class="aac-settings-card-header">
                <span class="aac-settings-card-title">Standar Baku Perpustakaan Nasional RI</span>
                <span class="aac-badge aac-badge-info">Pola Baku: {class} {author} {title}</span>
              </div>
              <p style="font-size:0.78rem;color:var(--aac-text-muted);line-height:1.5;margin-bottom:0.75rem;">
                Menurut standar pengolahan bahan perpustakaan Perpusnas RI, nomor panggil terdiri dari 3 unsur utama yang dipisahkan oleh spasi:
              </p>
              <div style="background:#f8fafc;border:1px solid var(--aac-border);border-radius:8px;padding:0.85rem;margin-bottom:0.75rem;">
                <div style="font-family:monospace;font-size:1.1rem;font-weight:700;color:var(--aac-primary);margin-bottom:0.5rem;">
                  495.1 LIL b
                </div>
                <div style="font-size:0.78rem;color:var(--aac-text);line-height:1.6;">
                  • <code>495.1</code> = <strong>Nomor Klasifikasi DDC</strong> (Dewey Decimal Classification) untuk Bahasa Mandarin.<br>
                  • <code>LIL</code> = <strong>Tiga Huruf Pertama Pengarang</strong> (Lily Thamrin) ditulis dalam huruf <strong>KAPITAL</strong>.<br>
                  • <code>b</code> = <strong>Satu Huruf Pertama Judul</strong> (Belajar Bahasa Mandarin) ditulis dalam huruf <strong>KECIL</strong> (mengabaikan kata sandang <em>The, A, An, Sang, Si</em>).
                </div>
              </div>
            </div>

            <div class="aac-settings-card">
              <div class="aac-settings-card-header">
                <span class="aac-settings-card-title">Kustomisasi Pola Nomor Panggil untuk Perpustakaan Anda</span>
              </div>
              <p style="font-size:0.78rem;color:var(--aac-text-muted);line-height:1.5;margin-bottom:0.75rem;">
                Setiap perpustakaan memiliki kebijakan penomoran yang berbeda. Anda dapat menyusun pola kustom di tab <strong>Nomor Panggil & Klasifikasi</strong> menggunakan kombinasi token berikut:
              </p>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;font-size:0.76rem;">
                <div style="padding:0.4rem 0.6rem;background:#f8fafc;border:1px solid var(--aac-border);border-radius:6px;"><code>{class}</code> : Nomor Klasifikasi DDC</div>
                <div style="padding:0.4rem 0.6rem;background:#f8fafc;border:1px solid var(--aac-border);border-radius:6px;"><code>{author}</code> : 3 Huruf Pengarang (Kapital)</div>
                <div style="padding:0.4rem 0.6rem;background:#f8fafc;border:1px solid var(--aac-border);border-radius:6px;"><code>{title}</code> : 1 Huruf Judul (Kecil)</div>
                <div style="padding:0.4rem 0.6rem;background:#f8fafc;border:1px solid var(--aac-border);border-radius:6px;"><code>{year}</code> : Tahun Terbit (Contoh: 2024)</div>
                <div style="padding:0.4rem 0.6rem;background:#f8fafc;border:1px solid var(--aac-border);border-radius:6px;"><code>{edition}</code> : Edisi/Cetakan (Contoh: Cet. 1)</div>
                <div style="padding:0.4rem 0.6rem;background:#f8fafc;border:1px solid var(--aac-border);border-radius:6px;"><code>{publisher}</code> : Kode Singkat Penerbit</div>
                <div style="padding:0.4rem 0.6rem;background:#f8fafc;border:1px solid var(--aac-border);border-radius:6px;"><code>{copy}</code> : Nomor Eksemplar (c.1)</div>
                <div style="padding:0.4rem 0.6rem;background:#f8fafc;border:1px solid var(--aac-border);border-radius:6px;"><code>{item_code}</code> : Barcode Koleksi</div>
              </div>

              <div style="margin-top:0.75rem;padding:0.75rem;background:#eff6ff;border-radius:8px;font-size:0.76rem;color:#1e40af;">
                <strong>Contoh Kombinasi Perpustakaan Kampus:</strong><br>
                Pola: <code>{class}/{author}/{title}/{year} {copy}</code> → Hasil: <code>495.1/LIL/b/2024 c.1</code>
              </div>
            </div>
          </article>

          <!-- CHAPTER 5: TROUBLESHOOTING -->
          <article class="aac-guide-chapter" id="guide-ch-troubleshoot">
            <h2 class="aac-guide-h2">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
              <span>Bab 5: Solusi Kendala & Troubleshooting (FAQ)</span>
            </h2>
            <p class="aac-guide-intro">
              Daftar pertanyaan umum, penjelasan penyebab, serta panduan praktis mengatasi kendala teknis dalam operasional sistem.
            </p>

            <div class="aac-guide-faq-item">
              <div class="aac-guide-faq-q">
                <span class="aac-badge aac-badge-warning" style="font-size:0.65rem;">FAQ 1</span>
                <span>Mengapa proses klasifikasi AI terasa lama atau muncul error "Operation timed out"?</span>
              </div>
              <div class="aac-guide-faq-a">
                <strong>Penyebab:</strong> Model AI lokal berukuran 8B (seperti <code>qwen3-vl:8b</code>) membutuhkan waktu komputasi mendalam pada hardware Anda.<br>
                <strong>Solusi:</strong> Buka Pengaturan AI, naikkan batas waktu timeout menjadi <strong>120 detik</strong> atau <strong>180 detik</strong>. Sebagai alternatif, pasang model teks cepat seperti <code>ollama run gemma4:e4b</code> yang dapat menyelesaikan klasifikasi dalam 10 detik.
              </div>
            </div>

            <div class="aac-guide-faq-item">
              <div class="aac-guide-faq-q">
                <span class="aac-badge aac-badge-warning" style="font-size:0.65rem;">FAQ 2</span>
                <span>Mengapa Perpusnas OPAC SRU menampilkan status "HTTP 403 / Cloudflare Challenge"?</span>
              </div>
              <div class="aac-guide-faq-a">
                <strong>Penyebab:</strong> Pangkalan data OPAC Perpusnas dilindungi Web Application Firewall Cloudflare yang terkadang memblokir lalu lintas otomatis.<br>
                <strong>Solusi:</strong> Jangan khawatir! Plugin ini secara otomatis menggunakan <strong>Indonesia OneSearch (Perpusnas)</strong> yang terhubung langsung ke REST API Union Catalog nasional dan 100% bebas dari blokir Cloudflare.
              </div>
            </div>

            <div class="aac-guide-faq-item">
              <div class="aac-guide-faq-q">
                <span class="aac-badge aac-badge-warning" style="font-size:0.65rem;">FAQ 3</span>
                <span>Mengapa tombol "Simpan ke Katalog SLiMS" tidak bisa diklik / nonaktif?</span>
              </div>
              <div class="aac-guide-faq-a">
                <strong>Penyebab:</strong> Masih terdapat field data wajib yang berstatus konflik atau belum disetujui pustakawan.<br>
                <strong>Solusi:</strong> Periksa Matriks Rekonsiliasi pada Panel 4. Pastikan field utama seperti <em>Judul Buku</em>, <em>Pengarang</em>, dan <em>Nomor Klasifikasi</em> sudah disetujui (memiliki tanda centang hijau pada nilai final).
              </div>
            </div>

            <div class="aac-guide-faq-item">
              <div class="aac-guide-faq-q">
                <span class="aac-badge aac-badge-warning" style="font-size:0.65rem;">FAQ 4</span>
                <span>Hasil OCR Tesseract kosong atau teks yang terbaca berantakan?</span>
              </div>
              <div class="aac-guide-faq-a">
                <strong>Penyebab:</strong> Foto bukti fisik terlalu gelap, miring ekstrim, atau resolusi kamera buram.<br>
                <strong>Solusi:</strong> Ambil ulang foto dengan pencahayaan terang dan sudut tegak lurus. Jika perlu, pustakawan dapat langsung mengetik atau mengedit teks hasil pembacaan pada kolom bukti fisik secara manual.
              </div>
            </div>
          </article>
        </main>
      </div>

      <div class="aac-modal-footer">
        <button type="button" class="aac-btn aac-btn-secondary" onclick="AAC.closeGuide()">Tutup Panduan</button>
        <button type="button" class="aac-btn aac-btn-primary" onclick="AAC.openSettingsFromGuide()">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
          <span>Buka Pengaturan AI</span>
        </button>
      </div>
    </div>
  </div>

  <!-- Modal Preview Bukti Fisik -->
  <div class="aac-modal-overlay" id="aac-modal-evidence" style="display:none;" onclick="if(event.target===this)AAC.closePreview()">
    <div class="aac-modal" style="max-width:760px;">
      <div class="aac-modal-header">
        <h3 id="aac-preview-title">Pratinjau Bukti Fisik Buku</h3>
        <button type="button" class="aac-modal-close" onclick="AAC.closePreview()">&times;</button>
      </div>
      <div class="aac-modal-body" style="display:flex;gap:1.25rem;flex-wrap:wrap;">
        <div style="flex:1;min-width:240px;text-align:center;">
          <img id="aac-preview-img" src="" alt="Bukti Buku" style="max-width:100%;max-height:360px;border-radius:8px;border:1px solid var(--aac-border);object-fit:contain;background:#f8fafc;">
          <div style="margin-top:0.75rem;" id="aac-preview-badge"></div>
        </div>
        <div style="flex:1.2;min-width:260px;display:flex;flex-direction:column;">
          <label class="aac-form-label" style="display:flex;justify-content:space-between;align-items:center;">
            <span>Teks Hasil Ekstraksi OCR:</span>
            <button type="button" class="aac-btn aac-btn-secondary aac-btn-sm" onclick="AAC.copyOcrText()">Salin Teks</button>
          </label>
          <textarea id="aac-preview-ocr" class="aac-input" style="flex:1;min-height:220px;font-family:monospace;font-size:0.78rem;line-height:1.45;resize:vertical;" readonly></textarea>
        </div>
      </div>
      <div class="aac-modal-footer" style="justify-content:space-between;">
        <button type="button" class="aac-btn aac-btn-sm" style="background:#fee2e2;color:#991b1b;border:1px solid #fecdd3;" id="aac-btn-delete-current-evidence" onclick="AAC.deleteCurrentPreviewEvidence()">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
          Hapus Foto Bukti Ini
        </button>
        <button type="button" class="aac-btn aac-btn-secondary" onclick="AAC.closePreview()">Tutup</button>
      </div>
    </div>
  </div>

</div>

<script>
  window.AAC_CONFIG = {
    apiBase: '<?= $apiBaseUrl ?>',
    pluginBase: '<?= $pluginWebBase ?>',
    slimsBase: '<?= (defined('SWB') ? rtrim(SWB, '/') : '') . '/' ?>'
  };
  if (window.AAC && typeof window.AAC.init === 'function') {
    window.AAC.init();
  }
</script>
<script src="<?= $pluginWebBase ?>assets/js/app.js?v=3.2"></script>
