<?php

declare(strict_types=1);

namespace KLXM\FilePond;

/**
 * API-Klasse für AI-Verbindungstest.
 */
class AiTestApi extends \rex_api_function
{
    protected $published = false; // Nur für Backend-User

    public function execute(): \rex_api_result
    {
        try {
            $generator = new AiAltGenerator();
            $result = $generator->testConnection();

            \rex_response::cleanOutputBuffers();
            \rex_response::sendJson($result);
            exit;
        } catch (\Throwable $e) {
            \rex_response::cleanOutputBuffers();
            \rex_response::sendJson([
                'success' => false,
                'message' => 'Fehler: ' . $e->getMessage(),
            ]);
            exit;
        }
    }
}
