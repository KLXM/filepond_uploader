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

    public function execute(): rex_api_result
    {
        // Prüfe ob User eingeloggt
        if (null === rex::getUser()) {
            rex_response::cleanOutputBuffers();
            rex_response::sendJson(['success' => false, 'message' => 'Nicht autorisiert']);
            exit;
        }

        try {
            $generator = new AiAltGenerator();
            $result = $generator->testConnection();

            rex_response::cleanOutputBuffers();
            rex_response::sendJson($result);
            exit;
        } catch (Throwable $e) {
            rex_response::cleanOutputBuffers();
            rex_response::sendJson([
                'success' => false,
                'message' => 'Fehler: ' . $e->getMessage(),
            ]);
            exit;
        }
    }
}
