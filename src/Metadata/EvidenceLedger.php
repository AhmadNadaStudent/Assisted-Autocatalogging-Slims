<?php
namespace AssistedAutocataloguing\Metadata;

/**
 * Tracks physical evidence sources linked to metadata claims
 */
class EvidenceLedger
{
    private array $items = [];

    public function addEvidence(array $item): string
    {
        $id = $item['evidence_id'] ?? ('evidence-' . bin2hex(random_bytes(6)));
        $item['evidence_id'] = $id;
        $this->items[$id] = $item;
        return $id;
    }

    public function getEvidence(string $id): ?array
    {
        return $this->items[$id] ?? null;
    }

    public function getAll(): array
    {
        return array_values($this->items);
    }

    public function toArray(): array
    {
        return $this->items;
    }
}
