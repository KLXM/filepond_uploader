<?php

declare(strict_types=1);

namespace KLXM\FilePond;

/**
 * API Endpoint für Alt-Text-Checker.
 */
class AltCheckerApi extends \rex_api_function
{
    protected $published = false;  // Nur für eingeloggte Backend-User

    public function execute(): \rex_api_result
    {
        \rex_response::cleanOutputBuffers();

        // Berechtigung prüfen
        $user = \rex::getUser();
        if (!\rex::isBackend() || null === $user || (!$user->isAdmin() && !$user->hasPerm('filepond_uploader[alt_checker]'))) {
            $this->sendJson(['error' => 'Zugriff verweigert']);
        }

        $action = \rex_request('action', 'string');

        match ($action) {
            'list' => $this->handleList(),
            'stats' => $this->handleStats(),
            'update' => $this->handleUpdate(),
            'bulk_update' => $this->handleBulkUpdate(),
            'ai_generate' => $this->handleAiGenerate(),
            'ai_bulk_generate' => $this->handleAiBulkGenerate(),
            'ai_test' => $this->handleAiTest(),
            default => $this->sendJson(['error' => 'Unbekannte Aktion']),
        };
        return new \rex_api_result(true);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function sendJson(array $data): never
    {
        \rex_response::setHeader('Content-Type', 'application/json');
        \rex_response::sendContent((string) json_encode($data, JSON_UNESCAPED_UNICODE));
        exit;
    }

    private function handleList(): void
    {
        $filterFilename = \rex_request('filter_filename', 'string', '');
        $filterCategory = \rex_request('filter_category', 'int', -1);

        $filters = [];
        if ('' !== $filterFilename) {
            $filters['filename'] = $filterFilename;
        }
        if ($filterCategory >= 0) {
            $filters['category_id'] = $filterCategory;
        }

        try {
            // Prüfen ob med_alt Feld existiert
            if (!AltTextChecker::checkAltFieldExists()) {
                $this->sendJson([
                    'error' => 'Das Feld med_alt existiert nicht in der Medientabelle. Bitte lege es über MetaInfo an.',
                    'field_missing' => true,
                ]);
            }

            $images = AltTextChecker::findImagesWithoutAlt($filters);
            $stats = AltTextChecker::getStatistics();

            $this->sendJson([
                'images' => $images,
                'stats' => $stats,
            ]);
        } catch (\Exception $e) {
            $this->sendJson(['error' => $e->getMessage()]);
        }
    }

    private function handleStats(): void
    {
        try {
            if (!AltTextChecker::checkAltFieldExists()) {
                $this->sendJson([
                    'error' => 'Das Feld med_alt existiert nicht',
                    'field_missing' => true,
                ]);
            }

            $stats = AltTextChecker::getStatistics();
            $categories = AltTextChecker::getCategoriesWithMissingAlt();

            $this->sendJson([
                'stats' => $stats,
                'categories' => $categories,
            ]);
        } catch (\Exception $e) {
            $this->sendJson(['error' => $e->getMessage()]);
        }
    }

    private function handleUpdate(): void
    {
        $filename = \rex_request('filename', 'string', '');
        $altText = \rex_request('alt_text', 'string', '');
        $decorative = \rex_request('decorative', 'bool', false);
        $isMultilang = \rex_request('is_multilang', 'bool', false);

        if ('' === $filename) {
            $this->sendJson(['error' => 'Kein Dateiname angegeben']);
        }

        // Dekoratives Bild: In Negativ-Liste aufnehmen
        if ($decorative) {
            $result = AltTextChecker::markAsDecorative($filename);
        } else {
            // Mehrsprachig: JSON-String zu Array konvertieren
            if ($isMultilang && '' !== $altText) {
                $altData = json_decode($altText, true);
                if (is_array($altData)) {
                    $result = AltTextChecker::updateAltText($filename, $altData);
                } else {
                    $result = AltTextChecker::updateAltText($filename, $altText);
                }
            } else {
                $result = AltTextChecker::updateAltText($filename, $altText);
            }
        }

        $this->sendJson($result);
    }

    private function handleBulkUpdate(): void
    {
        $updatesRaw = \rex_request('updates', 'string', '');

        if ('' !== $updatesRaw && '[' === $updatesRaw[0]) {
            $updates = json_decode($updatesRaw, true) ?? [];
        } else {
            $updates = \rex_request('updates', 'array', []);
        }

        if ([] === $updates) {
            $this->sendJson(['error' => 'Keine Updates angegeben']);
        }

        $result = AltTextChecker::bulkUpdateAltText($updates);
        $this->sendJson($result);
    }

    /**
     * AI Alt-Text für ein einzelnes Bild generieren.
     */
    private function handleAiGenerate(): void
    {
        if (!AiAltGenerator::isEnabled()) {
            $this->sendJson(['error' => 'AI Alt-Text-Generierung ist nicht aktiviert oder API-Key fehlt']);
        }

        $filename = \rex_request('filename', 'string', '');
        $language = \rex_request('language', 'string', 'de');

        if ('' === $filename) {
            $this->sendJson(['error' => 'Kein Dateiname angegeben']);
        }

        $generator = new AiAltGenerator();
        $result = $generator->generateAltText($filename, $language);

        $this->sendJson($result);
    }

    /**
     * AI Alt-Texte für mehrere Bilder generieren.
     */
    private function handleAiBulkGenerate(): void
    {
        if (!AiAltGenerator::isEnabled()) {
            $this->sendJson(['error' => 'AI Alt-Text-Generierung ist nicht aktiviert oder API-Key fehlt']);
        }

        $filenamesRaw = \rex_request('filenames', 'string', '');
        $language = \rex_request('language', 'string', 'de');

        if ('' !== $filenamesRaw && '[' === $filenamesRaw[0]) {
            $filenames = json_decode($filenamesRaw, true) ?? [];
        } else {
            $filenames = \rex_request('filenames', 'array', []);
        }

        if ([] === $filenames) {
            $this->sendJson(['error' => 'Keine Dateinamen angegeben']);
        }

        $generator = new AiAltGenerator();
        $results = $generator->generateBulk($filenames, $language);

        $this->sendJson([
            'success' => true,
            'results' => $results,
        ]);
    }

    /**
     * AI-Verbindung testen.
     */
    private function handleAiTest(): void
    {
        if (!AiAltGenerator::isAvailable()) {
            $this->sendJson(['success' => false, 'message' => 'API-Key nicht konfiguriert']);
        }

        $generator = new AiAltGenerator();
        $result = $generator->testConnection();

        $this->sendJson($result);
    }
}
