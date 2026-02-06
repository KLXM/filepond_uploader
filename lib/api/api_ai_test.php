<?php

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

        $generatorFile = rex_path::addon('filepond_uploader', 'lib/filepond_ai_alt_generator.php');

        if (!file_exists($generatorFile)) {
            rex_response::cleanOutputBuffers();
            rex_response::sendJson(['success' => false, 'message' => 'filepond_ai_alt_generator.php nicht gefunden']);
            exit;
        }

        require_once $generatorFile;

        if (!class_exists('filepond_ai_alt_generator')) {
            rex_response::cleanOutputBuffers();
            rex_response::sendJson(['success' => false, 'message' => 'Klasse filepond_ai_alt_generator nicht gefunden']);
            exit;
        }

        try {
            $generator = new filepond_ai_alt_generator();
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
