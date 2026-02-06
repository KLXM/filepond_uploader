<?php

class filepond_ai_provider_gemini extends filepond_ai_provider_abstract
{
    private string $apiKey;
    private string $model;
    
    public function __construct(string $apiKey, string $model)
    {
        $this->apiKey = $apiKey;
        $this->model = $model;
    }
    
    public function getKey(): string
    {
        return 'gemini';
    }
    
    public function getLabel(): string
    {
        return 'Google Gemini';
    }
    
    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }
    
    public function generate(string $base64Image, string $mimeType, string $prompt, int $maxTokens): array
    {
        $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models/';
        $url = $baseUrl . $this->model . ':generateContent?key=' . $this->apiKey;
        
        $data = [
            'contents' => [
                [
                    'parts' => [
                        [
                            'text' => $prompt
                        ],
                        [
                            'inline_data' => [
                                'mime_type' => $mimeType,
                                'data' => $base64Image
                            ]
                        ]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.4,
                'maxOutputTokens' => $maxTokens,
                'topP' => 0.8,
                'topK' => 40
            ],
            'safetySettings' => [
                [
                    'category' => 'HARM_CATEGORY_HARASSMENT',
                    'threshold' => 'BLOCK_NONE'
                ],
                [
                    'category' => 'HARM_CATEGORY_HATE_SPEECH',
                    'threshold' => 'BLOCK_NONE'
                ],
                [
                    'category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT',
                    'threshold' => 'BLOCK_NONE'
                ],
                [
                    'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT',
                    'threshold' => 'BLOCK_NONE'
                ]
            ]
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data, JSON_THROW_ON_ERROR),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json'
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        // Error Handling delegated mainly to logic below, but using helper for basic curl
        // Here we handle HTTP logic specific to Gemini
        if (curl_errno($ch) !== 0) {
            $this->handleCurlError($ch);
        }
        curl_close($ch);
        
        if (!is_string($response)) {
            throw new Exception('Empty response from API');
        }
        
        if ($httpCode !== 200) {
            $errorData = json_decode($response, true);
            $errorMessage = $errorData['error']['message'] ?? 'HTTP Error ' . $httpCode;
            
            if ($httpCode === 429 || stripos($errorMessage, 'quota') !== false || stripos($errorMessage, 'rate') !== false) {
                $waitTime = '';
                if (preg_match('/retry in (\d+\.?\d*)/i', $errorMessage, $matches) === 1) {
                    $seconds = ceil((float)$matches[1]);
                    $waitTime = " Bitte in {$seconds} Sekunden erneut versuchen.";
                }
                throw new Exception('Rate-Limit erreicht! Kostenloses Kontingent aufgebraucht.' . $waitTime);
            }
            throw new Exception('API Error: ' . $errorMessage);
        }
        
        $result = json_decode($response, true);
        
        if (!isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            $finishReason = $result['candidates'][0]['finishReason'] ?? 'UNKNOWN';
            throw new Exception('Unerwartete API-Antwort (finishReason: ' . $finishReason . ')');
        }
        
        $tokens = null;
        if (isset($result['usageMetadata'])) {
            $tokens = [
                'prompt' => $result['usageMetadata']['promptTokenCount'] ?? 0,
                'response' => $result['usageMetadata']['candidatesTokenCount'] ?? 0,
                'total' => $result['usageMetadata']['totalTokenCount'] ?? 0
            ];
        }
        
        return [
            'text' => $this->cleanText($result['candidates'][0]['content']['parts'][0]['text']),
            'tokens' => $tokens
        ];
    }
    
    public function testConnection(): array
    {
        if (!$this->isConfigured()) {
             return ['success' => false, 'message' => 'API-Key nicht konfiguriert'];
        }

        $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models/';
        $url = $baseUrl . $this->model . ':generateContent?key=' . $this->apiKey;
        
        $data = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => 'Antworte mit: OK']
                    ]
                ]
            ]
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data, JSON_THROW_ON_ERROR),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch) !== 0) {
             try {
                 $this->handleCurlError($ch);
             } catch (Exception $e) {
                 return ['success' => false, 'message' => $e->getMessage()];
             }
        }
        curl_close($ch);
        
        if (!is_string($response)) {
            return ['success' => false, 'message' => 'Empty response from API'];
        }
        
        if ($httpCode === 200) {
            $responseData = json_decode($response, true);
            $tokenInfo = '';
            if (isset($responseData['usageMetadata'])) {
                $usage = $responseData['usageMetadata'];
                $tokenInfo = sprintf(
                    ' | Tokens: %d (P) + %d (A) = %d',
                    $usage['promptTokenCount'] ?? 0,
                    $usage['candidatesTokenCount'] ?? 0,
                    $usage['totalTokenCount'] ?? 0
                );
            }
            return [
                'success' => true, 
                'message' => 'Gemini Verbindung erfolgreich! Modell: ' . $this->model . $tokenInfo
            ];
        }
        
        $errorData = json_decode($response, true);
        $errorMessage = $errorData['error']['message'] ?? 'HTTP Error ' . $httpCode;
        
        return ['success' => false, 'message' => 'API-Fehler: ' . $errorMessage];
    }
}
