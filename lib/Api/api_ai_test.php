<?php

namespace KLXM\FilePond\Api;

use Exception;
use KLXM\FilePond\AiAltGenerator;
use Throwable;
use rex;
use rex_api_function;
use rex_api_result;
use rex_response;

/**
 * API-Klasse für AI-Verbindungstest.
 */
class rex_api_filepond_ai_test extends rex_api_function
{
    protected $published = false; // Nur für Backend-User

    /**
     * @param array<string, mixed> $data
     */
    private function sendJson(array $data): never
    {
        rex_response::cleanOutputBuffers();
        rex_response::sendJson($data);
        exit;
    }

    public function execute(): rex_api_result
    {
        // Prüfe ob User eingeloggt
        if (null === rex::getUser()) {
            $this->sendJson(['success' => false, 'message' => 'Nicht autorisiert']);
        }

        try {
            $generator = new AiAltGenerator();
            $result = $generator->testConnection();

            $this->sendJson($result);
        } catch (Throwable $e) {
            $this->sendJson([
                'success' => false,
                'message' => 'Fehler: ' . $e->getMessage(),
            ]);
        }
    }
}
