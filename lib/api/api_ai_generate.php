<?php
/**
 * API Endpoint für AI Alt-Text Generierung
 * 
 * @package filepond_uploader
 */

class rex_api_filepond_ai_generate extends rex_api_function
{
    protected $published = true;

    public function execute(): rex_api_result
    {
        // Berechtigung prüfen
        if (rex::getUser() === null) {
            rex_response::setStatus(rex_response::HTTP_UNAUTHORIZED);
            rex_response::sendJson(['error' => 'Unauthorized']);
            exit;
        }

        // Prüfen ob AI aktiviert ist
        if (!filepond_ai_alt_generator::isEnabled()) {
            rex_response::setStatus(rex_response::HTTP_FORBIDDEN);
            rex_response::sendJson(['error' => 'AI generation is disabled']);
            exit;
        }

        $fileId = rex_request('file_id', 'string', '');
        $mediaName = rex_request('media_name', 'string', '');
        $language = rex_request('language', 'string', 'de');
        
        $generator = new filepond_ai_alt_generator();
        $result = ['success' => false, 'error' => 'Unknown error'];

        try {
            // Fall 1: Existierendes Bild im Medienpool
            if ($mediaName !== '') {
                $result = $generator->generateAltText($mediaName, $language);
            }
            // Fall 2: Temporärer Upload (FilePond)
            else {
                $filePath = '';
                
                // Check direct file upload (Client-side file)
                /** @var array<string, array{tmp_name?: string}> $files */
                $files = rex_request::files('file', 'array', []);
                if (isset($files['tmp_name']) && is_string($files['tmp_name']) && $files['tmp_name'] !== '') {
                    $filePath = $files['tmp_name'];
                } 
                // Check existing file by ID (Server-side file)
                elseif ($fileId !== '') {
                    $baseDir = rex_path::addonData('filepond_uploader', 'upload');
                    $filePath = $baseDir . $fileId;
                }
                
                if ($filePath !== '') {
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
