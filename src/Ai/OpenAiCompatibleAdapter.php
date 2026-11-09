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
        $images = $options['images'] ?? []; // Array of base64 data URIs or base64 strings

        // Construct user message content: either string or array of parts (text + images)
        if (!empty($images) && is_array($images)) {
            $userContentParts = [
                ['type' => 'text', 'text' => $userPrompt]
            ];
            foreach ($images as $img) {
                $dataUri = str_starts_with($img, 'data:') ? $img : ('data:image/jpeg;base64,' . $img);
                $userContentParts[] = [
                    'type' => 'image_url',
                    'image_url' => [
                        'url' => $dataUri,
                    ],
                ];
            }
            $userMessage = ['role' => 'user', 'content' => $userContentParts];
        } else {
            $userMessage = ['role' => 'user', 'content' => $userPrompt];
        }

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                $userMessage,
            ],
            'stream' => false,
            'response_format' => ['type' => 'json_object'],
            'temperature' => $options['temperature'] ?? 0.1,
        ];

        $execCall = function(array $data) use ($url, $apiKey) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($data),
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
            return [$response, $httpCode, $curlError];
        };

        list($response, $httpCode, $curlError) = $execCall($payload);

        // Fallback 1: If json_object mode fails (some models do not support response_format: json_object)
        if (($response === false || $httpCode !== 200) && isset($payload['response_format'])) {
            unset($payload['response_format']);
            list($response, $httpCode, $curlError) = $execCall($payload);
        }

        // Fallback 2: If image_url causes an error (model is text-only), fallback to text-only prompt
        if (($response === false || $httpCode !== 200) && !empty($images)) {
            $payload['messages'][1] = ['role' => 'user', 'content' => $userPrompt];
            list($response, $httpCode, $curlError) = $execCall($payload);
        }

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

        $content = '';
        $decoded = @json_decode($response, true);
        if (is_array($decoded)) {
            $content = $decoded['choices'][0]['message']['content'] ?? '';
            // If content is empty, check reasoning_content (used by reasoning models)
            if (empty($content) && !empty($decoded['choices'][0]['message']['reasoning_content'])) {
                $content = $decoded['choices'][0]['message']['reasoning_content'];
            }
        } elseif (str_contains($response, 'data:')) {
            // Parse Server-Sent Events (SSE) stream chunks if proxy streams output
            $lines = explode("\n", $response);
            $streamedContent = '';
            $reasoningContent = '';
            foreach ($lines as $line) {
                $line = trim($line);
                if (str_starts_with($line, 'data:')) {
                    $jsonStr = trim(substr($line, 5));
                    if ($jsonStr === '[DONE]') {
                        continue;
                    }
                    $chunk = @json_decode($jsonStr, true);
                    if (is_array($chunk) && isset($chunk['choices'][0]['delta'])) {
                        $delta = $chunk['choices'][0]['delta'];
                        if (isset($delta['content'])) {
                            $streamedContent .= $delta['content'];
                        } elseif (isset($delta['reasoning_content'])) {
                            $reasoningContent .= $delta['reasoning_content'];
                        }
                    }
                }
            }
            $content = !empty($streamedContent) ? $streamedContent : $reasoningContent;
        }
        
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
        $curlError = curl_error($ch);
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
