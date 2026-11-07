<?php
namespace AssistedAutocataloguing\Catalog;

/**
 * Interface for External Bibliographic Catalog Providers
 */
interface CatalogProviderInterface
{
    /**
     * Get the identifier name of this provider
     */
    public function getName(): string;

    /**
     * Human-readable label
     */
    public function getLabel(): string;

    /**
     * Search metadata by ISBN
     *
     * @param string $isbn Normalized ISBN (10 or 13 digits)
     * @return array Result array with [ 'found' => bool, 'metadata' => array, 'source' => string, 'raw' => mixed, 'error' => ?string ]
     */
    public function searchByIsbn(string $isbn): array;

    /**
     * Health check to see if service is reachable
     */
    public function testConnection(): array;
}
