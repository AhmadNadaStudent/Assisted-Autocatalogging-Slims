<?php
namespace AssistedAutocataloguing\Catalog;

use AssistedAutocataloguing\Core\Application;

/**
 * Catalog Search Manager & Orchestrator
 */
class CatalogManager
{
    private array $providers = [];
    private string $defaultProvider;

    public function __construct(?Application $app = null)
    {
        $app = $app ?? Application::getInstance();
        $config = $app->getConfig('catalog', []);

        $this->defaultProvider = $config['default_provider'] ?? 'onesearch';
        $timeout = $config['timeout'] ?? 15;

        // Register default providers
        $this->registerProvider(new PerpusnasOneSearchAdapter(
            $config['providers']['onesearch']['base_url'] ?? 'https://onesearch.id',
            $timeout
        ));

        $this->registerProvider(new PerpusnasSruAdapter(
            $config['providers']['perpusnas_sru']['base_url'] ?? 'https://opac.perpusnas.go.id/sru.aspx',
            $timeout
        ));
    }

    public function registerProvider(CatalogProviderInterface $provider): void
    {
        $this->providers[$provider->getName()] = $provider;
    }

    public function getProvider(string $name): ?CatalogProviderInterface
    {
        return $this->providers[$name] ?? null;
    }

    public function search(string $isbn, ?string $preferredProvider = null): array
    {
        $providerKey = $preferredProvider ?? $this->defaultProvider;
        $provider = $this->getProvider($providerKey) ?? $this->getProvider('onesearch');

        if (!$provider) {
            return [
                'found' => false,
                'metadata' => [],
                'source' => 'none',
                'error' => 'Tidak ada provider katalog yang terdaftar',
            ];
        }

        $result = $provider->searchByIsbn($isbn);

        // If preferred provider failed and fallback is available, try fallback
        if (!$result['found'] && $providerKey !== 'onesearch' && isset($this->providers['onesearch'])) {
            $fallbackResult = $this->providers['onesearch']->searchByIsbn($isbn);
            if ($fallbackResult['found']) {
                return $fallbackResult;
            }
        }

        return $result;
    }

    public function testProviders(): array
    {
        $status = [];
        foreach ($this->providers as $name => $provider) {
            $status[$name] = array_merge([
                'label' => $provider->getLabel(),
            ], $provider->testConnection());
        }
        return $status;
    }
}
