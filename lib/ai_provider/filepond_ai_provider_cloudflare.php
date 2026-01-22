<?php

class filepond_ai_provider_cloudflare extends filepond_ai_provider_abstract
{
    private string $apiKey;
    private string $accountId;
    private string $model;
    
    public function __construct(string $apiKey, string $accountId, string $model)
    {
        $this->apiKey = $apiKey;
        $this->accountId = $accountId;
        $this->model = $model;
    }
    
    public function getKey(): string
    {
        return 'cloudflare';
    }
    
    public function getLabel(): string
    {
        return 'Cloudflare Workers AI';
    }
    
    public function isConfigured(): bool
    {
        return !empty($this->apiKey) && !empty($this->accountId);
    }
    
    public function generate(string $base64Image, string $mimeType, string $prompt, int $maxTokens): array
    {
        $url = "https://api.cloudflare.com/client/v4/accounts/{$this->accountId}/ai/run/{$this->model}";
        
        // Cloudflare expects image bytes (int array)
        $imageBytes = array_values(unpack('C*', base64_decode($base64Image)));
        
        $data = [
            'image' => $imageBytes,
            'prompt' => $prompt,
            'max_tokens' => $maxTokens
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey
            ],
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            $this->handleCurlError($ch);
        }
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        if ($httpCode !== 200 || !($result['success'] ?? false)) {
            $errorMessage = $result['errors'][0]['message'] ?? ($result['error'] ?? 'HTTP Error ' . $httpCode);
            if (isset($result['errors']) && is_array($result['errors'])) {
                $errorMessage = implode(', ', array_column($result['errors'], 'message'));
            }
            if ($httpCode === 429) {
                throw new Exception('Rate-Limit erreicht! Bitte später erneut versuchen.');
            }
            throw new Exception('Cloudflare API Error: ' . $errorMessage);
        }
        
        if (!isset($result['result']['description'])) {
            if (is_string($result['result'] ?? null)) {
                 return ['text' => $this->cleanText($result['result']), 'tokens' => null];
            }
            throw new Exception('Unerwartete API-Antwort: ' . substr(json_encode($result), 0, 200));
        }
        
        return [
            'text' => $this->cleanText($result['result']['description']),
            'tokens' => null
        ];
    }
    
    public function testConnection(): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'Token/Account ID fehlt'];
        }
        
        $url = 'https://api.cloudflare.com/client/v4/user/tokens/verify';
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json'
            ],
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
             try {
                 $this->handleCurlError($ch);
             } catch (Exception $e) {
                 return ['success' => false, 'message' => $e->getMessage()];
             }
        }
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        if ($httpCode === 200 && ($result['success'] ?? false)) {
            $status = $result['result']['status'] ?? 'unknown';
            if ($status === 'active') {
                return ['success' => true, 'message' => 'Cloudflare Verbindung OK. Modell: ' . $this->model];
            }
            return ['success' => false, 'message' => 'Token Status: ' . $status];
        }
        
        $errorMessage = $result['errors'][0]['message'] ?? 'HTTP Error ' . $httpCode;
        return ['success' => false, 'message' => 'API-Fehler: ' . $errorMessage];
    }
}
