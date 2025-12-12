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
            
            $images = filepond_alt_text_checker::findImagesWithoutAlt($filters);
            $stats = filepond_alt_text_checker::getStatistics();
            
            $this->sendJson([
                'images' => $images,
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

        if (empty($filename)) {
            $this->sendJson(['error' => 'Kein Dateiname angegeben']);
            return;
        }

        // Dekoratives Bild: In Negativ-Liste aufnehmen
        if ($decorative) {
            $result = filepond_alt_text_checker::markAsDecorative($filename);
        } else {
            $result = filepond_alt_text_checker::updateAltText($filename, $altText);
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
}
