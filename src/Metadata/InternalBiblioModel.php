<?php
namespace AssistedAutocataloguing\Metadata;

/**
 * Independent Internal Bibliographic Model
 */
class InternalBiblioModel
{
    private array $fields = [];
    private EvidenceLedger $evidenceLedger;
    private string $sessionId;
    private string $createdAt;
    private string $updatedAt;

    public function __construct(?string $sessionId = null)
    {
        $this->sessionId = $sessionId ?? bin2hex(random_bytes(16));
        $this->evidenceLedger = new EvidenceLedger();
        $this->createdAt = date('Y-m-d H:i:s');
        $this->updatedAt = $this->createdAt;
        $this->initializeDefaultFields();
    }

    private function initializeDefaultFields(): void
    {
        $fieldKeys = [
            'title' => 'Judul Buku',
            'sor' => 'Pernyataan Tanggung Jawab',
            'authors' => 'Pengarang / Kontributor',
            'edition' => 'Edisi',
            'publisher' => 'Penerbit',
            'publish_place' => 'Tempat Terbit',
            'publish_year' => 'Tahun Terbit',
            'isbn' => 'ISBN',
            'collation' => 'Deskripsi Fisik / Halaman',
            'series_title' => 'Judul Seri',
            'classification' => 'Nomor Klasifikasi (DDC)',
            'call_number' => 'Nomor Panggil',
            'image' => 'Sampul Buku (Cover)',
            'subjects' => 'Subjek / Topik',
            'language' => 'Bahasa',
            'notes' => 'Abstrak / Sinopsis',
        ];

        foreach ($fieldKeys as $key => $label) {
            $this->fields[$key] = [
                'key' => $key,
                'label' => $label,
                'external_value' => null,
                'ocr_value' => null,
                'selected_value' => null,
                'candidates' => [],
                'status' => 'not_checked',
                'confidence' => 0.0,
                'evidence_ids' => [],
                'conflict_reason' => null,
                'user_modified' => false,
            ];
        }
    }

    public function setFieldData(string $key, array $data): void
    {
        if (isset($this->fields[$key])) {
            $this->fields[$key] = array_merge($this->fields[$key], $data);
            $this->updatedAt = date('Y-m-d H:i:s');
        }
    }

    public function getField(string $key): ?array
    {
        return $this->fields[$key] ?? null;
    }

    public function getAllFields(): array
    {
        return $this->fields;
    }

    public function getEvidenceLedger(): EvidenceLedger
    {
        return $this->evidenceLedger;
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function toArray(): array
    {
        return [
            'session_id' => $this->sessionId,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'fields' => $this->fields,
            'evidence' => $this->evidenceLedger->getAll(),
        ];
    }
}
