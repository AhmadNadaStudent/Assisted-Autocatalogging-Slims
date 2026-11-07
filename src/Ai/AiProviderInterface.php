<?php
namespace AssistedAutocataloguing\Ai;

/**
 * Interface for AI Inference Providers
 */
interface AiProviderInterface
{
    public function getName(): string;
    public function getLabel(): string;
    public function isLocal(): bool;

    /**
     * Send prompt to AI and get structured completion
     *
     * @param string $systemPrompt
     * @param string $userPrompt
     * @param array $options Model, temperature, timeout overrides
     * @return array [ 'success' => bool, 'raw_response' => string, 'parsed_json' => ?array, 'error' => ?string ]
     */
    public function complete(string $systemPrompt, string $userPrompt, array $options = []): array;

    /**
     * Test connection to provider
     */
    public function testConnection(): array;
}
