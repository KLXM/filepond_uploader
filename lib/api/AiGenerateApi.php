<?php

declare(strict_types=1);

namespace KLXM\FilePond;

use Exception;
use rex;
use rex_api_function;
use rex_api_result;
use rex_path;
use rex_request as rex_request_class;
use rex_response;
use function rex_request;

/**
 * API Endpoint für AI Alt-Text Generierung.
 */
class AiGenerateApi extends rex_api_function
{
    protected $published = true;

    public function execute(): rex_api_result
    {
        // Berechtigung prüfen
        if (null === rex::getUser()) {
            rex_response::setStatus(rex_response::HTTP_UNAUTHORIZED);
            rex_response::sendJson(['error' => 'Unauthorized']);
            exit;
        }

        // Prüfen ob AI aktiviert ist
        if (!AiAltGenerator::isEnabled()) {
            rex_response::setStatus(rex_response::HTTP_FORBIDDEN);
            rex_response::sendJson(['error' => 'AI generation is disabled']);
            exit;
        }

        $fileId = rex_request('file_id', 'string', '');
        $mediaName = rex_request('media_name', 'string', '');
        $language = rex_request('language', 'string', 'de');

        $generator = new AiAltGenerator();
        $result = ['success' => false, 'error' => 'Unknown error'];

        try {
            // Fall 1: Existierendes Bild im Medienpool
            if ('' !== $mediaName) {
                $result = $generator->generateAltText($mediaName, $language);
            }
            // Fall 2: Temporärer Upload (FilePond)
            else {
                $filePath = '';

                // Check direct file upload (Client-side file)
                $files = rex_request_class::files('file', 'array', []);
                if (isset($files['tmp_name']) && is_string($files['tmp_name']) && '' !== $files['tmp_name']) {
                    $filePath = $files['tmp_name'];
                }
                // Check existing file by ID (Server-side file)
                elseif ('' !== $fileId) {
                    $baseDir = rex_path::addonData('filepond_uploader', 'upload');
                    $filePath = $baseDir . $fileId;
                }

                if ('' !== $filePath) {
                    $result = $generator->generateAltTextFromPath($filePath, $language);
                } else {
                    $result = ['success' => false, 'error' => 'No file provided'];
                }
            }
        } catch (Exception $e) {
            $result = ['success' => false, 'error' => $e->getMessage()];
        }

        rex_response::cleanOutputBuffers();
        rex_response::sendJson($result);
        exit;
    }
}
