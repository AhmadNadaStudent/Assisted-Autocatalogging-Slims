/**
 * Assisted Autocataloguing - Frontend Controller
 * SLiMS 9 Bulian Integration
 */

window.AAC = {
  apiBase: (window.AAC_CONFIG && window.AAC_CONFIG.apiBase) ? window.AAC_CONFIG.apiBase : 'api.php',
  pluginBase: (window.AAC_CONFIG && window.AAC_CONFIG.pluginBase) ? window.AAC_CONFIG.pluginBase : '',
  slimsBase: (window.AAC_CONFIG && window.AAC_CONFIG.slimsBase) ? window.AAC_CONFIG.slimsBase : '/slims9_bulian-9.8.0/',
  csrfToken: '',
  currentPreviewId: null,
  _initialized: false,
  _evidenceCollapsed: false,
  showExtraFields: false,

  // 9 Field Utama yang paling sering dipakai pustakawan
  primaryFields: ['title', 'authors', 'publisher', 'publish_year', 'isbn', 'classification', 'call_number', 'subjects', 'notes'],

  state: {
    model: null,
    evidence: [],
    summary: {},
    callNumberTemplate: '{class} {author} {title}'
  },

  init() {
    if (this._initialized) return;
    this._initialized = true;

    this.bindEvents();
    this.loadStatus();
  },

  bindEvents() {
    // ISBN Search
    const searchBtn = document.getElementById('aac-btn-search-isbn');
    const isbnInput = document.getElementById('aac-input-isbn');
    if (searchBtn && isbnInput) {
      searchBtn.addEventListener('click', () => this.searchIsbn());
      isbnInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
          e.preventDefault();
          this.searchIsbn();
        }
      });
    }

    // Image Upload Zone
    const dropZone = document.getElementById('aac-upload-zone');
    const fileInput = document.getElementById('aac-file-input');
    if (dropZone && fileInput) {
      dropZone.addEventListener('click', (e) => {
        e.stopPropagation();
        fileInput.click();
      });
      dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.classList.add('dragover');
      });
      dropZone.addEventListener('dragleave', () => dropZone.classList.remove('dragover'));
      dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.classList.remove('dragover');
        if (e.dataTransfer.files && e.dataTransfer.files.length) {
          this.handleFiles(e.dataTransfer.files);
        }
      });
      fileInput.addEventListener('change', (e) => {
        if (e.target.files && e.target.files.length) {
          this.handleFiles(e.target.files);
        }
      });
    }

    // AI Re-extraction Button
    const runAiBtn = document.getElementById('aac-btn-run-ai');
    if (runAiBtn) {
      runAiBtn.addEventListener('click', () => this.runAiExtraction());
    }

    // Save Button
    const saveBtn = document.getElementById('aac-btn-save-slims');
    if (saveBtn) {
      saveBtn.addEventListener('click', () => this.saveToSlims());
    }

    // Reset Button (Header)
    const resetBtn = document.getElementById('aac-btn-reset');
    if (resetBtn) {
      resetBtn.addEventListener('click', () => this.resetSession());
    }

    // Close Modals on ESC key
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        this.closeSettings();
        this.closePreview();
        this.closeGuide();
      }
    });
  },

  toggleEvidencePanel() {
    const body = document.getElementById('aac-evidence-panel-body');
    const chevron = document.getElementById('aac-evidence-chevron');
    if (!body) return;

    this._evidenceCollapsed = !this._evidenceCollapsed;
    if (this._evidenceCollapsed) {
      body.style.display = 'none';
      if (chevron) chevron.style.transform = 'rotate(-90deg)';
    } else {
      body.style.display = 'block';
      if (chevron) chevron.style.transform = 'rotate(0deg)';
    }
  },

  toggleExtraFields() {
    this.showExtraFields = !this.showExtraFields;
    this.renderMetadataTable();
  },

  getUrl(action) {
    const glue = this.apiBase.includes('?') ? '&' : '?';
    return `${this.apiBase}${glue}action=${encodeURIComponent(action)}`;
  },

  async loadStatus() {
    try {
      const res = await fetch(this.getUrl('status'));
      const json = await res.json();
      if (json.status === 'success') {
        this.csrfToken = json.data.csrf_token;
        if (json.data.call_number_template) {
          this.state.callNumberTemplate = json.data.call_number_template;
        }
        this.renderStatusBadges(json.data);
      }
    } catch (err) {
      console.error('Gagal memuat status sistem:', err);
    }
  },

  renderStatusBadges(data) {
    const badgeContainer = document.getElementById('aac-status-badges');
    if (!badgeContainer) return;

    let html = '';
    // 1. Perpusnas OneSearch
    const onesearch = data.catalog?.onesearch;
    if (onesearch?.ok) {
      html += `<span class="aac-badge aac-badge-success" title="${onesearch.message}">● OneSearch Aktif</span>`;
    } else {
      html += `<span class="aac-badge aac-badge-warning" title="${onesearch?.message || 'Offline'}">▲ Perpusnas Terbatas</span>`;
    }

    // 2. Vision AI / OCR
    const driver = data.ocr_driver || 'auto';
    if (driver === 'vision_ai') {
      html += `<span class="aac-badge aac-badge-info" title="Membaca bukti fisik langsung menggunakan model Vision AI">● Vision AI Aktif</span>`;
    } else {
      html += `<span class="aac-badge aac-badge-info" title="Driver OCR / Vision: ${driver}">● OCR: ${driver.toUpperCase()}</span>`;
    }

    // 3. AI Status
    const aiStatus = data.ai_status;
    const aiMode = data.ai_mode || 'local_only';
    if (aiMode === 'disabled') {
      html += `<span class="aac-badge aac-badge-muted" title="AI dinonaktifkan di pengaturan">○ AI Nonaktif</span>`;
    } else if (aiStatus?.ok) {
      html += `<span class="aac-badge aac-badge-success" title="${aiStatus.message || 'Terkoneksi'}">● AI Terhubung</span>`;
    } else {
      html += `<span class="aac-badge aac-badge-danger" title="${aiStatus?.message || 'Gagal terhubung'}">▲ AI Terputus</span>`;
    }

    // 4. Model Aktif
    const activeModel = data.ai_model || 'Belum dipilih';
    html += `<span class="aac-badge aac-badge-muted" title="Model AI yang aktif">Model: ${this.escapeHtml(activeModel)}</span>`;

    badgeContainer.innerHTML = html;
  },

  async searchIsbn() {
    const input = document.getElementById('aac-input-isbn');
    const btn = document.getElementById('aac-btn-search-isbn');
    const alertBox = document.getElementById('aac-isbn-alert');
    const isbn = input ? input.value.trim() : '';

    if (!isbn) {
      this.showAlert(alertBox, 'Silakan masukkan nomor ISBN terlebih dahulu.', 'warning');
      return;
    }

    this.setButtonLoading(btn, true, 'Mencari...');
    this.hideAlert(alertBox);

    try {
      const formData = new FormData();
      formData.append('action', 'search_isbn');
      formData.append('isbn', isbn);

      const res = await fetch(this.apiBase, { method: 'POST', body: formData });
      const json = await res.json();

      if (json.status === 'success') {
        this.state.model = json.data.model;
        this.state.summary = json.data.summary;

        // Render mini Google Books card
        this.renderGoogleBooksCard(json.data.google_books, json.data.search_result?.metadata?.title);

        this.showAlert(alertBox, 'Data Perpusnas ditemukan: ' + (json.data.search_result?.metadata?.title || 'OK'), 'success');
        this.renderMetadataTable();
        this.renderSummary();

        // Otomatis jalankan klasifikasi AI di background
        this.runAiClassification();
      } else {
        this.showAlert(alertBox, json.message || 'Gagal mencari ISBN', 'danger');
      }
    } catch (err) {
      this.showAlert(alertBox, 'Terjadi kesalahan jaringan saat menghubungi server', 'danger');
    } finally {
      this.setButtonLoading(btn, false, '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg> <span>Cari</span>');
    }
  },

  renderGoogleBooksCard(gbooks, title) {
    const contentEl = document.getElementById('aac-gbooks-content');
    const imgEl = document.getElementById('aac-gbooks-img');
    const titleEl = document.getElementById('aac-gbooks-title');
    const descEl = document.getElementById('aac-gbooks-status-desc');
    const badgeEl = document.getElementById('aac-gbooks-badge');
    const linkEl = document.getElementById('aac-gbooks-link');

    if (!contentEl) return;

    if (!gbooks || !gbooks.found) {
      contentEl.style.display = 'none';
      return;
    }

    contentEl.style.display = 'block';

    if (imgEl) {
      if (gbooks.cover_file) {
        imgEl.src = `${this.slimsBase}images/docs/${gbooks.cover_file}`;
      } else if (gbooks.cover_url) {
        imgEl.src = gbooks.cover_url;
      } else if (gbooks.thumbnail_url) {
        imgEl.src = gbooks.thumbnail_url;
      } else {
        imgEl.src = '';
      }
    }

    if (titleEl) {
      titleEl.innerText = title || 'Buku Terdaftar';
    }

    if (descEl) {
      descEl.innerText = gbooks.cover_file ? 'Sampul tersimpan otomatis ke direktori SLiMS' : 'Sampul pratinjau Google Books';
    }

    if (badgeEl) {
      badgeEl.innerText = gbooks.preview_type === 'full' ? 'Pratinjau Penuh' : (gbooks.preview_type === 'partial' ? 'Pratinjau Tersedia' : 'Sampul Buku');
    }

    if (linkEl) {
      linkEl.href = gbooks.preview_url || '#';
      linkEl.style.display = gbooks.preview_url ? 'inline-flex' : 'none';
    }
  },

  async handleFiles(files) {
    const pageTypeSelect = document.getElementById('aac-page-type-select');
    const pageType = pageTypeSelect ? pageTypeSelect.value : 'title_page';
    const alertBox = document.getElementById('aac-upload-alert');
    const fileInput = document.getElementById('aac-file-input');

    this.hideAlert(alertBox);

    let hasSuccess = false;
    for (const file of Array.from(files)) {
      const formData = new FormData();
      formData.append('action', 'upload_image');
      formData.append('image', file);
      formData.append('page_type', pageType);

      try {
        const res = await fetch(this.apiBase, { method: 'POST', body: formData });
        const json = await res.json();
        if (json.status === 'success') {
          this.state.evidence = json.data.all_evidence;
          this.renderEvidenceList();
          hasSuccess = true;
        } else {
          this.showAlert(alertBox, json.message || 'Gagal mengunggah file', 'danger');
        }
      } catch (err) {
        this.showAlert(alertBox, 'Gagal mengunggah berkas gambar: ' + file.name, 'danger');
      }
    }

    // Reset file input agar jika memilih file yang sama bisa trigger change lagi
    if (fileInput) fileInput.value = '';

    // Otomatis jalankan ekstraksi AI saat ada foto baru yang diunggah
    if (hasSuccess && this.state.evidence && this.state.evidence.length > 0) {
      this.runAiExtraction();
    }
  },

  renderEvidenceList() {
    const container = document.getElementById('aac-evidence-list');
    const countBadge = document.getElementById('aac-evidence-count-badge');
    const reExtractBox = document.getElementById('aac-re-extract-box');
    const aiBtn = document.getElementById('aac-btn-run-ai');
    if (!container) return;

    const count = (this.state.evidence && this.state.evidence.length) || 0;
    if (countBadge) countBadge.innerText = `${count} Foto`;

    if (count === 0) {
      container.innerHTML = '<div style="color:var(--aac-text-subtle);font-size:0.75rem;text-align:center;padding:0.6rem;grid-column:1/-1;">Belum ada foto bukti fisik yang diunggah.</div>';
      if (reExtractBox) reExtractBox.style.display = 'none';
      if (aiBtn) aiBtn.disabled = true;
      this.renderMetadataTable();
      return;
    }

    if (reExtractBox) reExtractBox.style.display = 'block';
    if (aiBtn) aiBtn.disabled = false;

    let html = '';
    this.state.evidence.forEach((ev) => {
      const thumbUrl = ev.thumb_name ? `${this.pluginBase}storage/uploads/thumbs/${ev.thumb_name}` : `${this.pluginBase}storage/uploads/${ev.file_name}`;
      const pageLabel = this.getPageTypeLabel(ev.page_type);
      const textPreview = ev.ocr_text ? (ev.ocr_text.substring(0, 50) + '...') : '[Teks siap dibaca AI]';

      let statusBadge = '✓ OCR Siap';
      if (ev.ocr_status === 'ready_for_vision') {
        statusBadge = '⚡ Vision AI';
      } else if (ev.ocr_status === 'empty') {
        statusBadge = '○ Teks Kosong';
      }

      html += `
        <div class="aac-evidence-card" onclick="AAC.previewEvidence('${ev.evidence_id}')" title="Klik untuk lihat foto & koreksi teks\n${this.escapeHtml(textPreview)}">
          <button type="button" class="aac-evidence-delete" onclick="event.stopPropagation(); AAC.deleteEvidence('${ev.evidence_id}');" title="Hapus foto ini">&times;</button>
          <img src="${thumbUrl}" alt="${pageLabel}">
          <div class="aac-evidence-info">
            <strong>${pageLabel}</strong>
            <div style="font-size:0.65rem;color:var(--aac-text-muted);">${statusBadge}</div>
          </div>
        </div>
      `;
    });

    container.innerHTML = html;
    this.renderMetadataTable();
  },

  getPageTypeLabel(type) {
    const labels = {
      title_page: 'Halaman Judul',
      copyright_page: 'Hak Cipta (KDT)',
      toc: 'Daftar Isi',
      cover: 'Sampul Depan',
      back_cover: 'Sampul Belakang / Sinopsis',
      other: 'Halaman Lain'
    };
    return labels[type] || 'Halaman';
  },

  async deleteEvidence(evidenceId) {
    if (!confirm('Hapus foto bukti fisik ini dari sesi?')) {
      return;
    }

    try {
      const formData = new FormData();
      formData.append('action', 'delete_evidence');
      formData.append('evidence_id', evidenceId);

      const res = await fetch(this.apiBase, { method: 'POST', body: formData });
      const json = await res.json();
      if (json.status === 'success') {
        this.state.evidence = json.data.all_evidence;
        this.renderEvidenceList();
        this.closePreview();
      } else {
        alert(json.message || 'Gagal menghapus bukti');
      }
    } catch (e) {
      alert('Terjadi kesalahan saat menghapus bukti');
    }
  },

  previewEvidence(evidenceId) {
    const item = (this.state.evidence || []).find(e => e.evidence_id === evidenceId);
    if (!item) return;

    this.currentPreviewId = evidenceId;
    const modal = document.getElementById('aac-modal-evidence');
    const imgEl = document.getElementById('aac-preview-img');
    const ocrEl = document.getElementById('aac-preview-ocr');
    const badgeEl = document.getElementById('aac-preview-badge');
    const titleEl = document.getElementById('aac-preview-title');

    const fullUrl = `${this.pluginBase}storage/uploads/${item.file_name}`;
    if (imgEl) imgEl.src = fullUrl;
    if (ocrEl) ocrEl.value = item.ocr_text || '';
    if (badgeEl) {
      badgeEl.innerHTML = `<span class="aac-badge aac-badge-info">${this.getPageTypeLabel(item.page_type)}</span> <span class="aac-badge aac-badge-muted">${item.original_name || ''}</span>`;
    }
    if (titleEl) {
      titleEl.innerText = `Bukti Fisik: ${this.getPageTypeLabel(item.page_type)}`;
    }

    if (modal) modal.style.display = 'flex';
  },

  closePreview() {
    const modal = document.getElementById('aac-modal-evidence');
    if (modal) modal.style.display = 'none';
    this.currentPreviewId = null;
  },

  copyOcrText() {
    const ocrEl = document.getElementById('aac-preview-ocr');
    if (!ocrEl) return;
    navigator.clipboard.writeText(ocrEl.value).then(() => {
      this.showToast('Teks berhasil disalin ke clipboard!');
    }).catch(() => {
      ocrEl.select();
      document.execCommand('copy');
      this.showToast('Teks disalin!');
    });
  },

  async saveOcrTextEdit() {
    if (!this.currentPreviewId) return;
    const ocrEl = document.getElementById('aac-preview-ocr');
    const saveBtn = document.getElementById('btn-save-ocr-text');
    const textVal = ocrEl ? ocrEl.value : '';

    this.setButtonLoading(saveBtn, true, 'Menyimpan...');

    try {
      const formData = new FormData();
      formData.append('action', 'update_evidence_ocr');
      formData.append('evidence_id', this.currentPreviewId);
      formData.append('ocr_text', textVal);

      const res = await fetch(this.apiBase, { method: 'POST', body: formData });
      const json = await res.json();
      if (json.status === 'success') {
        this.state.evidence = json.data.all_evidence;
        this.renderEvidenceList();
        this.showToast('Teks bukti fisik berhasil disimpan!');
      } else {
        alert(json.message || 'Gagal menyimpan teks');
      }
    } catch (e) {
      alert('Terjadi kesalahan saat menyimpan teks');
    } finally {
      this.setButtonLoading(saveBtn, false, 'Simpan Teks');
    }
  },

  deleteCurrentPreviewEvidence() {
    if (this.currentPreviewId) {
      this.deleteEvidence(this.currentPreviewId);
    }
  },

  async runAiExtraction() {
    const btn = document.getElementById('aac-btn-run-ai');
    const alertBox = document.getElementById('aac-metadata-alert');

    this.setButtonLoading(btn, true, 'Mengekstraksi...');
    this.hideAlert(alertBox);

    try {
      const formData = new FormData();
      formData.append('action', 'run_ai_extraction');

      const res = await fetch(this.apiBase, { method: 'POST', body: formData });
      const json = await res.json();

      if (json.status === 'success') {
        this.state.model = json.data.model;
        this.state.summary = json.data.summary;
        this.renderMetadataTable();
        this.renderSummary();
        this.showAlert(alertBox, 'Ekstraksi bukti fisik selesai!', 'success');
      } else {
        this.showAlert(alertBox, json.message || 'Ekstraksi gagal', 'danger');
      }
    } catch (err) {
      this.showAlert(alertBox, 'Terjadi kesalahan saat memproses ekstraksi bukti', 'danger');
    } finally {
      this.setButtonLoading(btn, false, '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg> <span>Ekstraksi Ulang Bukti Fisik</span>');
    }
  },

  async runAiClassification() {
    const btn = document.getElementById('aac-btn-ai-classify');
    const alertBox = document.getElementById('aac-metadata-alert');

    this.setButtonLoading(btn, true, 'Menganalisis DDC...');
    this.hideAlert(alertBox);

    try {
      const formData = new FormData();
      formData.append('action', 'ai_classify');

      const res = await fetch(this.apiBase, { method: 'POST', body: formData });
      const json = await res.json();

      if (json.status === 'success') {
        this.state.model = json.data.model;
        if (json.data.summary) this.state.summary = json.data.summary;
        this.renderMetadataTable();
        this.renderSummary();
        this.showAlert(alertBox, 'Analisis DDC selesai! Nomor klasifikasi dan call number telah disesuaikan.', 'success');
      } else {
        this.showAlert(alertBox, json.message || 'Gagal analisis klasifikasi AI', 'danger');
      }
    } catch (err) {
      this.showAlert(alertBox, 'Terjadi kesalahan jaringan saat menghubungi AI', 'danger');
    } finally {
      this.setButtonLoading(btn, false, '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg> <span>Analisis DDC</span>');
    }
  },

  renderMetadataTable() {
    const tbody = document.getElementById('aac-metadata-tbody');
    const thEvidence = document.getElementById('th-evidence');
    const approveAllBtn = document.getElementById('aac-btn-approve-all');
    const classifyAiBtn = document.getElementById('aac-btn-ai-classify');
    if (!tbody || !this.state.model?.fields) return;

    if (approveAllBtn) approveAllBtn.style.display = 'inline-flex';
    if (classifyAiBtn) classifyAiBtn.style.display = 'inline-flex';

    const hasEvidence = Array.isArray(this.state.evidence) && this.state.evidence.length > 0;
    if (thEvidence) {
      thEvidence.style.display = hasEvidence ? '' : 'none';
    }

    const fields = this.state.model.fields;

    // Periksa apakah ada conflict di field sekunder
    let hasExtraConflict = false;
    for (const [key, f] of Object.entries(fields)) {
      if (!this.primaryFields.includes(key) && f.status === 'conflict') {
        hasExtraConflict = true;
        break;
      }
    }
    if (hasExtraConflict) {
      this.showExtraFields = true;
    }

    let html = '';

    const renderRow = (key, f) => {
      const isConflict = f.status === 'conflict';
      const isVerified = f.status === 'verified';
      const rowClass = isConflict ? 'aac-row-conflict' : (isVerified ? 'aac-row-verified' : '');

      let extDisplay = this.formatValue(f.external_value);
      let ocrDisplay = this.formatValue(f.ocr_value);
      let currentSelected = f.selected_value !== null ? this.formatValue(f.selected_value) : '';

      if (key === 'image') {
        const renderThumb = (v) => {
          if (!v) return '<span class="aac-text-empty">—</span>';
          const src = v.startsWith('http') ? v : `${this.slimsBase}images/docs/${v}`;
          return `<div style="display:flex;align-items:center;gap:0.5rem;"><img src="${src}" alt="Sampul" style="height:44px;border-radius:4px;box-shadow:0 1px 3px rgba(0,0,0,0.15);object-fit:cover;border:1px solid var(--aac-border);"> <span style="font-size:0.75rem;font-family:monospace;color:var(--aac-text);">${this.escapeHtml(v)}</span></div>`;
        };
        if (f.external_value) extDisplay = renderThumb(f.external_value);
        if (f.ocr_value) ocrDisplay = renderThumb(f.ocr_value);
      }

      let warningHtml = '';
      if (isConflict && f.conflict_reason) {
        warningHtml = `<div class="aac-conflict-pill"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg> ${this.escapeHtml(f.conflict_reason)}</div>`;
      } else if (f.status === 'external_only_unverified') {
        warningHtml = `<div class="aac-warning-hint"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg> Sumber Perpusnas</div>`;
      }

      const evidenceCellHtml = hasEvidence ? `
        <td class="aac-source-cell col-evidence">
          <div class="aac-source-pill ${ocrDisplay ? '' : 'is-empty'}">
            <div class="aac-source-text">${ocrDisplay ? this.escapeHtml(ocrDisplay) : '<span class="aac-text-empty">—</span>'}</div>
            ${ocrDisplay ? `<button type="button" class="aac-btn-pick" onclick="AAC.selectValue('${key}', 'ocr')" title="Pilih nilai dari bukti fisik">Pilih ➔</button>` : ''}
          </div>
        </td>
      ` : '';

      return `
        <tr class="${rowClass}">
          <td class="aac-field-cell">
            <div class="aac-field-name">${f.label}</div>
            ${this.renderStatusBadge(f.status)}
          </td>
          <td class="aac-source-cell">
            <div class="aac-source-pill ${extDisplay ? '' : 'is-empty'}">
              <div class="aac-source-text">${extDisplay ? this.escapeHtml(extDisplay) : '<span class="aac-text-empty">—</span>'}</div>
              ${extDisplay ? `<button type="button" class="aac-btn-pick" onclick="AAC.selectValue('${key}', 'external')" title="Pilih nilai dari Perpusnas">Pilih ➔</button>` : ''}
            </div>
          </td>
          ${evidenceCellHtml}
          <td class="aac-final-cell">
            <div class="aac-final-group">
              ${(() => {
                if (key === 'classification') {
                  const candidates = Array.isArray(f.candidates) ? f.candidates : [];
                  this.state.classificationCandidateMap = {};
                  let isMatched = false;
                  let currentReasoning = '';
                  let optHtml = '<option value="">-- Rekomendasi DDC --</option>';
                  candidates.forEach((cand) => {
                    const cVal = cand.class;
                    this.state.classificationCandidateMap[cVal] = cand;
                    const isSel = (cVal && currentSelected && cVal.toLowerCase() === currentSelected.toLowerCase());
                    if (isSel) {
                      isMatched = true;
                      currentReasoning = cand.reasoning || '';
                    }
                    const srcLabel = cand.source === 'external' ? 'Perpusnas' : 'AI';
                    const desc = cand.label ? ` - ${cand.label}` : '';
                    optHtml += `<option value="${this.escapeHtml(cVal)}" ${isSel ? 'selected' : ''}>[${srcLabel}] ${this.escapeHtml(cVal)}${this.escapeHtml(desc)}</option>`;
                  });
                  const isCustom = !isMatched && currentSelected !== '';
                  optHtml += `<option value="__custom__" ${isCustom ? 'selected' : ''}>✏️ Ketik Manual...</option>`;

                  return `
                    <div style="width:100%;">
                      <select class="aac-input aac-input-final" id="select-field-classification" onchange="AAC.onClassificationSelectChange(this.value)" style="margin-bottom:0.35rem;font-weight:600;">
                        ${optHtml}
                      </select>
                      <input type="text" class="aac-input aac-input-final" id="input-field-classification" value="${this.escapeHtml(currentSelected)}" oninput="AAC.onClassificationInputChange(this.value)" onchange="AAC.onFieldChange('classification', this.value)" placeholder="Ketik nomor DDC kustom..." style="${isCustom ? 'display:block;' : 'display:none;'}">
                      <div id="aac-classification-reasoning" style="${(currentReasoning || isCustom) ? 'display:flex;' : 'display:none;'}align-items:flex-start;gap:0.4rem;background:#f0f9ff;border:1px solid #bae6fd;color:#0369a1;border-radius:6px;padding:0.4rem 0.6rem;font-size:0.72rem;line-height:1.4;margin-top:0.35rem;">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;margin-top:1px;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                        <div id="aac-classification-reasoning-text">${this.escapeHtml(isCustom ? 'Pilihan Kustom: Masukkan nomor klasifikasi DDC secara manual.' : (currentReasoning ? ('Sudut Pandang: ' + currentReasoning) : ''))}</div>
                      </div>
                    </div>
                  `;
                } else if (key === 'notes') {
                  return `
                    <div style="width:100%;">
                      <textarea class="aac-input aac-input-final" id="input-field-notes" rows="2" onchange="AAC.onFieldChange('notes', this.value)" placeholder="Sinopsis / abstrak buku..." style="line-height:1.4;resize:vertical;font-size:0.78rem;">${this.escapeHtml(currentSelected)}</textarea>
                    </div>
                  `;
                } else {
                  let extraHint = '';
                  if (key === 'call_number') {
                    extraHint = `<div style="font-size:0.68rem;color:var(--aac-text-muted);margin-top:0.2rem;">Format: <code>${this.escapeHtml(this.state.callNumberTemplate || '{class} {author} {title}')}</code></div>`;
                  }
                  return `
                    <div style="width:100%;">
                      <input type="text" class="aac-input aac-input-final" id="input-field-${key}" value="${this.escapeHtml(currentSelected)}" onchange="AAC.onFieldChange('${key}', this.value)" placeholder="Nilai final...">
                      ${extraHint}
                    </div>
                  `;
                }
              })()}
              <button type="button" class="aac-btn-approve ${isVerified ? 'is-approved' : 'not-approved'}" onclick="AAC.approveField('${key}')" title="${isVerified ? 'Sudah disetujui' : 'Setujui nilai ini'}">
                ${isVerified ? '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg> OK' : 'Setujui'}
              </button>
            </div>
            ${warningHtml}
          </td>
        </tr>
      `;
    };

    // 1. Render Primary Fields
    this.primaryFields.forEach((key) => {
      if (fields[key]) {
        html += renderRow(key, fields[key]);
      }
    });

    // 2. Extra Fields Toggle Divider
    const extraFieldKeys = Object.keys(fields).filter(k => !this.primaryFields.includes(k));
    const colSpan = hasEvidence ? 4 : 3;

    html += `
      <tr class="aac-extra-fields-row">
        <td colspan="${colSpan}" style="padding:0.6rem 0.9rem;background:#f8fafc;border-top:1px solid var(--aac-border);border-bottom:1px solid var(--aac-border);text-align:center;">
          <button type="button" class="aac-btn aac-btn-secondary aac-btn-sm" onclick="AAC.toggleExtraFields()" style="font-size:0.75rem;padding:0.3rem 0.75rem;">
            ${this.showExtraFields ? '▲ Sembunyikan Field Tambahan' : `▼ Tampilkan ${extraFieldKeys.length} Field Tambahan (Edisi, Tempat Terbit, Kolasi, dll.)`}
          </button>
        </td>
      </tr>
    `;

    // 3. Render Extra Fields jika toggle dibuka
    if (this.showExtraFields) {
      extraFieldKeys.forEach((key) => {
        html += renderRow(key, fields[key]);
      });
    }

    tbody.innerHTML = html;
  },

  renderStatusBadge(status) {
    const map = {
      verified: '<span class="aac-badge aac-badge-success" style="font-size:0.65rem;">● Disetujui</span>',
      candidate: '<span class="aac-badge aac-badge-info" style="font-size:0.65rem;">● Kandidat</span>',
      conflict: '<span class="aac-badge aac-badge-warning" style="font-size:0.65rem;">▲ Konflik</span>',
      external_only_unverified: '<span class="aac-badge aac-badge-muted" style="font-size:0.65rem;">○ Perpusnas</span>',
      not_stated_in_examined_sources: '<span class="aac-badge aac-badge-muted" style="font-size:0.65rem;">○ Kosong</span>',
      not_checked: '<span class="aac-badge aac-badge-muted" style="font-size:0.65rem;">○ Baru</span>',
      rejected: '<span class="aac-badge aac-badge-danger" style="font-size:0.65rem;">✕ Ditolak</span>'
    };
    return map[status] || `<span class="aac-badge aac-badge-muted">${status}</span>`;
  },

  formatValue(val) {
    if (val === null || val === undefined || val === '') return '';
    if (Array.isArray(val)) return val.join('; ');
    return String(val);
  },

  selectValue(key, source) {
    const field = this.state.model.fields[key];
    if (!field) return;

    let targetVal = (source === 'external') ? field.external_value : field.ocr_value;
    if (Array.isArray(targetVal)) targetVal = targetVal.join('; ');

    const input = document.getElementById(`input-field-${key}`);
    if (input) {
      input.value = targetVal || '';
    }

    if (key === 'classification') {
      const select = document.getElementById('select-field-classification');
      if (select) {
        let matched = false;
        for (let i = 0; i < select.options.length; i++) {
          if (select.options[i].value.toLowerCase() === (targetVal || '').toLowerCase()) {
            select.selectedIndex = i;
            matched = true;
            break;
          }
        }
        if (!matched) {
          select.value = '__custom__';
          if (input) input.style.display = 'block';
        } else {
          if (input) input.style.display = 'none';
        }
      }
      this.updateCallNumberFromClassification(targetVal);
    }

    this.updateField(key, targetVal, 'verified');
    if (key === 'authors' || key === 'title') {
      this.autoCalculateCallNumber();
    }
  },

  onClassificationSelectChange(val) {
    const customInput = document.getElementById('input-field-classification');
    const reasoningBox = document.getElementById('aac-classification-reasoning');
    const reasoningText = document.getElementById('aac-classification-reasoning-text');

    if (val === '__custom__') {
      if (customInput) {
        customInput.style.display = 'block';
        customInput.focus();
      }
      if (reasoningBox && reasoningText) {
        reasoningBox.style.display = 'flex';
        reasoningText.innerText = 'Pilihan Kustom: Masukkan nomor klasifikasi DDC secara manual.';
      }
    } else {
      if (customInput) {
        customInput.style.display = 'none';
        customInput.value = val;
      }
      const cand = this.state.classificationCandidateMap?.[val];
      if (cand && cand.reasoning) {
        if (reasoningBox && reasoningText) {
          reasoningBox.style.display = 'flex';
          reasoningText.innerText = 'Sudut Pandang: ' + cand.reasoning;
        }
      } else {
        if (reasoningBox) reasoningBox.style.display = 'none';
      }
      this.updateCallNumberFromClassification(val);
      this.onFieldChange('classification', val);
    }
  },

  onClassificationInputChange(val) {
    this.updateCallNumberFromClassification(val);
  },

  formatCallNumber(data, template = null) {
    const tmpl = template || this.state.callNumberTemplate || '{class} {author} {title}';
    const classVal = (data.class || '').trim();
    const authorVal = (data.author || '').trim();
    const titleVal = (data.title || '').trim();
    const yearVal = (data.year || '').trim();
    const editionVal = (data.edition || '').trim();
    const pubVal = (data.publisher || '').trim();
    const copyVal = (data.copy || 'c.1').trim();
    const itemVal = (data.item_code || '').trim();

    // Standard Perpusnas: 3 huruf kapital pengarang, 1 huruf kecil judul
    const authorCode = authorVal ? authorVal.replace(/[^a-zA-Z]/g, '').substring(0, 3).toUpperCase() : 'XXX';
    const titleCode = titleVal ? titleVal.replace(/[^a-zA-Z]/g, '').substring(0, 1).toLowerCase() : '';
    const pubCode = pubVal ? pubVal.replace(/[^a-zA-Z]/g, '').substring(0, 4).toUpperCase() : '';

    let res = tmpl
      .replace(/\{class\}/gi, classVal)
      .replace(/\{author\}/gi, authorCode)
      .replace(/\{author_code\}/gi, authorCode)
      .replace(/\{title\}/gi, titleCode)
      .replace(/\{title_code\}/gi, titleCode)
      .replace(/\{year\}/gi, yearVal)
      .replace(/\{edition\}/gi, editionVal)
      .replace(/\{publisher\}/gi, pubCode)
      .replace(/\{copy\}/gi, copyVal)
      .replace(/\{copies\}/gi, copyVal)
      .replace(/\{item_code\}/gi, itemVal);

    return res.replace(/\s+/g, ' ').trim();
  },

  updateCallNumberFromClassification(classNumber) {
    if (!classNumber) return;
    const fields = this.state.model?.fields || {};

    let firstAuthor = '';
    const authVal = fields.authors?.selected_value || fields.authors?.ocr_value || fields.authors?.external_value;
    if (Array.isArray(authVal) && authVal.length > 0) {
      firstAuthor = typeof authVal[0] === 'object' ? (authVal[0].name || '') : String(authVal[0]);
    } else if (typeof authVal === 'string') {
      firstAuthor = authVal.split(';')[0];
    }

    const title = fields.title?.selected_value || fields.title?.ocr_value || fields.title?.external_value || '';
    const year = fields.publish_year?.selected_value || fields.publish_year?.ocr_value || fields.publish_year?.external_value || '';
    const edition = fields.edition?.selected_value || fields.edition?.ocr_value || fields.edition?.external_value || '';
    const publisher = fields.publisher?.selected_value || fields.publisher?.ocr_value || fields.publisher?.external_value || '';

    const newCallNumber = this.formatCallNumber({
      class: classNumber,
      author: firstAuthor,
      title: title,
      year: year,
      edition: edition,
      publisher: publisher
    });

    const callInput = document.getElementById('input-field-call_number');
    if (callInput) {
      callInput.value = newCallNumber;
    }
    this.updateField('call_number', newCallNumber, 'candidate', false);
  },

  autoCalculateCallNumber() {
    const classInput = document.getElementById('input-field-classification');
    const classSelect = document.getElementById('select-field-classification');
    const classVal = (classSelect && classSelect.value !== '__custom__' && classSelect.value) 
      ? classSelect.value 
      : (classInput ? classInput.value.trim() : '');

    if (classVal) {
      this.updateCallNumberFromClassification(classVal);
    }
  },

  insertCallNumberToken(token) {
    const input = document.getElementById('cfg-call-number-template');
    if (!input) return;
    const start = input.selectionStart !== undefined ? input.selectionStart : input.value.length;
    const end = input.selectionEnd !== undefined ? input.selectionEnd : input.value.length;
    const val = input.value;
    input.value = val.substring(0, start) + token + val.substring(end);
    input.focus();
    input.selectionStart = input.selectionEnd = start + token.length;
    this.updateCallNumberPreview();
  },

  updateCallNumberPreview() {
    const input = document.getElementById('cfg-call-number-template');
    const previewEl = document.getElementById('aac-call-number-live-preview');
    if (!input || !previewEl) return;

    let tmpl = input.value || '{class} {author} {title}';
    const sampleMap = {
      '{class}': '495.1',
      '{author}': 'LIL',
      '{title}': 'b',
      '{year}': '2024',
      '{edition}': 'Cet. 1',
      '{publisher}': 'GRAM',
      '{copy}': 'c.1',
      '{item_code}': 'B00123'
    };
    for (const [token, sample] of Object.entries(sampleMap)) {
      tmpl = tmpl.replaceAll(token, sample);
    }
    previewEl.innerText = tmpl.replace(/\s+/g, ' ').trim() || '-';
  },

  selectCatalogProvider(provider) {
    const select = document.getElementById('cfg-catalog-provider');
    if (select) select.value = provider;

    document.querySelectorAll('.aac-provider-card').forEach(c => c.classList.remove('active'));
    const activeCard = document.getElementById(provider === 'perpusnas_sru' ? 'card-prov-sru' : 'card-prov-onesearch');
    if (activeCard) activeCard.classList.add('active');
  },

  onCtxCheckboxChange(cb, labelId) {
    const label = document.getElementById(labelId);
    if (!label) return;
    if (cb.checked) {
      label.classList.add('checked');
    } else {
      label.classList.remove('checked');
    }
  },

  onFieldChange(key, value) {
    this.updateField(key, value, 'candidate');
    if (key === 'classification' || key === 'authors' || key === 'title') {
      this.autoCalculateCallNumber();
    }
  },

  approveField(key) {
    const input = document.getElementById(`input-field-${key}`);
    const val = input ? input.value : '';
    this.updateField(key, val, 'verified');
    if (key === 'classification' || key === 'authors' || key === 'title') {
      this.autoCalculateCallNumber();
    }
  },

  async approveAllExternal() {
    if (!this.state.model?.fields) return;
    const btn = document.getElementById('aac-btn-approve-all');
    this.setButtonLoading(btn, true, 'Menyetujui...');

    try {
      const updates = [];
      for (const [key, f] of Object.entries(this.state.model.fields)) {
        if (f.external_value) {
          let val = f.external_value;
          if (Array.isArray(val)) val = val.join('; ');
          const input = document.getElementById(`input-field-${key}`);
          if (input) input.value = val;
          updates.push(this.updateField(key, val, 'verified', false));
        }
      }
      await Promise.all(updates);
      this.renderMetadataTable();
      this.renderSummary();
    } finally {
      this.setButtonLoading(btn, false, '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="20 6 9 17 4 12"></polyline></svg> <span>Setujui Semua Perpusnas</span>');
    }
  },

  async updateField(key, value, status, shouldRerender = true) {
    try {
      const formData = new FormData();
      formData.append('action', 'update_field');
      formData.append('field', key);
      formData.append('value', value);
      formData.append('status', status);

      const res = await fetch(this.apiBase, { method: 'POST', body: formData });
      const json = await res.json();
      if (json.status === 'success') {
        this.state.model.fields[key] = json.data.field;
        this.state.summary = json.data.summary;
        if (shouldRerender) {
          this.renderMetadataTable();
          this.renderSummary();
        }
      }
    } catch (err) {
      console.error('Gagal memperbarui field:', err);
    }
  },

  renderSummary() {
    const s = this.state.summary || {};
    const setVal = (id, val) => {
      const el = document.getElementById(id);
      if (el) el.innerText = val || 0;
    };

    setVal('aac-stat-verified', s.verified);
    setVal('aac-stat-conflict', s.conflict);
    setVal('aac-stat-unverified', s.external_only_unverified);
  },

  async saveToSlims() {
    const btn = document.getElementById('aac-btn-save-slims');
    const alertBox = document.getElementById('aac-storage-alert');

    if (!confirm('Apakah Anda yakin ingin menyimpan metadata ini ke basis data bibliografi SLiMS?')) {
      return;
    }

    this.setButtonLoading(btn, true, 'Menyimpan...');
    this.hideAlert(alertBox);

    try {
      const formData = new FormData();
      formData.append('action', 'save_to_slims');
      formData.append('csrf_token', this.csrfToken);

      const res = await fetch(this.apiBase, { method: 'POST', body: formData });
      const json = await res.json();

      if (json.status === 'success') {
        const biblioId = json.data?.biblio_id || '-';
        const title = json.data?.title || '';

        alertBox.className = 'aac-alert aac-alert-success';
        alertBox.style.display = 'block';
        alertBox.innerHTML = `
          <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;width:100%;">
            <div>
              <div style="font-weight:700;font-size:0.9rem;display:flex;align-items:center;gap:0.4rem;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                Berhasil Tersimpan ke SLiMS!
              </div>
              <div style="margin-top:0.25rem;font-size:0.82rem;line-height:1.4;">
                Bibliografi tersimpan dengan ID <strong>#${this.escapeHtml(biblioId)}</strong>: "<em>${this.escapeHtml(title)}</em>". Indeks pencarian SLiMS telah diperbarui.
              </div>
            </div>
            <button type="button" class="aac-btn aac-btn-success aac-btn-sm" onclick="AAC.resetSession(true)" style="margin-top:2px;">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="1 4 1 10 7 10"></polyline><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path></svg>
              <span>+ Input Buku Baru</span>
            </button>
          </div>
        `;

        alertBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      } else {
        this.showAlert(alertBox, json.message || 'Gagal menyimpan ke SLiMS', 'danger');
      }
    } catch (err) {
      this.showAlert(alertBox, 'Terjadi kesalahan komunikasi dengan basis data', 'danger');
    } finally {
      this.setButtonLoading(btn, false, '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg> <span>Simpan Final ke SLiMS</span>');
    }
  },

  async resetSession(silent = false) {
    if (!silent) {
      if (!confirm('Mereset sesi akan mengosongkan gambar dan metadata saat ini. Lanjutkan?')) {
        return;
      }
    }

    try {
      await fetch(this.getUrl('reset_session'));
    } catch (e) {
      console.warn('Gagal reset sesi backend:', e);
    }

    this.state = {
      model: null,
      evidence: [],
      summary: { total: 0, verified: 0, candidate: 0, conflict: 0, external_only_unverified: 0 }
    };

    const isbnInput = document.getElementById('aac-input-isbn');
    if (isbnInput) {
      isbnInput.value = '';
      isbnInput.focus();
    }
    const fileInput = document.getElementById('aac-file-input');
    if (fileInput) fileInput.value = '';

    this.hideAlert(document.getElementById('aac-isbn-alert'));
    this.hideAlert(document.getElementById('aac-upload-alert'));
    this.hideAlert(document.getElementById('aac-metadata-alert'));
    this.hideAlert(document.getElementById('aac-storage-alert'));

    // Reset cover preview card
    this.renderGoogleBooksCard(null);

    // Clear evidence list
    this.renderEvidenceList();

    // Reset table
    const tbody = document.getElementById('aac-metadata-tbody');
    if (tbody) {
      tbody.innerHTML = `
        <tr>
          <td colspan="4" style="text-align:center;padding:3rem 1.5rem;color:var(--aac-text-subtle);">
            Silakan masukkan ISBN di panel kiri untuk memulai proses verifikasi katalog.
          </td>
        </tr>
      `;
    }

    const approveAllBtn = document.getElementById('aac-btn-approve-all');
    if (approveAllBtn) approveAllBtn.style.display = 'none';
    const classifyAiBtn = document.getElementById('aac-btn-ai-classify');
    if (classifyAiBtn) classifyAiBtn.style.display = 'none';

    this.renderSummary();
    this.loadStatus();
  },

  /* Settings Modal Handlers */
  async openSettings() {
    const modal = document.getElementById('aac-modal-settings');
    const alertBox = document.getElementById('aac-settings-alert');
    this.hideAlert(alertBox);

    try {
      const res = await fetch(this.getUrl('get_settings'));
      const json = await res.json();
      if (json.status === 'success') {
        const s = json.data;

        const aiMode = document.getElementById('cfg-ai-mode');
        if (aiMode) aiMode.value = s.ai.mode || 'local_only';

        const aiProv = document.getElementById('cfg-ai-default-provider');
        if (aiProv) aiProv.value = s.ai.default_provider || 'ollama';

        const ollamaUrl = document.getElementById('cfg-ollama-url');
        if (ollamaUrl) ollamaUrl.value = s.ai.ollama?.base_url || 'http://localhost:11434';

        const ollamaModel = document.getElementById('cfg-ollama-model');
        if (ollamaModel) ollamaModel.value = s.ai.ollama?.model || 'llama3.2';

        const ollamaTimeout = document.getElementById('cfg-ollama-timeout');
        if (ollamaTimeout) ollamaTimeout.value = s.ai.ollama?.timeout || 60;

        const cloudUrl = document.getElementById('cfg-cloud-url');
        if (cloudUrl) cloudUrl.value = s.ai.openai_compatible?.base_url || 'https://api.openai.com/v1';

        const cloudKey = document.getElementById('cfg-cloud-key');
        if (cloudKey) {
          cloudKey.value = s.ai.openai_compatible?.api_key || '';
          cloudKey.placeholder = s.ai.openai_compatible?.has_key ? 'Sudah tersimpan (isi baru untuk mengganti)' : 'Masukkan API Key';
        }

        const cloudModel = document.getElementById('cfg-cloud-model');
        if (cloudModel) cloudModel.value = s.ai.openai_compatible?.model || 'gpt-4o-mini';

        const cloudTimeout = document.getElementById('cfg-cloud-timeout');
        if (cloudTimeout) cloudTimeout.value = s.ai.openai_compatible?.timeout || 45;

        const ocrDriver = document.getElementById('cfg-ocr-driver');
        if (ocrDriver) ocrDriver.value = s.ocr?.driver || 'auto';

        const ocrTess = document.getElementById('cfg-ocr-tesseract');
        if (ocrTess) ocrTess.value = s.ocr?.tesseract_binary || 'tesseract';

        const ocrPy = document.getElementById('cfg-ocr-python');
        if (ocrPy) ocrPy.value = s.ocr?.python_binary || 'python';

        const ocrLang = document.getElementById('cfg-ocr-lang');
        if (ocrLang) ocrLang.value = s.ocr?.languages || 'ind+eng';

        const catProv = document.getElementById('cfg-catalog-provider');
        const defaultProv = s.catalog?.default_provider || 'onesearch';
        if (catProv) catProv.value = defaultProv;
        this.selectCatalogProvider(defaultProv);

        const callNumberTmpl = document.getElementById('cfg-call-number-template');
        if (callNumberTmpl) callNumberTmpl.value = s.slims?.call_number_template || '{class} {author} {title}';
        this.updateCallNumberPreview();

        const ctxSources = s.classification?.context_sources || ['title', 'abstract', 'toc', 'subjects'];
        ['title', 'abstract', 'toc', 'subjects'].forEach(src => {
          const el = document.getElementById(`cfg-ctx-${src}`);
          const isChecked = ctxSources.includes(src);
          if (el) el.checked = isChecked;
          const label = document.getElementById(`label-ctx-${src}`);
          if (label) label.classList.toggle('checked', isChecked);
        });

        this.onAiModeChange(s.ai.mode || 'local_only');
      }
    } catch (e) {
      console.warn('Gagal memuat konfigurasi:', e);
    }

    if (modal) modal.style.display = 'flex';
  },

  closeSettings() {
    const modal = document.getElementById('aac-modal-settings');
    if (modal) modal.style.display = 'none';
  },

  openGuide() {
    const modal = document.getElementById('aac-modal-guide');
    if (modal) modal.style.display = 'flex';
  },

  closeGuide() {
    const modal = document.getElementById('aac-modal-guide');
    if (modal) modal.style.display = 'none';
  },

  switchGuideTab(chapterId, btn) {
    const sidebar = document.querySelector('.aac-guide-sidebar');
    if (sidebar) {
      sidebar.querySelectorAll('.aac-tab-btn').forEach(b => b.classList.remove('active'));
    }
    document.querySelectorAll('.aac-guide-chapter').forEach(c => c.classList.remove('active'));

    if (btn) btn.classList.add('active');
    const chapter = document.getElementById(chapterId);
    if (chapter) chapter.classList.add('active');
  },

  openSettingsFromGuide() {
    this.closeGuide();
    this.openSettings();
  },

  async copyCommand(text, btn) {
    try {
      if (navigator.clipboard && navigator.clipboard.writeText) {
        await navigator.clipboard.writeText(text);
      } else {
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
      }

      if (btn) {
        const origText = btn.innerText;
        btn.innerText = '✓ Tersalin!';
        btn.style.background = '#16a34a';
        btn.style.color = '#ffffff';
        setTimeout(() => {
          btn.innerText = origText;
          btn.style.background = '';
          btn.style.color = '';
        }, 2000);
      }
      this.showToast(`Perintah "${text}" berhasil disalin!`);
    } catch (e) {
      alert('Gagal menyalin perintah: ' + text);
    }
  },

  showToast(message) {
    let toast = document.getElementById('aac-global-toast');
    if (!toast) {
      toast = document.createElement('div');
      toast.id = 'aac-global-toast';
      toast.className = 'aac-toast';
      document.body.appendChild(toast);
    }
    toast.innerHTML = `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#38bdf8" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg> <span>${this.escapeHtml(message)}</span>`;
    toast.style.display = 'flex';
    clearTimeout(this._toastTimer);
    this._toastTimer = setTimeout(() => {
      if (toast) toast.style.display = 'none';
    }, 2800);
  },

  filterGuide(query) {
    const q = (query || '').toLowerCase().trim();
    const chapters = document.querySelectorAll('.aac-guide-chapter');
    const buttons = document.querySelectorAll('.aac-guide-sidebar .aac-tab-btn');

    if (!q) {
      buttons.forEach(b => b.style.display = 'flex');
      return;
    }

    buttons.forEach((btn, idx) => {
      const chapter = chapters[idx];
      const text = (btn.innerText + ' ' + (chapter ? chapter.innerText : '')).toLowerCase();
      if (text.includes(q)) {
        btn.style.display = 'flex';
      } else {
        btn.style.display = 'none';
      }
    });

    for (let i = 0; i < buttons.length; i++) {
      if (buttons[i].style.display !== 'none') {
        const targetId = chapters[i]?.id;
        if (targetId) {
          this.switchGuideTab(targetId, buttons[i]);
        }
        break;
      }
    }
  },

  switchTab(tabId, btn) {
    document.querySelectorAll('.aac-tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.aac-tab-content').forEach(c => c.classList.remove('active'));

    if (btn) btn.classList.add('active');
    const content = document.getElementById(tabId);
    if (content) content.classList.add('active');
  },

  onAiModeChange(mode) {
    const secOllama = document.getElementById('sec-ollama');
    const secCloud = document.getElementById('sec-cloud');
    const modeDesc = document.getElementById('aac-mode-desc');
    const modeBanner = document.getElementById('aac-mode-banner');

    if (secOllama) secOllama.style.opacity = (mode === 'disabled') ? '0.45' : '1';
    if (secCloud) secCloud.style.opacity = (mode === 'local_only' || mode === 'disabled') ? '0.45' : '1';

    const descs = {
      local_only: 'Mode Local-Only: Semua pemrosesan data dilakukan 100% di komputer lokal via Ollama tanpa mengirim data keluar.',
      cloud: 'Mode Cloud AI: Ekstraksi dan klasifikasi diproses via API Cloud berkecepatan tinggi (OpenAI/9Router/Gemini).',
      hybrid: 'Mode Hybrid: Pembacaan gambar/OCR lokal, sedangkan analisis subjek & DDC dikonsultasikan ke model AI.',
      disabled: 'Mode Disabled: AI dinonaktifkan sepenuhnya. Pemetaan mengandalkan aturan bibliografi standar dan regex.'
    };

    if (modeDesc) modeDesc.innerText = descs[mode] || descs.local_only;
    if (modeBanner) {
      modeBanner.style.background = (mode === 'disabled') ? '#f8fafc' : ((mode === 'cloud') ? '#eff6ff' : '#f0fdf4');
      modeBanner.style.color = (mode === 'disabled') ? '#64748b' : ((mode === 'cloud') ? '#1e40af' : '#166534');
      modeBanner.style.borderColor = (mode === 'disabled') ? '#e2e8f0' : ((mode === 'cloud') ? '#bfdbfe' : '#bbf7d0');
    }
  },

  async testOllama() {
    const btn = document.getElementById('btn-test-ollama');
    const resBox = document.getElementById('res-test-ollama');
    const url = document.getElementById('cfg-ollama-url')?.value || 'http://localhost:11434';

    this.setButtonLoading(btn, true, 'Menguji...');
    if (resBox) resBox.style.display = 'none';

    try {
      const formData = new FormData();
      formData.append('action', 'test_ai');
      formData.append('provider', 'ollama');
      formData.append('base_url', url);

      const res = await fetch(this.apiBase, { method: 'POST', body: formData });
      const json = await res.json();

      if (json.status === 'success' && json.data.ok) {
        if (resBox) {
          resBox.className = 'aac-test-result aac-badge-success';
          resBox.style.display = 'block';
          const modelList = json.data.models ? json.data.models.join(', ') : '-';
          resBox.innerText = `✓ Terkoneksi ke Ollama! Model tersedia: ${modelList}`;

          const dl = document.getElementById('ollama-model-list');
          if (dl && json.data.models) {
            dl.innerHTML = json.data.models.map(m => `<option value="${m}">`).join('');
          }
        }
      } else {
        if (resBox) {
          resBox.className = 'aac-test-result aac-badge-danger';
          resBox.style.display = 'block';
          resBox.innerText = `✕ ${json.data?.message || json.message || 'Gagal terhubung ke Ollama'}`;
        }
      }
    } catch (e) {
      if (resBox) {
        resBox.className = 'aac-test-result aac-badge-danger';
        resBox.style.display = 'block';
        resBox.innerText = '✕ Kesalahan jaringan saat menghubungi backend';
      }
    } finally {
      this.setButtonLoading(btn, false, 'Tes Koneksi Ollama');
    }
  },

  async testCloudAi() {
    const btn = document.getElementById('btn-test-cloud');
    const resBox = document.getElementById('res-test-cloud');
    const url = document.getElementById('cfg-cloud-url')?.value || 'https://api.openai.com/v1';
    const key = document.getElementById('cfg-cloud-key')?.value || '';
    const model = document.getElementById('cfg-cloud-model')?.value || 'gpt-4o-mini';

    this.setButtonLoading(btn, true, 'Menguji...');
    if (resBox) resBox.style.display = 'none';

    try {
      const formData = new FormData();
      formData.append('action', 'test_ai');
      formData.append('provider', 'openai_compatible');
      formData.append('base_url', url);
      formData.append('api_key', key);
      formData.append('model', model);

      const res = await fetch(this.apiBase, { method: 'POST', body: formData });
      const json = await res.json();

      if (json.status === 'success' && json.data.ok) {
        if (resBox) {
          resBox.className = 'aac-test-result aac-badge-success';
          resBox.style.display = 'block';
          resBox.innerText = `✓ ${json.data.message || 'Terkoneksi dengan sukses ke Cloud AI / 9Router!'}`;
        }
      } else {
        if (resBox) {
          resBox.className = 'aac-test-result aac-badge-danger';
          resBox.style.display = 'block';
          resBox.innerText = `✕ ${json.data?.message || json.message || 'Gagal koneksi ke Cloud AI'}`;
        }
      }
    } catch (e) {
      if (resBox) {
        resBox.className = 'aac-test-result aac-badge-danger';
        resBox.style.display = 'block';
        resBox.innerText = '✕ Kesalahan jaringan saat menghubungi backend';
      }
    } finally {
      this.setButtonLoading(btn, false, 'Tes Koneksi Cloud');
    }
  },

  async saveSettings() {
    const btn = document.getElementById('btn-save-settings');
    const alertBox = document.getElementById('aac-settings-alert');

    this.setButtonLoading(btn, true, 'Menyimpan...');
    this.hideAlert(alertBox);

    try {
      const formData = new FormData();
      formData.append('action', 'save_settings');
      formData.append('csrf_token', this.csrfToken);

      formData.append('ai_mode', document.getElementById('cfg-ai-mode')?.value || 'local_only');
      formData.append('ai_default_provider', document.getElementById('cfg-ai-default-provider')?.value || 'ollama');

      formData.append('ollama_base_url', document.getElementById('cfg-ollama-url')?.value || '');
      formData.append('ollama_model', document.getElementById('cfg-ollama-model')?.value || '');
      formData.append('ollama_timeout', document.getElementById('cfg-ollama-timeout')?.value || '60');

      formData.append('cloud_base_url', document.getElementById('cfg-cloud-url')?.value || '');
      formData.append('cloud_api_key', document.getElementById('cfg-cloud-key')?.value || '');
      formData.append('cloud_model', document.getElementById('cfg-cloud-model')?.value || '');
      formData.append('cloud_timeout', document.getElementById('cfg-cloud-timeout')?.value || '45');

      formData.append('ocr_driver', document.getElementById('cfg-ocr-driver')?.value || 'auto');
      formData.append('ocr_tesseract_binary', document.getElementById('cfg-ocr-tesseract')?.value || 'tesseract');
      formData.append('ocr_python_binary', document.getElementById('cfg-ocr-python')?.value || 'python');
      formData.append('ocr_languages', document.getElementById('cfg-ocr-lang')?.value || 'ind+eng');

      formData.append('catalog_default_provider', document.getElementById('cfg-catalog-provider')?.value || 'onesearch');
      formData.append('call_number_template', document.getElementById('cfg-call-number-template')?.value || '{class} {author} {title}');

      const selectedCtx = [];
      ['title', 'abstract', 'toc', 'subjects'].forEach(src => {
        const el = document.getElementById(`cfg-ctx-${src}`);
        if (el && el.checked) selectedCtx.push(src);
      });
      formData.append('classification_sources', selectedCtx.join(','));

      const res = await fetch(this.apiBase, { method: 'POST', body: formData });
      const json = await res.json();

      if (json.status === 'success') {
        if (json.data?.config?.slims?.call_number_template) {
          this.state.callNumberTemplate = json.data.config.slims.call_number_template;
        }
        this.showAlert(alertBox, 'Pengaturan berhasil disimpan!', 'success');
        this.loadStatus();
        setTimeout(() => this.closeSettings(), 1000);
      } else {
        this.showAlert(alertBox, json.message || 'Gagal menyimpan pengaturan', 'danger');
      }
    } catch (e) {
      this.showAlert(alertBox, 'Terjadi kesalahan saat menyimpan pengaturan', 'danger');
    } finally {
      this.setButtonLoading(btn, false, '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg> <span>Simpan Pengaturan</span>');
    }
  },

  showAlert(el, msg, type = 'info') {
    if (!el) return;
    const icons = {
      success: '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>',
      danger: '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>',
      warning: '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>',
      info: '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>'
    };
    el.className = `aac-alert aac-alert-${type}`;
    el.style.display = 'flex';
    el.innerHTML = `${icons[type] || icons.info} <span>${this.escapeHtml(msg)}</span>`;
  },

  hideAlert(el) {
    if (el) {
      el.style.display = 'none';
      el.innerHTML = '';
    }
  },

  setButtonLoading(btn, loading, text) {
    if (!btn) return;
    btn.disabled = loading;
    btn.innerHTML = loading ? `<span class="aac-spinner"></span> ${text}` : text;
  },

  escapeHtml(str) {
    if (!str) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }
};

// Global alias
const AAC = window.AAC;

// Auto-run when DOM ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => AAC.init());
} else {
  AAC.init();
}
