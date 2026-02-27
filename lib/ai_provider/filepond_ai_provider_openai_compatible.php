<?php

class filepond_ai_provider_openai_compatible extends filepond_ai_provider_abstract
{
    private string $apiKey;
    private string $baseUrl;
    private string $model;

    public function __construct(string $apiKey, string $baseUrl, string $model)
    {
        $this->apiKey = $apiKey;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->model = $model;
    }

    private function resolveBaseUrl(): string
    {
        if ('' !== trim($this->baseUrl)) {
            return $this->baseUrl;
        }

        return 'https://api.openai.com';
    }

    private function getModelIdentifier(array $model): string
    {
        $id = $model['id'] ?? ($model['name'] ?? '');
        return is_string($id) ? $id : '';
    }

    private function toBool(mixed $value): bool
    {
        return true === $value || 1 === $value || '1' === $value;
    }

    private function hasTruthyPath(array $model, array $path): bool
    {
        $value = $model;

        foreach ($path as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return false;
            }

            $value = $value[$segment];
        }

        return $this->toBool($value);
    }

    private function hasImageInputModality(array $model): bool
    {
        $possibleArrays = [
            $model['input_modalities'] ?? null,
            $model['modalities'] ?? null,
            $model['capabilities']['input'] ?? null,
            $model['capabilities']['modalities'] ?? null,
            $model['meta']['capabilities']['input'] ?? null,
            $model['meta']['capabilities']['modalities'] ?? null,
        ];

        foreach ($possibleArrays as $modalities) {
            if (!is_array($modalities)) {
                continue;
            }

            foreach ($modalities as $modality) {
                if (is_string($modality) && str_contains(strtolower($modality), 'image')) {
                    return true;
                }
            }
        }

        return false;
    }

    private function isVisionCapableModel(array $model): bool
    {
        $explicitVisionPaths = [
            ['vision'],
            ['capabilities', 'vision'],
            ['capabilities', 'image'],
            ['meta', 'vision'],
            ['meta', 'capabilities', 'vision'],
            ['details', 'vision'],
        ];

        foreach ($explicitVisionPaths as $path) {
            if ($this->hasTruthyPath($model, $path)) {
                return true;
            }
        }

        if ($this->hasImageInputModality($model)) {
            return true;
        }

        $id = strtolower($this->getModelIdentifier($model));
        if ('' === $id) {
            return false;
        }

        $visionNameHints = [
            'gpt-4o',
            'gpt-4.1',
            'vision',
            'vl',
            'llava',
            'minicpm-v',
            'qwen-vl',
            'pixtral',
            'gemma3',
            'moondream',
        ];

        foreach ($visionNameHints as $hint) {
            if (str_contains($id, $hint)) {
                return true;
            }
        }

        return false;
    }

    public function getKey(): string
    {
        return 'openwebui';
    }

    public function getLabel(): string
    {
        return 'OpenWebUI / OpenAI Compatible';
    }

    public function isConfigured(): bool
    {
        return '' !== trim($this->apiKey);
    }

    public function generate(string $base64Image, string $mimeType, string $prompt, int $maxTokens): array
    {
        // Smart URL handling
        $url = $this->resolveBaseUrl();

        // 1. Wenn die URL bereits auf /chat/completions endet (User hat vollen Pfad eingegeben)
        if (str_ends_with($url, '/chat/completions')) {
            // URL so lassen wie sie ist
        }
        // 2. Wenn die URL auf /v1 oder /api endet
        elseif (str_ends_with($url, '/v1') || str_ends_with($url, '/api')) {
            $url .= '/chat/completions';
        }
        // 3. Fallback: Standardpfad anhängen
        else {
            if (str_contains($url, '/v1')) {
                $url .= '/chat/completions';
            } else {
                // Standard OpenAI: /v1/chat/completions
                // Aber manche Custom Server (wie dieser) nutzen /api/chat/completions ohne /v1
                // Da wir es nicht wissen, nutzen wir den Standard.
                // Sollte der User Custom Pfade haben, muss er diese (siehe 1. oder 2.) in der Config angeben.
                $url .= '/v1/chat/completions';
            }
        }

        $data = [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => $prompt,
                        ],
                        [
                            'type' => 'image_url',
                            'image_url' => [
                                'url' => 'data:' . $mimeType . ';base64,' . $base64Image,
                            ],
                        ],
                    ],
                ],
            ],
            'max_tokens' => $maxTokens,
            'temperature' => 0.4,
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data, JSON_THROW_ON_ERROR),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_TIMEOUT => 60,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (0 !== curl_errno($ch)) {
            $this->handleCurlError($ch);
        }
        curl_close($ch);

        if (!is_string($response)) {
            throw new Exception('Empty response from API');
        }

        if (200 !== $httpCode) {
            throw new Exception('API Error (' . $httpCode . '): ' . $response);
        }

        $result = json_decode($response, true);

        if (!isset($result['choices'][0]['message']['content'])) {
            throw new Exception('Unerwartete API-Antwort: ' . substr($response, 0, 200));
        }

        $tokens = null;
        if (isset($result['usage'])) {
            $tokens = [
                'prompt' => $result['usage']['prompt_tokens'] ?? 0,
                'response' => $result['usage']['completion_tokens'] ?? 0,
                'total' => $result['usage']['total_tokens'] ?? 0,
            ];
        }

        return [
            'text' => $this->cleanText($result['choices'][0]['message']['content']),
            'tokens' => $tokens,
        ];
    }

    public function testConnection(): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'Base URL nicht konfiguriert'];
        }

        // Smart URL handling für Models Check
        $url = $this->resolveBaseUrl();

        // Versuchen den "Basis"-Pfad zu erraten, falls der User /chat/completions eingegeben hat
        if (str_ends_with($url, '/chat/completions')) {
            $url = str_replace('/chat/completions', '/models', $url); // Z.B. /api/chat/completions -> /api/models
        } elseif (str_ends_with($url, '/v1') || str_ends_with($url, '/api')) {
            $url .= '/models';
        } else {
            if (str_contains($url, '/v1')) {
                $url .= '/models';
            } else {
                $url .= '/v1/models';
            }
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (0 !== curl_errno($ch)) {
            try {
                $this->handleCurlError($ch);
            } catch (Exception $e) {
                return ['success' => false, 'message' => 'Verbindungsfehler: ' . $e->getMessage()];
            }
        }

        curl_close($ch);

        if (!is_string($response)) {
            return ['success' => false, 'message' => 'Empty response from API'];
        }

        if (200 === $httpCode) {
            $data = json_decode($response, true);
            $count = 0;
            $visionModelList = [];

            if (isset($data['data']) && is_array($data['data'])) {
                $count = count($data['data']);
                foreach ($data['data'] as $model) {
                    if (!is_array($model)) {
                        continue;
                    }

                    if ($this->isVisionCapableModel($model)) {
                        $modelId = $this->getModelIdentifier($model);
                        if ('' !== $modelId) {
                            $visionModelList[] = $modelId;
                        }
                    }
                }
            }

            $msg = "Verbindung OK! $count Modelle gefunden.";
            $msg .= ' Vision-fähig: ' . count($visionModelList) . '.';

            if ([] !== $visionModelList) {
                $msg .= ' Vision-Modelle: <br><code>' . implode('</code>, <code>', $visionModelList) . '</code>';
            }

            return ['success' => true, 'message' => $msg];
        }

        return ['success' => false, 'message' => "HTTP Error $httpCode - " . substr(strip_tags($response), 0, 100)];
    }
}
