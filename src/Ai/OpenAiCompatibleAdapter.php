<?php
namespace AssistedAutocataloguing\Ai;

/**
 * Adapter for OpenAI-compatible Cloud AI Providers
 */
class OpenAiCompatibleAdapter implements AiProviderInterface
{
    private string $baseUrl;
    private string $apiKey;
    private string $model;
    private int $timeout;

    public function __construct(
        string $baseUrl = 'https://api.openai.com/v1',
        string $apiKey = '',
        string $model = 'gpt-4o-mini',
        int $timeout = 45
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiKey = $apiKey;
        $this->model = $model;
        $this->timeout = $timeout;
    }

    public function getName(): string
    {
        return 'openai_compatible';
    }

    public function getLabel(): string
    {
        return 'Cloud AI (OpenAI / OpenRouter / Gemini)';
    }

    public function isLocal(): bool
    {
        return false;
    }

    public function complete(string $systemPrompt, string $userPrompt, array $options = []): array
    {
        $apiKey = $options['api_key'] ?? $this->apiKey;
        if (empty($apiKey)) {
            return [
                'success' => false,
                'raw_response' => '',
                'parsed_json' => null,
                'error' => 'API Key belum dikonfigurasi untuk provider AI Cloud',
            ];
        }

        $url = $this->baseUrl . '/chat/completions';
        $model = $options['model'] ?? $this->model;

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
            'response_format' => ['type' => 'json_object'],
            'temperature' => $options['temperature'] ?? 0.1,
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            $errData = @json_decode($response ?: '', true);
            $msg = $errData['error']['message'] ?? ($curlError ?: "HTTP $httpCode");
            return [
                'success' => false,
                'raw_response' => $response ?: '',
                'parsed_json' => null,
                'error' => 'Request AI Cloud gagal: ' . $msg,
            ];
        }

        $decoded = @json_decode($response, true);
        $content = $decoded['choices'][0]['message']['content'] ?? '';
        
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
            'error' => is_array($jsonParsed) ? null : 'Respons AI Cloud bukan JSON yang valid',
        ];
    }

    public function testConnection(): array
    {
        if (empty($this->apiKey)) {
            return [
                'ok' => false,
                'message' => 'API Key belum diisi',
            ];
        }

        $ch = curl_init($this->baseUrl . '/models');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 6,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            return [
                'ok' => true,
                'message' => 'Terkoneksi dengan sukses ke Cloud AI',
            ];
        }

        return [
            'ok' => false,
            'message' => 'Gagal koneksi (HTTP ' . $httpCode . ')',
        ];
    }
}
