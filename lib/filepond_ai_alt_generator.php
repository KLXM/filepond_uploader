<?php
/**
 * AI Alt-Text Generator für REDAXO
 * 
 * Unterstützt Google Gemini, Cloudflare Workers AI und OpenWebUI (OpenAI Compatible)
 * 
 * @package filepond_uploader
 */

class filepond_ai_alt_generator
{
    private filepond_ai_provider_interface $provider;
    
    // Verfügbare Provider
    public const PROVIDERS = [
        'gemini' => 'Google Gemini',
        'cloudflare' => 'Cloudflare Workers AI',
        'openwebui' => 'OpenWebUI / OpenAI Compatible'
    ];
    
    // Verfügbare Gemini-Modelle (Stand: Dezember 2025)
    // Diese werden für die Settings-Seite benötigt
    public const GEMINI_MODELS = [
        // Kostenlose Modelle (Free Tier)
        'gemini-2.5-flash' => 'Gemini 2.5 Flash - Kostenlos ⭐',
        'gemini-2.5-flash-lite' => 'Gemini 2.5 Flash-Lite - Kostenlos (schneller)',
        'gemini-2.0-flash' => 'Gemini 2.0 Flash - Kostenlos',
        'gemini-2.0-flash-lite' => 'Gemini 2.0 Flash-Lite - Kostenlos (schneller)',
        // Bezahlte Modelle
        'gemini-3-pro-preview' => 'Gemini 3 Pro (Preview) - Bezahlt 💎',
        'gemini-2.5-pro' => 'Gemini 2.5 Pro - Bezahlt 💎',
    ];
    
    // Verfügbare Cloudflare-Modelle
    public const CLOUDFLARE_MODELS = [
        '@cf/llava-hf/llava-1.5-7b-hf' => 'LLaVA 1.5 7B ⭐',
    ];
    
    // Legacy: für Abwärtskompatibilität
    public const MODELS = self::GEMINI_MODELS;
    
    /**
     * Constructor
     */
    public function __construct()
    {
        $providerKey = rex_config::get('filepond_uploader', 'ai_provider', 'gemini');
        
        // Provider Factory Logic
        switch ($providerKey) {
            case 'cloudflare':
                $this->provider = new filepond_ai_provider_cloudflare(
                    rex_config::get('filepond_uploader', 'cloudflare_api_token', ''),
                    rex_config::get('filepond_uploader', 'cloudflare_account_id', ''),
                    rex_config::get('filepond_uploader', 'cloudflare_model', '@cf/llava-hf/llava-1.5-7b-hf')
                );
                break;
                
            case 'openwebui':
                $this->provider = new filepond_ai_provider_openai_compatible(
                    rex_config::get('filepond_uploader', 'openwebui_api_key', ''),
                    rex_config::get('filepond_uploader', 'openwebui_base_url', 'http://localhost:3000'),
                    rex_config::get('filepond_uploader', 'openwebui_model', 'llava')
                );
                break;
                
            case 'gemini':
            default:
                $this->provider = new filepond_ai_provider_gemini(
                    rex_config::get('filepond_uploader', 'gemini_api_key', ''),
                    rex_config::get('filepond_uploader', 'gemini_model', 'gemini-2.5-flash')
                );
                break;
        }
    }
    
    /**
     * Gibt den aktuellen Provider zurück
     */
    public static function getProvider(): string
    {
        return rex_config::get('filepond_uploader', 'ai_provider', 'gemini');
    }
    
    /**
     * Prüft ob die AI-Funktion verfügbar ist
     */
    public static function isAvailable(): bool
    {
        // Wir erstellen eine Instanz, um die Konfiguration zu prüfen
        // Das ist sauberer als hier die Config-Logik zu duplizieren
        $generator = new self();
        return $generator->provider->isConfigured();
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
        if (!$this->provider->isConfigured()) {
            return [
                'success' => false,
                'alt_text' => '',
                'error' => 'AI Provider nicht korrekt konfiguriert'
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
        
        // SVG nicht unterstützt (noch nicht)
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
        
        return $this->executeGeneration($filePath, $language);
    }
    
    /**
     * Generiert einen Alt-Text für eine Datei anhand des Pfades
     * 
     * @param string $filePath Absoluter Pfad zur Datei
     * @param string $language Zielsprache
     * @return array ['success' => bool, 'alt_text' => string, 'error' => string|null]
     */
    public function generateAltTextFromPath(string $filePath, string $language = 'de'): array
    {
        if (!$this->provider->isConfigured()) {
            return [
                'success' => false,
                'alt_text' => '',
                'error' => 'AI Provider nicht korrekt konfiguriert'
            ];
        }
        
        if (!file_exists($filePath)) {
            return [
                'success' => false,
                'alt_text' => '',
                'error' => 'Datei nicht gefunden'
            ];
        }
        
        // Mime-Type prüfen
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($filePath);
        
        if (strpos($mimeType, 'image/') !== 0) {
            return [
                'success' => false,
                'alt_text' => '',
                'error' => 'Keine Bilddatei'
            ];
        }
        
        if ($mimeType === 'image/svg+xml') {
            return [
                'success' => false,
                'alt_text' => '',
                'error' => 'SVG-Dateien werden nicht unterstützt'
            ];
        }
        
        return $this->executeGeneration($filePath, $language);
    }

    /**
     * Interne Methode zur Ausführung der Generierung
     */
    private function executeGeneration(string $filePath, string $language): array
    {
        // Bild vorbereiten (Resize & Encoding)
        try {
            $prepared = $this->prepareImage($filePath, true);
            $base64Image = $prepared['data'];
            $mimeType = $prepared['mime'];
        } catch (Exception $e) {
            return [
                'success' => false,
                'alt_text' => '',
                'error' => $e->getMessage()
            ];
        }
        
        // Prompt zusammenstellen
        $prompt = $this->buildPrompt($language);
        
        // Max Tokens holen
        $maxTokens = (int) rex_config::get('filepond_uploader', 'ai_max_tokens', 2048);
        if ($maxTokens <= 0) $maxTokens = 2048;
        
        // API Request via Provider
        try {
            $result = $this->provider->generate($base64Image, $mimeType, $prompt, $maxTokens);
            
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
     * Bereitet das Bild für die AI vor (Resize auf max. 1024px & Formatierung)
     * 
     * @param string $path Pfad zum Bild oder Bild-Daten
     * @param bool $isPath True wenn $path ein Dateipfad ist
     * @return array ['data' => base64_string, 'mime' => mime_string]
     * @throws Exception
     */
    private function prepareImage(string $path, bool $isPath = true): array
    {
        $maxDimension = 1024;
        
        // Original laden
        $imageData = $isPath ? @file_get_contents($path) : $path;
        if ($imageData === false) {
             throw new Exception('Konnte Bilddatei nicht lesen');
        }

        // Mime Type ermitteln
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->buffer($imageData);
        
        // Wenn kein Bild, direkt Abbruch
        if (strpos($mimeType, 'image/') !== 0) {
             throw new Exception('Ungültiges Bildformat: ' . $mimeType);
        }
        
        // Versuchen zu resizen mit GD
        if (extension_loaded('gd') && $mimeType !== 'image/gif') {
            $image = @imagecreatefromstring($imageData);
            if ($image) {
                $width = imagesx($image);
                $height = imagesy($image);
                
                // Nur resizen wenn größer als Max
                if ($width > $maxDimension || $height > $maxDimension) {
                    $ratio = $width / $height;
                    if ($width > $height) {
                        $newWidth = $maxDimension;
                        $newHeight = (int)($maxDimension / $ratio);
                    } else {
                        $newHeight = $maxDimension;
                        $newWidth = (int)($maxDimension * $ratio);
                    }
                    
                    $newImage = imagescale($image, $newWidth, $newHeight);
                    if ($newImage) {
                        imagedestroy($image);
                        $image = $newImage;
                    }
                }
                
                // Als JPEG exportieren (kompatibel & kleiner)
                ob_start();
                // 85% Qualität ist ein guter Kompromiss für AI-Analyse
                imagejpeg($image, null, 85); 
                $imageData = ob_get_clean();
                $mimeType = 'image/jpeg';
                
                imagedestroy($image);
            }
        }
        
        return [
            'data' => base64_encode($imageData),
            'mime' => $mimeType
        ];
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
        return $this->provider->testConnection();
    }
}
