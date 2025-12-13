<?php

/**
 * API Endpoint für Bulk Resize - asynchrone Bildverarbeitung
 */
class rex_api_filepond_bulk_resize extends rex_api_function
{
    protected $published = false;  // Nur für eingeloggte Backend-User

    public function execute()
    {
        rex_response::cleanOutputBuffers();
        
        // Nur für Backend-Nutzer mit Admin-Rechten oder bulk_resize Berechtigung
        $user = rex::getUser();
        if (!rex::isBackend() || !$user || (!$user->isAdmin() && !$user->hasPerm('filepond_uploader[bulk_resize]'))) {
            $this->sendJson(['error' => 'Zugriff verweigert']);
            return;
        }

        $action = rex_request('action', 'string');
        
        switch ($action) {
            case 'list':
                $this->handleList();
                break;
            case 'start':
                $this->handleStart();
                break;
            case 'process':
                $this->handleProcess();
                break;
            case 'status':
                $this->handleStatus();
                break;
            case 'cancel':
                $this->handleCancel();
                break;
            default:
                $this->sendJsonResponse(false, ['message' => 'Unbekannte Aktion']);
        }
    }

    private function sendJsonResponse(bool $success, $data): void
    {
        rex_response::setHeader('Content-Type', 'application/json');
        rex_response::sendContent(json_encode([
            'success' => $success,
            'data' => $data
        ]));
        exit;
    }

    /**
     * Direktes JSON Response senden (für einfachere Struktur)
     */
    private function sendJson(array $data): void
    {
        rex_response::setHeader('Content-Type', 'application/json');
        rex_response::sendContent(json_encode($data, JSON_UNESCAPED_UNICODE));
        exit;
    }

    /**
     * Liste der übergroßen Bilder laden
     */
    private function handleList(): void
    {
        $maxWidth = rex_request('max_width', 'int', 0);
        $maxHeight = rex_request('max_height', 'int', 0);
        $filterFilename = rex_request('filter_filename', 'string', '');
        $filterCategory = rex_request('filter_category', 'int', -1);
        $page = max(1, rex_request('page', 'int', 1));
        $perPage = max(1, rex_request('per_page', 'int', 50));

        $filters = [];
        if (!empty($filterFilename)) {
            $filters['filename'] = $filterFilename;
        }
        if ($filterCategory >= 0) {
            $filters['category_id'] = (string)$filterCategory;
        }

        try {
            $all = filepond_bulk_resize::findOversizedImages($maxWidth, $maxHeight, $filters);
            $total = count($all);
            $offset = ($page - 1) * $perPage;
            $images = array_slice($all, $offset, $perPage);
            $this->sendJson(['images' => $images, 'page' => $page, 'per_page' => $perPage, 'total' => $total]);
        } catch (Exception $e) {
            $this->sendJson(['error' => $e->getMessage()]);
        }
    }

    private function handleStart(): void
    {
        // Unterstütze beide Varianten: JSON-String oder Array
        $filenamesRaw = rex_request('filenames', 'string', '');
        if (!empty($filenamesRaw) && $filenamesRaw[0] === '[') {
            $filenames = json_decode($filenamesRaw, true) ?: [];
        } else {
            $filenames = rex_request('filenames', 'array', []);
        }
        
        $maxWidth = rex_request('max_width', 'int', rex_request('maxWidth', 'int', 0));
        $maxHeight = rex_request('max_height', 'int', rex_request('maxHeight', 'int', 0));
        $quality = rex_request('quality', 'int', 85);

        if (empty($filenames)) {
            $this->sendJson(['error' => 'Keine Dateien ausgewählt']);
        }

        if ($maxWidth <= 0 && $maxHeight <= 0) {
            $this->sendJson(['error' => 'Max. Breite oder Höhe muss angegeben werden']);
        }

        // Alte Batches aufräumen
        filepond_bulk_resize::cleanupOldBatches();

        // Batch starten
        $batchId = filepond_bulk_resize::startBatch($filenames, $maxWidth, $maxHeight, $quality);
        $status = filepond_bulk_resize::getBatchStatusExtended($batchId);

        $this->sendJson([
            'batch_id' => $batchId,
            'batch' => $status
        ]);
    }

    private function handleProcess(): void
    {
        $batchId = rex_request('batch_id', 'string', rex_request('batchId', 'string'));

        if (!$batchId) {
            $this->sendJson(['error' => 'Keine Batch-ID angegeben']);
        }

        $result = filepond_bulk_resize::processNextBatchItems($batchId);
        
        $this->sendJson($result);
    }

    private function handleStatus(): void
    {
        $batchId = rex_request('batch_id', 'string', rex_request('batchId', 'string'));

        if (!$batchId) {
            $this->sendJson(['error' => 'Keine Batch-ID angegeben']);
        }

        $status = filepond_bulk_resize::getBatchStatusExtended($batchId);

        if (!$status) {
            $this->sendJson(['error' => 'Batch nicht gefunden']);
        }

        $this->sendJson(['batch' => $status]);
    }

    private function handleCancel(): void
    {
        $batchId = rex_request('batch_id', 'string', rex_request('batchId', 'string'));

        if (!$batchId) {
            $this->sendJson(['error' => 'Keine Batch-ID angegeben']);
        }

        $status = filepond_bulk_resize::getBatchStatus($batchId);
        
        if ($status) {
            $status['status'] = 'cancelled';
            $status['endTime'] = time();
            
            // Status speichern
            $cacheDir = rex_path::addonCache('filepond_uploader');
            rex_file::put(
                $cacheDir . '/filepond_bulk_resize_' . $batchId . '.json',
                json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );
        }

        $this->sendJson(['message' => 'Batch abgebrochen', 'status' => 'cancelled']);
    }
}
