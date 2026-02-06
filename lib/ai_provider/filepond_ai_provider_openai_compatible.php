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
        return $this->baseUrl !== '';
    }
    
    public function generate(string $base64Image, string $mimeType, string $prompt, int $maxTokens): array
    {
        // Smart URL handling
        $url = $this->baseUrl;
        
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
             if (strpos($url, '/v1') !== false) {
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
                            'text' => $prompt
                        ],
                        [
                            'type' => 'image_url',
                            'image_url' => [
                                'url' => 'data:' . $mimeType . ';base64,' . $base64Image
                            ]
                        ]
                    ]
                ]
            ],
            'max_tokens' => $maxTokens,
            'temperature' => 0.4
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data, JSON_THROW_ON_ERROR),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey
            ],
            CURLOPT_TIMEOUT => 60
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch) !== 0) {
            $this->handleCurlError($ch);
        }
        curl_close($ch);
        
        if (!is_string($response)) {
            throw new Exception('Empty response from API');
        }
        
        if ($httpCode !== 200) {
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
                 'total' => $result['usage']['total_tokens'] ?? 0
            ];
        }
        
        return [
            'text' => $this->cleanText($result['choices'][0]['message']['content']),
            'tokens' => $tokens
        ];
    }
    
    public function testConnection(): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'Base URL nicht konfiguriert'];
        }

        // Smart URL handling für Models Check
        $url = $this->baseUrl;
        
        // Versuchen den "Basis"-Pfad zu erraten, falls der User /chat/completions eingegeben hat
        if (str_ends_with($url, '/chat/completions')) {
            $url = str_replace('/chat/completions', '/models', $url); // Z.B. /api/chat/completions -> /api/models
        }
        elseif (str_ends_with($url, '/v1') || str_ends_with($url, '/api')) {
             $url .= '/models';
        }
        else {
             if (strpos($url, '/v1') !== false) {
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
                'Content-Type: application/json'
            ],
            CURLOPT_TIMEOUT => 10
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch) !== 0) {
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
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            $count = 0;
            $modelList = [];
            
            if (isset($data['data']) && is_array($data['data'])) {
                $count = count($data['data']);
                foreach ($data['data'] as $model) {
                    if (isset($model['id'])) {
                        $modelList[] = $model['id'];
                    }
                }
            }
            
            $msg = "Verbindung OK! $count Modelle gefunden.";
            if ($modelList !== []) {
                $msg .= ' Verfügbare Modelle: <br><code>' . implode('</code>, <code>', $modelList) . '</code>';
            }
            
            return ['success' => true, 'message' => $msg];
        }
        
        return ['success' => false, 'message' => "HTTP Error $httpCode - " . substr(strip_tags($response), 0, 100)];
    }
}
