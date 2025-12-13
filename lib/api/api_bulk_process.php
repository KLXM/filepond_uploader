<?php

/**
 * API Endpunkt für asynchrone Bulk-Verarbeitung
 *
 * @package filepond_uploader
 */
class rex_api_filepond_bulk_process extends rex_api_function
{
    protected $published = true;

    /**
     * Zentrale Methode für das Senden von JSON-Antworten
     */
    protected function sendResponse($data, $statusCode = 200)
    {
        rex_response::cleanOutputBuffers();
        if ($statusCode !== 200) {
            rex_response::setStatus($statusCode);
        }
        rex_response::sendJson($data);
        exit;
    }

    public function execute()
    {
        // Nur für Backend-Nutzer mit Rechten
        if (!rex::isBackend() || !rex::getUser() || !rex::getUser()->isAdmin()) {
            $this->sendResponse([
                'success' => false,
                'error' => 'Zugriff verweigert'
            ], 403);
        }

        $action = rex_request('action', 'string', '');

        switch ($action) {
            case 'start':
                $result = $this->startBatch();
                $this->sendResponse([
                    'success' => true,
                    'data' => $result
                ]);
                break;
                
            case 'process':
                $result = $this->processNext();
                $this->sendResponse([
                    'success' => true,
                    'data' => $result
                ]);
                break;
                
            case 'status':
                $result = $this->getStatus();
                $this->sendResponse([
                    'success' => true,
                    'data' => $result
                ]);
                break;
                
            default:
                $this->sendResponse([
                    'success' => false,
                    'error' => 'Unbekannte Aktion: "' . $action . '"'
                ], 400);
        }
    }

    private function startBatch(): array
    {
        $filenames = rex_request('filenames', 'array', []);
        $maxWidth = rex_request('maxWidth', 'int', null);
        $maxHeight = rex_request('maxHeight', 'int', null);

        if (empty($filenames)) {
            return ['error' => 'Keine Dateien angegeben'];
        }

        // Bereinige alte Batches - verwende vollständigen Klassennamen
        \FriendsOfRedaxo\FilePondUploader\BulkResize::cleanupOldBatches();

        $batchId = \FriendsOfRedaxo\FilePondUploader\BulkResize::startBatchProcessing($filenames, $maxWidth, $maxHeight);

        return [
            'batchId' => $batchId,
            'status' => \FriendsOfRedaxo\FilePondUploader\BulkResize::getBatchStatus($batchId),
        ];
    }

    private function processNext(): array
    {
        $batchId = rex_request('batchId', 'string');

        if (!$batchId) {
            return ['error' => 'Keine Batch-ID angegeben'];
        }

        $result = \FriendsOfRedaxo\FilePondUploader\BulkResize::processNextBatchItems($batchId);

        return $result;
    }

    private function getStatus(): array
    {
        $batchId = rex_request('batchId', 'string');

        if (!$batchId) {
            return ['error' => 'Keine Batch-ID angegeben'];
        }

        $status = \FriendsOfRedaxo\FilePondUploader\BulkResize::getBatchStatus($batchId);

        if (!$status) {
            return ['error' => 'Batch nicht gefunden'];
        }

        return $status;
    }
}
