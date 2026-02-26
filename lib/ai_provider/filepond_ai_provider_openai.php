<?php

declare(strict_types=1);

####################################################################################################
### OpenAI Provider — dedizierter Provider für OpenAI/ChatGPT Vision API

class filepond_ai_provider_openai extends filepond_ai_provider_abstract
{
    private const API_URL = 'https://api.openai.com/v1/chat/completions';

    private string $api_key;
    private string $model;

    public function __construct(string $api_key, string $model)
    {
        $this->api_key = $api_key;
        $this->model   = $model;
    }

    public function getKey(): string
    {
        return 'openai';
    }

    public function getLabel(): string
    {
        return 'OpenAI / ChatGPT';
    }

    public function isConfigured(): bool
    {
        return '' !== $this->api_key;
    }

    ####################################################################################################
    ### generate

    public function generate(string $base64Image, string $mimeType, string $prompt, int $maxTokens): array
    {
        $data = [
            'model'      => $this->model,
            'messages'   => [
                [
                    'role'    => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => $prompt,
                        ],
                        [
                            'type'      => 'image_url',
                            'image_url' => [
                                'url'    => 'data:' . $mimeType . ';base64,' . $base64Image,
                                'detail' => 'low',
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
            CURLOPT_URL            => self::API_URL,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($data, JSON_THROW_ON_ERROR),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->api_key,
            ],
            CURLOPT_TIMEOUT => 60,
        ]);

        $response  = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (0 !== curl_errno($ch)) :
            $this->handleCurlError($ch);
        endif;

        curl_close($ch);

        if (false === $response || '' === $response) :
            throw new Exception('Empty response from OpenAI API');
        endif;

        $result = json_decode($response, true);

        if (!is_array($result)) :
            throw new Exception('Invalid JSON response from OpenAI API');
        endif;

        if (isset($result['error']['message'])) :
            throw new Exception('OpenAI API Error: ' . $result['error']['message']);
        endif;

        if ($http_code !== 200) :
            throw new Exception('OpenAI API HTTP ' . $http_code);
        endif;

        $text      = $result['choices'][0]['message']['content'] ?? '';
        $usage     = $result['usage'] ?? null;
        $token_info = null;

        if (is_array($usage)) :
            $token_info = [
                'prompt'   => (int) ($usage['prompt_tokens'] ?? 0),
                'response' => (int) ($usage['completion_tokens'] ?? 0),
                'total'    => (int) ($usage['total_tokens'] ?? 0),
            ];
        endif;

        return [
            'text'   => $this->cleanText($text),
            'tokens' => $token_info,
        ];
    }

    ####################################################################################################
    ### testConnection

    public function testConnection(): array
    {
        if (!$this->isConfigured()) :
            return ['success' => false, 'message' => 'API key not configured'];
        endif;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => 'https://api.openai.com/v1/models',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->api_key,
            ],
            CURLOPT_TIMEOUT => 15,
        ]);

        $response  = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (false === $response) :
            return ['success' => false, 'message' => 'Connection failed'];
        endif;

        $result = json_decode($response, true);

        if (isset($result['error']['message'])) :
            return ['success' => false, 'message' => $result['error']['message']];
        endif;

        if ($http_code !== 200) :
            return ['success' => false, 'message' => 'HTTP ' . $http_code];
        endif;

        # Nur Vision-fähige Modelle sammeln
        $models = [];
        if (isset($result['data']) && is_array($result['data'])) :
            foreach ($result['data'] as $model) :
                $id = (string) ($model['id'] ?? '');
                if (str_contains($id, 'gpt-4') || str_contains($id, 'gpt-5')) :
                    $models[] = $id;
                endif;
            endforeach;
            sort($models);
        endif;

        $message = 'Connected to OpenAI';
        if (!empty($models)) :
            $message .= ' — Vision models: ' . implode(', ', array_slice($models, 0, 8));
        endif;

        return ['success' => true, 'message' => $message];
    }
}
