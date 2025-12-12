<?php
/**
 * AI Alt-Text Generator für REDAXO
 * 
 * Nutzt Google Gemini Vision API zur automatischen Generierung von Alt-Texten
 * 
 * @package filepond_uploader
 */

class filepond_ai_alt_generator
{
    private string $apiKey;
    private string $model;
    private string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models/';
    
    // Verfügbare Modelle (Stand: Dezember 2025)
    public const MODELS = [
        // Kostenlose Modelle (Free Tier)
        'gemini-2.5-flash' => 'Gemini 2.5 Flash - Kostenlos ⭐',
        'gemini-2.5-flash-lite' => 'Gemini 2.5 Flash-Lite - Kostenlos (schneller)',
        'gemini-2.0-flash' => 'Gemini 2.0 Flash - Kostenlos',
        'gemini-2.0-flash-lite' => 'Gemini 2.0 Flash-Lite - Kostenlos (schneller)',
        // Bezahlte Modelle
        'gemini-3-pro-preview' => 'Gemini 3 Pro (Preview) - Bezahlt 💎',
        'gemini-2.5-pro' => 'Gemini 2.5 Pro - Bezahlt 💎',
    ];
    
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->apiKey = rex_config::get('filepond_uploader', 'gemini_api_key', '');
        $this->model = rex_config::get('filepond_uploader', 'gemini_model', 'gemini-2.5-flash');
    }
    
    /**
     * Prüft ob die AI-Funktion verfügbar ist
     */
    public static function isAvailable(): bool
    {
        $apiKey = rex_config::get('filepond_uploader', 'gemini_api_key', '');
        return !empty($apiKey);
    }
    
    /**
     * Prüft ob die AI-Funktion aktiviert ist
     */
    public static function isEnabled(): bool
    {
        return rex_config::get('filepond_uploader', 'enable_ai_alt', false) && self::isAvailable();
    }
    
    /**
     * Generiert einen Alt-Text für ein Bild
     * 
     * @param string $filename Der Dateiname im Medienpool
     * @param string $language Zielsprache (de, en, etc.)
     * @return array ['success' => bool, 'alt_text' => string, 'error' => string|null]
     */
    public function generateAltText(string $filename, string $language = 'de'): array
    {
        if (empty($this->apiKey)) {
            return [
                'success' => false,
                'alt_text' => '',
                'error' => 'API-Key nicht konfiguriert'
            ];
        }
        
        $media = rex_media::get($filename);
        if (!$media) {
            return [
                'success' => false,
                'alt_text' => '',
                'error' => 'Datei nicht gefunden'
            ];
        }
        
        // Nur Bilder verarbeiten
        if (!$media->isImage()) {
            return [
                'success' => false,
                'alt_text' => '',
                'error' => 'Keine Bilddatei'
            ];
        }
        
        // SVG nicht unterstützt (Gemini kann SVG nicht analysieren)
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if ($extension === 'svg') {
            return [
                'success' => false,
                'alt_text' => '',
                'error' => 'SVG-Dateien werden nicht unterstützt'
            ];
        }
        
        $filePath = rex_path::media($filename);
        if (!file_exists($filePath)) {
            return [
                'success' => false,
                'alt_text' => '',
                'error' => 'Datei nicht auf dem Server gefunden'
            ];
        }
        
        // Bild als Base64 kodieren
        $imageData = file_get_contents($filePath);
        $base64Image = base64_encode($imageData);
        $mimeType = $media->getType();
        
        // Prompt zusammenstellen
        $prompt = $this->buildPrompt($language);
        
        // API Request
        try {
            $result = $this->callGeminiApi($base64Image, $mimeType, $prompt);
            return [
                'success' => true,
                'alt_text' => $result['text'],
                'tokens' => $result['tokens'] ?? null,
                'error' => null
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'alt_text' => '',
                'tokens' => null,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Generiert Alt-Texte für mehrere Bilder (Bulk)
     * 
     * @param array $filenames Array von Dateinamen
     * @param string $language Zielsprache
     * @return array Array mit Ergebnissen pro Datei
     */
    public function generateBulk(array $filenames, string $language = 'de'): array
    {
        $results = [];
        
        foreach ($filenames as $filename) {
            $results[$filename] = $this->generateAltText($filename, $language);
            
            // Rate Limiting: 100ms Pause zwischen Requests
            usleep(100000);
        }
        
        return $results;
    }
    
    /**
     * Baut den Prompt für die AI
     */
    private function buildPrompt(string $language = 'de'): string
    {
        // Custom Prompt aus Einstellungen laden
        $customPrompt = rex_config::get('filepond_uploader', 'ai_alt_prompt', '');
        
        if (!empty($customPrompt)) {
            // Platzhalter ersetzen
            return str_replace(
                ['{language}', '{lang}'],
                [$this->getLanguageName($language), $language],
                $customPrompt
            );
        }
        
        // Standard-Prompt
        $langName = $this->getLanguageName($language);
        
        return <<<PROMPT
Analysiere dieses Bild und erstelle einen beschreibenden Alt-Text auf $langName.

Regeln:
- Beschreibe den wesentlichen Bildinhalt in einem vollständigen Satz
- Halte den Text kurz (ca. 10-15 Wörter), aber vollständig
- Keine Phrasen wie "Bild von", "Foto zeigt" oder "Abbildung"
- Beginne direkt mit der Beschreibung
- Beschreibe konkret: Farben, Personen, Objekte, Handlungen
- Der Text muss für Screenreader-Nutzer verständlich sein
- WICHTIG: Der Satz muss vollständig sein, nicht mitten im Wort abbrechen!

Antworte NUR mit dem Alt-Text, ohne Anführungszeichen oder Erklärungen.
PROMPT;
    }
    
    /**
     * Ruft die Gemini API auf
     */
    private function callGeminiApi(string $base64Image, string $mimeType, string $prompt): string
    {
        $url = $this->baseUrl . $this->model . ':generateContent?key=' . $this->apiKey;
        
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
                'maxOutputTokens' => 500,
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
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json'
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception('cURL Error: ' . $error);
        }
        
        if ($httpCode !== 200) {
            $errorData = json_decode($response, true);
            $errorMessage = $errorData['error']['message'] ?? 'HTTP Error ' . $httpCode;
            throw new Exception('API Error: ' . $errorMessage);
        }
        
        $result = json_decode($response, true);
        
        // Debug: finishReason prüfen
        $finishReason = $result['candidates'][0]['finishReason'] ?? 'UNKNOWN';
        if ($finishReason !== 'STOP' && $finishReason !== 'END_TURN') {
            rex_logger::factory()->log('warning', 'Gemini finishReason: ' . $finishReason . ' - Response: ' . substr($response, 0, 500));
        }
        
        if (!isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            throw new Exception('Unerwartete API-Antwort (finishReason: ' . $finishReason . ')');
        }
        
        $altText = trim($result['candidates'][0]['content']['parts'][0]['text']);
        
        // Anführungszeichen am Anfang/Ende entfernen falls vorhanden
        $altText = trim($altText, '"\'');
        
        // Token-Nutzung extrahieren
        $tokens = null;
        if (isset($result['usageMetadata'])) {
            $tokens = [
                'prompt' => $result['usageMetadata']['promptTokenCount'] ?? 0,
                'response' => $result['usageMetadata']['candidatesTokenCount'] ?? 0,
                'total' => $result['usageMetadata']['totalTokenCount'] ?? 0
            ];
        }
        
        return [
            'text' => $altText,
            'tokens' => $tokens
        ];
    }
    
    /**
     * Gibt den Sprachnamen für den Prompt zurück
     */
    private function getLanguageName(string $code): string
    {
        $languages = [
            'de' => 'Deutsch',
            'en' => 'Englisch',
            'fr' => 'Französisch',
            'es' => 'Spanisch',
            'it' => 'Italienisch',
            'nl' => 'Niederländisch',
            'pl' => 'Polnisch',
            'pt' => 'Portugiesisch',
            'ru' => 'Russisch',
            'zh' => 'Chinesisch',
            'ja' => 'Japanisch'
        ];
        
        // Sprache aus Code extrahieren (z.B. "de_de" -> "de")
        $shortCode = substr($code, 0, 2);
        
        return $languages[$shortCode] ?? 'Deutsch';
    }
    
    /**
     * Testet die API-Verbindung
     * 
     * @return array ['success' => bool, 'message' => string]
     */
    public function testConnection(): array
    {
        if (empty($this->apiKey)) {
            return [
                'success' => false,
                'message' => 'API-Key nicht konfiguriert'
            ];
        }
        
        // Einfacher Text-Test ohne Bild
        $url = $this->baseUrl . $this->model . ':generateContent?key=' . $this->apiKey;
        
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
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        curl_close($ch);
        
        // cURL-Fehler (Netzwerk, SSL, etc.)
        if ($curlErrno) {
            $errorDetails = 'cURL Error #' . $curlErrno . ': ' . $curlError;
            
            // Hilfreiche Hinweise für häufige Fehler
            if ($curlErrno === 60 || $curlErrno === 77) {
                $errorDetails .= ' (SSL-Zertifikatsproblem - evtl. cacert.pem fehlt)';
            } elseif ($curlErrno === 6) {
                $errorDetails .= ' (DNS-Auflösung fehlgeschlagen)';
            } elseif ($curlErrno === 7) {
                $errorDetails .= ' (Verbindung zu Google-Servern fehlgeschlagen)';
            } elseif ($curlErrno === 28) {
                $errorDetails .= ' (Timeout - Server antwortet nicht)';
            }
            
            return [
                'success' => false,
                'message' => $errorDetails
            ];
        }
        
        if ($httpCode === 200) {
            $responseData = json_decode($response, true);
            
            // Token-Info extrahieren
            $tokenInfo = '';
            if (isset($responseData['usageMetadata'])) {
                $usage = $responseData['usageMetadata'];
                $tokenInfo = sprintf(
                    ' | Tokens: %d (Prompt) + %d (Antwort) = %d',
                    $usage['promptTokenCount'] ?? 0,
                    $usage['candidatesTokenCount'] ?? 0,
                    $usage['totalTokenCount'] ?? 0
                );
            }
            
            return [
                'success' => true,
                'message' => 'Verbindung erfolgreich! Modell: ' . $this->model . $tokenInfo
            ];
        }
        
        $errorData = json_decode($response, true);
        $errorMessage = $errorData['error']['message'] ?? 'HTTP Error ' . $httpCode;
        
        return [
            'success' => false,
            'message' => 'API-Fehler: ' . $errorMessage
        ];
    }
}
