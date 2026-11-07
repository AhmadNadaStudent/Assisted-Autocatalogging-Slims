<?php
namespace AssistedAutocataloguing\Ai;

/**
 * Adapter for Local Ollama Model
 */
class OllamaAdapter implements AiProviderInterface
{
    private string $baseUrl;
    private string $model;
    private int $timeout;

    public function __construct(string $baseUrl = 'http://localhost:11434', string $model = 'llama3.2', int $timeout = 60)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->model = $model;
        $this->timeout = $timeout;
    }

    public function getName(): string
    {
        return 'ollama';
    }

    public function getLabel(): string
    {
        return 'Ollama (Lokal)';
    }

    public function isLocal(): bool
    {
        return true;
    }

    public function complete(string $systemPrompt, string $userPrompt, array $options = []): array
    {
        $model = $options['model'] ?? $this->model;
        $url = $this->baseUrl . '/api/chat';

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
            'format' => 'json',
            'stream' => false,
            'options' => [
                'temperature' => $options['temperature'] ?? 0.1,
            ],
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            return [
                'success' => false,
                'raw_response' => $response ?: '',
                'parsed_json' => null,
                'error' => 'Koneksi ke Ollama gagal: ' . ($curlError ?: "HTTP $httpCode"),
            ];
        }

        $decoded = @json_decode($response, true);
        $content = $decoded['message']['content'] ?? ($decoded['response'] ?? '');
        
        // Remove <think>...</think> blocks if model uses internal chain of thought
        $cleanContent = preg_replace('/<think>.*?<\/think>/is', '', $content);
        $cleanContent = trim($cleanContent);

        // Strip markdown code fences or extract JSON block
        if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/is', $cleanContent, $m)) {
            $jsonParsed = @json_decode($m[1], true);
        } else {
            $jsonParsed = @json_decode($cleanContent, true);
            if (!is_array($jsonParsed) && preg_match('/(\{.*\})/s', $cleanContent, $m)) {
                $jsonParsed = @json_decode($m[1], true);
            }
        }

        return [
            'success' => is_array($jsonParsed),
            'raw_response' => $content,
            'parsed_json' => $jsonParsed,
            'error' => is_array($jsonParsed) ? null : 'Model lokal tidak menghasilkan format JSON yang valid',
        ];
    }

    public function testConnection(): array
    {
        $ch = curl_init($this->baseUrl . '/api/tags');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 4,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response !== false && $httpCode === 200) {
            $data = @json_decode($response, true);
            $models = array_column($data['models'] ?? [], 'name');
            return [
                'ok' => true,
                'message' => 'Terkoneksi ke Ollama',
                'models' => $models,
            ];
        }

        return [
            'ok' => false,
            'message' => 'Tidak dapat terhubung ke Ollama: ' . ($curlError ?: "HTTP $httpCode"),
            'models' => [],
        ];
    }
}
