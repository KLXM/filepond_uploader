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
    
    // Verfügbare Modelle
    public const MODELS = [
        'gemini-2.5-flash-preview-05-20' => 'Gemini 2.5 Flash (Preview) - Kostenlos',
        'gemini-2.0-flash' => 'Gemini 2.0 Flash - Kostenlos',
        'gemini-1.5-flash' => 'Gemini 1.5 Flash - Kostenlos',
        'gemini-1.5-pro' => 'Gemini 1.5 Pro - Bezahlt',
        'gemini-2.5-pro-preview-05-06' => 'Gemini 2.5 Pro (Preview) - Bezahlt',
    ];
    
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->apiKey = rex_config::get('filepond_uploader', 'gemini_api_key', '');
        $this->model = rex_config::get('filepond_uploader', 'gemini_model', 'gemini-2.5-flash-preview-05-20');
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
            $response = $this->callGeminiApi($base64Image, $mimeType, $prompt);
            return [
                'success' => true,
                'alt_text' => $response,
                'error' => null
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'alt_text' => '',
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
Analysiere dieses Bild und erstelle einen prägnanten, beschreibenden Alt-Text auf $langName.

Regeln für den Alt-Text:
- Beschreibe den wesentlichen Inhalt des Bildes in 1-2 Sätzen
- Maximal 125 Zeichen
- Keine Phrasen wie "Bild von" oder "Foto zeigt"
- Beginne direkt mit der Beschreibung
- Sei konkret und beschreibend
- Berücksichtige wichtige Details wie Farben, Personen, Objekte, Aktionen
- Der Text soll für Screenreader-Nutzer den Bildinhalt verständlich machen

Antworte NUR mit dem Alt-Text, ohne Anführungszeichen oder zusätzliche Erklärungen.
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
                'maxOutputTokens' => 200,
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
        
        if (!isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            throw new Exception('Unerwartete API-Antwort');
        }
        
        $altText = trim($result['candidates'][0]['content']['parts'][0]['text']);
        
        // Anführungszeichen am Anfang/Ende entfernen falls vorhanden
        $altText = trim($altText, '"\'');
        
        return $altText;
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
            CURLOPT_TIMEOUT => 10
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            return [
                'success' => true,
                'message' => 'Verbindung erfolgreich! API ist bereit.'
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
