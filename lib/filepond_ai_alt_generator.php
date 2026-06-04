<?php

/**
 * AI Alt-Text Generator für REDAXO.
 *
 * Unterstützt Google Gemini, Cloudflare Workers AI und OpenWebUI (OpenAI Compatible)
 *
 * @package filepond_uploader
 */

class filepond_ai_alt_generator
{
    // Verfügbare Provider
    public const PROVIDERS = [
        'gemini' => 'Google Gemini',
        'cloudflare' => 'Cloudflare Workers AI',
        'openwebui' => 'OpenWebUI / OpenAI Compatible',
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
    private filepond_ai_provider_interface $provider;

    /**
     * Constructor.
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
                    rex_config::get('filepond_uploader', 'cloudflare_model', '@cf/llava-hf/llava-1.5-7b-hf'),
                );
                break;

            case 'openwebui':
                $this->provider = new filepond_ai_provider_openai_compatible(
                    rex_config::get('filepond_uploader', 'openwebui_api_key', ''),
                    rex_config::get('filepond_uploader', 'openwebui_base_url', ''),
                    rex_config::get('filepond_uploader', 'openwebui_model', 'llava'),
                );
                break;

            case 'gemini':
            default:
                $this->provider = new filepond_ai_provider_gemini(
                    rex_config::get('filepond_uploader', 'gemini_api_key', ''),
                    rex_config::get('filepond_uploader', 'gemini_model', 'gemini-2.5-flash'),
                );
                break;
        }
    }

    /**
     * Gibt den aktuellen Provider zurück.
     */
    public static function getProvider(): string
    {
        return rex_config::get('filepond_uploader', 'ai_provider', 'gemini');
    }

    /**
     * Prüft ob die AI-Funktion verfügbar ist.
     */
    public static function isAvailable(): bool
    {
        // Wir erstellen eine Instanz, um die Konfiguration zu prüfen
        // Das ist sauberer als hier die Config-Logik zu duplizieren
        $generator = new self();
        return $generator->provider->isConfigured();
    }

    /**
     * Prüft ob die AI-Funktion aktiviert ist.
     */
    public static function isEnabled(): bool
    {
        $enabledRaw = rex_config::get('filepond_uploader', 'enable_ai_alt', '0');
        $enabled = in_array($enabledRaw, [1, '1', true, 'true', '|1|'], true);

        return $enabled && self::isAvailable();
    }

    /**
     * Generiert einen Alt-Text für ein Bild.
     *
     * @param string $filename Der Dateiname im Medienpool
     * @param string $language Zielsprache (de, en, etc.)
     * @return array{success: bool, alt_text: string, error: string|null}
     */
    public function generateAltText(string $filename, string $language = 'de'): array
    {
        if (!$this->provider->isConfigured()) {
            return [
                'success' => false,
                'alt_text' => '',
                'error' => 'AI Provider nicht korrekt konfiguriert',
            ];
        }

        $media = rex_media::get($filename);
        if (null === $media) {
            return [
                'success' => false,
                'alt_text' => '',
                'error' => 'Datei nicht gefunden',
            ];
        }

        // Nur Bilder verarbeiten
        if (!$media->isImage()) {
            return [
                'success' => false,
                'alt_text' => '',
                'error' => 'Keine Bilddatei',
            ];
        }

        // SVG nicht unterstützt (noch nicht)
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if ('svg' === $extension) {
            return [
                'success' => false,
                'alt_text' => '',
                'error' => 'SVG-Dateien werden nicht unterstützt',
            ];
        }

        $filePath = rex_path::media($filename);
        if (!file_exists($filePath)) {
            return [
                'success' => false,
                'alt_text' => '',
                'error' => 'Datei nicht auf dem Server gefunden',
            ];
        }

        return $this->executeGeneration($filePath, $language);
    }

    /**
     * Generiert einen Alt-Text für eine Datei anhand des Pfades.
     *
     * @param string $filePath Absoluter Pfad zur Datei
     * @param string $language Zielsprache
     * @return array{success: bool, alt_text: string, error: string|null}
     */
    public function generateAltTextFromPath(string $filePath, string $language = 'de'): array
    {
        if (!$this->provider->isConfigured()) {
            return [
                'success' => false,
                'alt_text' => '',
                'error' => 'AI Provider nicht korrekt konfiguriert',
            ];
        }

        if (!file_exists($filePath)) {
            return [
                'success' => false,
                'alt_text' => '',
                'error' => 'Datei nicht gefunden',
            ];
        }

        // Mime-Type prüfen
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($filePath);

        if (!is_string($mimeType) || !str_starts_with($mimeType, 'image/')) {
            return [
                'success' => false,
                'alt_text' => '',
                'error' => 'Keine Bilddatei',
            ];
        }

        if ('image/svg+xml' === $mimeType) {
            return [
                'success' => false,
                'alt_text' => '',
                'error' => 'SVG-Dateien werden nicht unterstützt',
            ];
        }

        return $this->executeGeneration($filePath, $language);
    }

    /**
     * Generiert Alt-Texte für mehrere Sprachen in einem einzigen Vision-Request.
     *
     * @param list<string> $languages Sprachcodes, z.B. ['de', 'en']
     * @return array{success: bool, alt_texts: array<string, string>, tokens?: array{prompt: int, response: int, total: int}|null, error: string|null}
     */
    public function generateAltTexts(string $filename, array $languages): array
    {
        if (!$this->provider->isConfigured()) {
            return [
                'success' => false,
                'alt_texts' => [],
                'error' => 'AI Provider nicht korrekt konfiguriert',
            ];
        }

        $media = rex_media::get($filename);
        if (null === $media) {
            return [
                'success' => false,
                'alt_texts' => [],
                'error' => 'Datei nicht gefunden',
            ];
        }

        if (!$media->isImage()) {
            return [
                'success' => false,
                'alt_texts' => [],
                'error' => 'Keine Bilddatei',
            ];
        }

        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if ('svg' === $extension) {
            return [
                'success' => false,
                'alt_texts' => [],
                'error' => 'SVG-Dateien werden nicht unterstützt',
            ];
        }

        $filePath = rex_path::media($filename);
        if (!file_exists($filePath)) {
            return [
                'success' => false,
                'alt_texts' => [],
                'error' => 'Datei nicht auf dem Server gefunden',
            ];
        }

        return $this->executeGenerationMultiple($filePath, $languages);
    }

    /**
     * Generiert Alt-Texte für mehrere Sprachen aus einem Dateipfad.
     *
     * @param list<string> $languages Sprachcodes, z.B. ['de', 'en']
     * @return array{success: bool, alt_texts: array<string, string>, tokens?: array{prompt: int, response: int, total: int}|null, error: string|null}
     */
    public function generateAltTextsFromPath(string $filePath, array $languages): array
    {
        if (!$this->provider->isConfigured()) {
            return [
                'success' => false,
                'alt_texts' => [],
                'error' => 'AI Provider nicht korrekt konfiguriert',
            ];
        }

        if (!file_exists($filePath)) {
            return [
                'success' => false,
                'alt_texts' => [],
                'error' => 'Datei nicht gefunden',
            ];
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($filePath);

        if (!is_string($mimeType) || !str_starts_with($mimeType, 'image/')) {
            return [
                'success' => false,
                'alt_texts' => [],
                'error' => 'Keine Bilddatei',
            ];
        }

        if ('image/svg+xml' === $mimeType) {
            return [
                'success' => false,
                'alt_texts' => [],
                'error' => 'SVG-Dateien werden nicht unterstützt',
            ];
        }

        return $this->executeGenerationMultiple($filePath, $languages);
    }

    /**
     * Interne Methode zur Ausführung der Generierung.
     *
     * @return array{success: bool, alt_text: string, error: string|null, tokens?: array{prompt: int, response: int, total: int}|null}
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
                'error' => $e->getMessage(),
            ];
        }

        // Prompt zusammenstellen
        $prompt = $this->buildPrompt($language);

        // Max Tokens holen
        $maxTokens = (int) rex_config::get('filepond_uploader', 'ai_max_tokens', 2048);
        if ($maxTokens <= 0) {
            $maxTokens = 2048;
        }

        // API Request via Provider
        try {
            $result = $this->provider->generate($base64Image, $mimeType, $prompt, $maxTokens);

            return [
                'success' => true,
                'alt_text' => $result['text'],
                'tokens' => $result['tokens'] ?? null,
                'error' => null,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'alt_text' => '',
                'tokens' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @param list<string> $languages
     * @return array{success: bool, alt_texts: array<string, string>, tokens?: array{prompt: int, response: int, total: int}|null, error: string|null}
     */
    private function executeGenerationMultiple(string $filePath, array $languages): array
    {
        $requestedLanguages = $this->normalizeLanguageCodes($languages);
        if ([] === $requestedLanguages) {
            $requestedLanguages = ['de'];
        }

        $fallbackLanguage = $this->getFallbackLanguageCode();
        $blockedLanguages = $this->getBlockedLanguageCodes();
        $blockedLanguages = array_values(array_diff($blockedLanguages, [$fallbackLanguage]));

        $directLanguages = array_values(array_diff($requestedLanguages, $blockedLanguages));

        $promptLanguages = $directLanguages;
        if (!in_array($fallbackLanguage, $promptLanguages, true)) {
            $promptLanguages[] = $fallbackLanguage;
        }

        try {
            $prepared = $this->prepareImage($filePath, true);
            $base64Image = $prepared['data'];
            $mimeType = $prepared['mime'];
        } catch (Exception $e) {
            return [
                'success' => false,
                'alt_texts' => [],
                'error' => $e->getMessage(),
            ];
        }

        $prompt = $this->buildMultiLanguagePrompt($promptLanguages);

        $maxTokens = (int) rex_config::get('filepond_uploader', 'ai_max_tokens', 2048);
        if ($maxTokens <= 0) {
            $maxTokens = 2048;
        }

        try {
            $result = $this->provider->generate($base64Image, $mimeType, $prompt, $maxTokens);
            $allAltTexts = $this->parseMultiLanguageResponse((string) ($result['text'] ?? ''), $promptLanguages);

            $resolvedAltTexts = [];
            $fallbackText = $allAltTexts[$fallbackLanguage] ?? '';

            foreach ($requestedLanguages as $language) {
                $isBlocked = in_array($language, $blockedLanguages, true);

                if ($isBlocked) {
                    if ('' !== trim($fallbackText)) {
                        $resolvedAltTexts[$language] = trim($fallbackText);
                    }
                    continue;
                }

                $directText = $allAltTexts[$language] ?? '';
                if ('' !== trim($directText)) {
                    $resolvedAltTexts[$language] = trim($directText);
                    continue;
                }

                if ('' !== trim($fallbackText)) {
                    $resolvedAltTexts[$language] = trim($fallbackText);
                }
            }

            if ([] === $resolvedAltTexts) {
                throw new Exception('Mehrsprachen-Antwort enthält keine verwertbaren Alt-Texte');
            }

            return [
                'success' => true,
                'alt_texts' => $resolvedAltTexts,
                'tokens' => $result['tokens'] ?? null,
                'error' => null,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'alt_texts' => [],
                'tokens' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Generiert Alt-Texte für mehrere Bilder (Bulk).
     *
     * @param list<string> $filenames Array von Dateinamen
     * @param string $language Zielsprache
     * @return array<string, array{success: bool, alt_text: string, error: string|null}> Array mit Ergebnissen pro Datei
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
     * Bereitet das Bild für die AI vor (Resize auf max. 1024px & Formatierung).
     *
     * @param string $path Pfad zum Bild oder Bild-Daten
     * @param bool $isPath True wenn $path ein Dateipfad ist
     * @throws Exception
     * @return array{data: string, mime: string}
     */
    private function prepareImage(string $path, bool $isPath = true): array
    {
        $configuredMaxDimension = (int) rex_config::get('filepond_uploader', 'ai_max_image_dimension', 1024);
        if ($configuredMaxDimension < 256) {
            $configuredMaxDimension = 256;
        }
        if ($configuredMaxDimension > 2048) {
            $configuredMaxDimension = 2048;
        }
        $maxDimension = $configuredMaxDimension;

        // Original laden
        $imageData = $isPath ? @file_get_contents($path) : $path;
        if (false === $imageData) {
            throw new Exception('Konnte Bilddatei nicht lesen');
        }

        // Mime Type ermitteln
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->buffer($imageData);

        // Wenn kein Bild, direkt Abbruch
        if (!is_string($mimeType) || !str_starts_with($mimeType, 'image/')) {
            throw new Exception('Ungültiges Bildformat: ' . $mimeType);
        }

        // Versuchen zu resizen mit GD
        if (extension_loaded('gd') && 'image/gif' !== $mimeType) {
            $image = @imagecreatefromstring($imageData);
            if (false !== $image) {
                $width = imagesx($image);
                $height = imagesy($image);

                // Nur resizen wenn größer als Max
                if ($width > $maxDimension || $height > $maxDimension) {
                    $ratio = $width / $height;
                    if ($width > $height) {
                        $newWidth = $maxDimension;
                        $newHeight = (int) ($maxDimension / $ratio);
                    } else {
                        $newHeight = $maxDimension;
                        $newWidth = (int) ($maxDimension * $ratio);
                    }

                    $newImage = imagescale($image, $newWidth, $newHeight);
                    if (false !== $newImage) {
                        $image = $newImage;
                    }
                }

                // Als JPEG exportieren (kompatibel & kleiner)
                ob_start();
                // 85% Qualität ist ein guter Kompromiss für AI-Analyse
                imagejpeg($image, null, 85);
                $obResult = ob_get_clean();
                if (is_string($obResult)) {
                    $imageData = $obResult;
                }
                $mimeType = 'image/jpeg';
            }
        }

        return [
            'data' => base64_encode($imageData),
            'mime' => $mimeType,
        ];
    }

    /**
     * Baut den Prompt für die AI.
     */
    private function buildPrompt(string $language = 'de'): string
    {
        // Custom Prompt aus Einstellungen laden
        $customPrompt = rex_config::get('filepond_uploader', 'ai_alt_prompt', '');

        if ('' !== $customPrompt && is_string($customPrompt)) {
            // Platzhalter ersetzen
            return str_replace(
                ['{language}', '{lang}'],
                [$this->getLanguageName($language), $language],
                $customPrompt,
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
     * @param list<string> $languages
     */
    private function buildMultiLanguagePrompt(array $languages): string
    {
        $languageParts = [];
        foreach ($languages as $language) {
            $languageParts[] = $language . ' (' . $this->getLanguageName($language) . ')';
        }

        $languageList = implode(', ', $languageParts);
        $jsonTemplateParts = [];
        foreach ($languages as $language) {
            $jsonTemplateParts[] = '  "' . $language . '": "<alt text in ' . $this->getLanguageName($language) . '>"';
        }

        $jsonTemplate = "{\n" . implode(",\n", $jsonTemplateParts) . "\n}";

        return <<<PROMPT
            Analysiere dieses Bild einmal und erstelle Alt-Texte für diese Sprachen: $languageList.

            Regeln:
            - Pro Sprache genau ein vollständiger Satz
            - Kurz und präzise (ca. 10-15 Wörter)
            - Keine Phrasen wie "Bild von", "Foto zeigt" oder "Abbildung"
            - Beschreibe konkret sichtbare Inhalte
            - Für Screenreader gut verständlich
            - Keine Halluzinationen über nicht sichtbare Details

            Antworte NUR mit gültigem JSON in exakt diesem Format und ohne weiteren Text:
            $jsonTemplate
            PROMPT;
    }

    /**
     * @param list<string> $languages
     * @return list<string>
     */
    private function normalizeLanguageCodes(array $languages): array
    {
        $normalized = [];

        foreach ($languages as $language) {
            if (!is_string($language)) {
                continue;
            }

            $trimmed = trim($language);
            if ('' === $trimmed) {
                continue;
            }

            $short = strtolower(substr($trimmed, 0, 2));
            if (!preg_match('/^[a-z]{2}$/', $short)) {
                continue;
            }

            if (!in_array($short, $normalized, true)) {
                $normalized[] = $short;
            }
        }

        return $normalized;
    }

    private function getFallbackLanguageCode(): string
    {
        $configured = rex_config::get('filepond_uploader', 'ai_fallback_language', 'en');
        if (!is_string($configured)) {
            return 'en';
        }

        $trimmed = trim($configured);
        if ('' === $trimmed) {
            return 'en';
        }

        $short = strtolower(substr($trimmed, 0, 2));
        if (!preg_match('/^[a-z]{2}$/', $short)) {
            return 'en';
        }

        return $short;
    }

    /**
     * @return list<string>
     */
    private function getBlockedLanguageCodes(): array
    {
        $configured = rex_config::get('filepond_uploader', 'ai_blocked_languages', '');
        if (!is_string($configured) || '' === trim($configured)) {
            return [];
        }

        $parts = preg_split('/[\s,;]+/', strtolower($configured));
        if (!is_array($parts)) {
            return [];
        }

        $normalized = [];
        foreach ($parts as $part) {
            if (!is_string($part) || '' === $part) {
                continue;
            }

            $short = substr($part, 0, 2);
            if (!preg_match('/^[a-z]{2}$/', $short)) {
                continue;
            }

            if (!in_array($short, $normalized, true)) {
                $normalized[] = $short;
            }
        }

        return $normalized;
    }

    /**
     * @param list<string> $languages
     * @return array<string, string>
     */
    private function parseMultiLanguageResponse(string $rawText, array $languages): array
    {
        $json = trim($rawText);

        if (str_starts_with($json, '```')) {
            $json = preg_replace('/^```(?:json)?\s*/i', '', $json) ?? $json;
            $json = preg_replace('/\s*```$/', '', $json) ?? $json;
            $json = trim($json);
        }

        if (!str_starts_with($json, '{')) {
            if (preg_match('/\{.*\}/s', $json, $matches) && isset($matches[0])) {
                $json = $matches[0];
            }
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            throw new Exception('Mehrsprachen-Antwort konnte nicht als JSON gelesen werden');
        }

        $result = [];
        foreach ($languages as $language) {
            $value = $decoded[$language] ?? null;
            if (!is_string($value)) {
                continue;
            }

            $clean = trim($value);
            if ('' !== $clean) {
                $result[$language] = $clean;
            }
        }

        if ([] === $result) {
            throw new Exception('Mehrsprachen-Antwort enthält keine verwertbaren Alt-Texte');
        }

        return $result;
    }

    /**
     * Gibt den Sprachnamen für den Prompt zurück.
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
            'sl' => 'Slowenisch',
            'pl' => 'Polnisch',
            'pt' => 'Portugiesisch',
            'ru' => 'Russisch',
            'zh' => 'Chinesisch',
            'ja' => 'Japanisch',
        ];

        // Sprache aus Code extrahieren (z.B. "de_de" -> "de")
        $shortCode = substr($code, 0, 2);

        return $languages[$shortCode] ?? strtoupper($shortCode);
    }

    /**
     * Testet die API-Verbindung.
     *
     * @return array{success: bool, message: string}
     */
    public function testConnection(): array
    {
        return $this->provider->testConnection();
    }
}
