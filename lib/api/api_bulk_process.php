<?php

namespace FriendsOfRedaxo\FilePondUploader;

use rex;
use rex_api_function;
use rex_request;
use rex_response;

/**
 * API Endpunkt für asynchrone Bulk-Verarbeitung
 *
 * @package filepond_uploader
 */
class ApiBulkProcess extends rex_api_function
{
    public function execute(): void
    {
        rex_response::cleanOutputBuffers();

        // Nur für Backend-Nutzer mit Rechten
        if (!rex::isBackend() || !rex::getUser() || !rex::getUser()->isAdmin()) {
            $this->sendJsonResponse(false, 'Zugriff verweigert');
        }

        $action = rex_request('action', 'string');

        switch ($action) {
            case 'start':
                $this->sendJsonResponse(true, $this->startBatch());
                break;
            case 'process':
                $this->sendJsonResponse(true, $this->processNext());
                break;
            case 'status':
                $this->sendJsonResponse(true, $this->getStatus());
                break;
            default:
                $this->sendJsonResponse(false, 'Unbekannte Aktion');
        }
    }

    private function sendJsonResponse(bool $success, $data): void
    {
        rex_response::sendJson([
            'success' => $success,
            'data' => $data,
        ]);
        exit;
    }

    private function startBatch(): array
    {
        $filenames = rex_request('filenames', 'array', []);
        $maxWidth = rex_request('maxWidth', 'int', null);
        $maxHeight = rex_request('maxHeight', 'int', null);

        if (empty($filenames)) {
            return ['error' => 'Keine Dateien angegeben'];
        }

        // Bereinige alte Batches
        BulkResize::cleanupOldBatches();

        $batchId = BulkResize::startBatchProcessing($filenames, $maxWidth, $maxHeight);

        return [
            'batchId' => $batchId,
            'status' => BulkResize::getBatchStatus($batchId),
        ];
    }

    private function processNext(): array
    {
        $batchId = rex_request('batchId', 'string');

        if (!$batchId) {
            return ['error' => 'Keine Batch-ID angegeben'];
        }

        $result = BulkResize::processNextBatchItems($batchId);

        return $result;
    }

    private function getStatus(): array
    {
        $batchId = rex_request('batchId', 'string');

        if (!$batchId) {
            return ['error' => 'Keine Batch-ID angegeben'];
        }

        $status = BulkResize::getBatchStatus($batchId);

        if (!$status) {
            return ['error' => 'Batch nicht gefunden'];
        }

        return $status;
    }
}
