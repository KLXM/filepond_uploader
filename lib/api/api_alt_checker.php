<?php

/**
 * API Endpoint für Alt-Text-Checker
 */
class rex_api_filepond_alt_checker extends rex_api_function
{
    protected $published = false;  // Nur für eingeloggte Backend-User

    public function execute()
    {
        rex_response::cleanOutputBuffers();
        
        // Berechtigung prüfen
        $user = rex::getUser();
        if (!rex::isBackend() || !$user || (!$user->isAdmin() && !$user->hasPerm('filepond_uploader[alt_checker]'))) {
            $this->sendJson(['error' => 'Zugriff verweigert']);
            return;
        }

        $action = rex_request('action', 'string');
        
        switch ($action) {
            case 'list':
                $this->handleList();
                break;
            case 'stats':
                $this->handleStats();
                break;
            case 'update':
                $this->handleUpdate();
                break;
            case 'bulk_update':
                $this->handleBulkUpdate();
                break;
            case 'ai_generate':
                $this->handleAiGenerate();
                break;
            case 'ai_bulk_generate':
                $this->handleAiBulkGenerate();
                break;
            case 'ai_test':
                $this->handleAiTest();
                break;
            default:
                $this->sendJson(['error' => 'Unbekannte Aktion']);
        }
    }

    private function sendJson(array $data): void
    {
        rex_response::setHeader('Content-Type', 'application/json');
        rex_response::sendContent(json_encode($data, JSON_UNESCAPED_UNICODE));
        exit;
    }

    private function handleList(): void
    {
        $filterFilename = rex_request('filter_filename', 'string', '');
        $filterCategory = rex_request('filter_category', 'int', -1);
        $page = rex_request('page', 'int', 1);
        $perPage = rex_request('per_page', 'int', 50);

        $filters = [];
        if (!empty($filterFilename)) {
            $filters['filename'] = $filterFilename;
        }
        if ($filterCategory >= 0) {
            $filters['category_id'] = $filterCategory;
        }

        try {
            // Prüfen ob med_alt Feld existiert
            if (!filepond_alt_text_checker::checkAltFieldExists()) {
                $this->sendJson([
                    'error' => 'Das Feld med_alt existiert nicht in der Medientabelle. Bitte lege es über MetaInfo an.',
                    'field_missing' => true
                ]);
                return;
            }
            
            $result = filepond_alt_text_checker::findImagesWithoutAlt($filters, $page, $perPage);
            $stats = filepond_alt_text_checker::getStatistics();
            
            $this->sendJson([
                'images' => $result['items'],
                'pagination' => [
                    'total' => $result['total'],
                    'page' => $result['page'],
                    'perPage' => $result['perPage'],
                    'totalPages' => $result['totalPages']
                ],
                'stats' => $stats
            ]);
        } catch (Exception $e) {
            $this->sendJson(['error' => $e->getMessage()]);
        }
    }

    private function handleStats(): void
    {
        try {
            if (!filepond_alt_text_checker::checkAltFieldExists()) {
                $this->sendJson([
                    'error' => 'Das Feld med_alt existiert nicht',
                    'field_missing' => true
                ]);
                return;
            }
            
            $stats = filepond_alt_text_checker::getStatistics();
            $categories = filepond_alt_text_checker::getCategoriesWithMissingAlt();
            
            $this->sendJson([
                'stats' => $stats,
                'categories' => $categories
            ]);
        } catch (Exception $e) {
            $this->sendJson(['error' => $e->getMessage()]);
        }
    }

    private function handleUpdate(): void
    {
        $filename = rex_request('filename', 'string', '');
        $altText = rex_request('alt_text', 'string', '');
        $decorative = rex_request('decorative', 'bool', false);
        $isMultilang = rex_request('is_multilang', 'bool', false);

        if (empty($filename)) {
            $this->sendJson(['error' => 'Kein Dateiname angegeben']);
            return;
        }

        // Dekoratives Bild: In Negativ-Liste aufnehmen
        if ($decorative) {
            $result = filepond_alt_text_checker::markAsDecorative($filename);
        } else {
            // Mehrsprachig: JSON-String zu Array konvertieren
            if ($isMultilang && !empty($altText)) {
                $altData = json_decode($altText, true);
                if (is_array($altData)) {
                    $result = filepond_alt_text_checker::updateAltText($filename, $altData);
                } else {
                    $result = filepond_alt_text_checker::updateAltText($filename, $altText);
                }
            } else {
                $result = filepond_alt_text_checker::updateAltText($filename, $altText);
            }
        }
        
        $this->sendJson($result);
    }

    private function handleBulkUpdate(): void
    {
        $updatesRaw = rex_request('updates', 'string', '');
        
        if (!empty($updatesRaw) && $updatesRaw[0] === '[') {
            $updates = json_decode($updatesRaw, true) ?: [];
        } else {
            $updates = rex_request('updates', 'array', []);
        }

        if (empty($updates)) {
            $this->sendJson(['error' => 'Keine Updates angegeben']);
            return;
        }

        $result = filepond_alt_text_checker::bulkUpdateAltText($updates);
        $this->sendJson($result);
    }

    /**
     * AI Alt-Text für ein einzelnes Bild generieren
     */
    private function handleAiGenerate(): void
    {
        if (!filepond_ai_alt_generator::isEnabled()) {
            $this->sendJson(['error' => 'AI Alt-Text-Generierung ist nicht aktiviert oder API-Key fehlt']);
            return;
        }
        
        $filename = rex_request('filename', 'string', '');
        $language = rex_request('language', 'string', 'de');
        
        if (empty($filename)) {
            $this->sendJson(['error' => 'Kein Dateiname angegeben']);
            return;
        }
        
        $generator = new filepond_ai_alt_generator();
        $result = $generator->generateAltText($filename, $language);
        
        $this->sendJson($result);
    }
    
    /**
     * AI Alt-Texte für mehrere Bilder generieren
     */
    private function handleAiBulkGenerate(): void
    {
        if (!filepond_ai_alt_generator::isEnabled()) {
            $this->sendJson(['error' => 'AI Alt-Text-Generierung ist nicht aktiviert oder API-Key fehlt']);
            return;
        }
        
        $filenamesRaw = rex_request('filenames', 'string', '');
        $language = rex_request('language', 'string', 'de');
        
        if (!empty($filenamesRaw) && $filenamesRaw[0] === '[') {
            $filenames = json_decode($filenamesRaw, true) ?: [];
        } else {
            $filenames = rex_request('filenames', 'array', []);
        }
        
        if (empty($filenames)) {
            $this->sendJson(['error' => 'Keine Dateinamen angegeben']);
            return;
        }
        
        $generator = new filepond_ai_alt_generator();
        $results = $generator->generateBulk($filenames, $language);
        
        $this->sendJson([
            'success' => true,
            'results' => $results
        ]);
    }
    
    /**
     * AI-Verbindung testen
     */
    private function handleAiTest(): void
    {
        if (!filepond_ai_alt_generator::isAvailable()) {
            $this->sendJson(['success' => false, 'message' => 'API-Key nicht konfiguriert']);
            return;
        }
        
        $generator = new filepond_ai_alt_generator();
        $result = $generator->testConnection();
        
        $this->sendJson($result);
    }
}
