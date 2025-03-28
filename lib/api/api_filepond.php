<?php
class rex_api_filepond_uploader extends rex_api_function
{
    protected $published = true;
    protected $chunksDir = '';
    
    public function __construct()
    {
        // Verzeichnis für Chunks erstellen falls es nicht existiert
        $this->chunksDir = rex_path::pluginData('yform', 'manager', 'upload/filepond/chunks');
        if (!file_exists($this->chunksDir)) {
            mkdir($this->chunksDir, 0775, true);
        }
    }

    public function execute()
    {
        try {
            $logger = rex_logger::factory();
            $logger->log('info', 'FILEPOND: Starting execute()');

            // Backend User Check
            $user = rex_backend_login::createUser();
            $isBackendUser = $user ? true : false;

            $logger->log('info', 'FILEPOND: isBackendUser = ' . ($isBackendUser ? 'true' : 'false'));

            // Token Check
            $apiToken = rex_config::get('filepond_uploader', 'api_token');
            $requestToken = rex_request('api_token', 'string', null);
            $sessionToken = rex_session('filepond_token', 'string', '');

            $isValidToken = ($apiToken && $requestToken && hash_equals($apiToken, $requestToken)) ||
                ($apiToken && $sessionToken && hash_equals($apiToken, $sessionToken));

            $authorized = false;

            $isYComUser = false;
            if (rex_plugin::get('ycom', 'auth')->isAvailable()) {
                if (rex_ycom_auth::getUser()) {
                    $authorized = true;
                    $isYComUser = true;
                }
            }

            if ($isBackendUser) {
                $authorized = true;
            }
            if ($isValidToken) {
                $authorized = true;
            }

            if (!$authorized) {
                $errors = [];
                if (!$isYComUser) {
                    $errors[] = 'no YCom login';
                }
                if (!$isBackendUser) {
                    $errors[] = 'no Backend login';
                }
                if (!$isValidToken) {
                    $errors[] = 'invalid API token';
                }
                throw new rex_api_exception('Unauthorized access - ' . implode(', ', $errors));
            }

            $func = rex_request('func', 'string', '');
            $categoryId = rex_request('category_id', 'int', 0);

            switch ($func) {
                case 'upload':
                    $result = $this->handleUpload($categoryId);
                    rex_response::cleanOutputBuffers();
                    rex_response::sendJson($result);
                    exit;
                
                case 'chunk-upload':
                    $result = $this->handleChunkUpload($categoryId);
                    rex_response::cleanOutputBuffers();
                    rex_response::sendJson($result);
                    exit;

                case 'delete':
                    $result = $this->handleDelete();
                    rex_response::cleanOutputBuffers();
                    rex_response::sendJson($result);
                    exit;

                case 'load':
                    return $this->handleLoad();

                case 'restore':
                    $result = $this->handleRestore();
                    rex_response::cleanOutputBuffers();
                    rex_response::sendJson($result);
                    exit;

                default:
                    throw new rex_api_exception('Invalid function');
            }
        } catch (Exception $e) {
            rex_logger::logException($e);
            rex_response::cleanOutputBuffers();
            rex_response::setStatus(rex_response::HTTP_INTERNAL_ERROR);
            rex_response::sendJson(['error' => $e->getMessage()]);
            exit;
        }
    }

    protected function handleChunkUpload($categoryId)
    {
        // Chunk-Informationen aus dem Request holen
        $chunkIndex = rex_request('chunkIndex', 'int', 0);
        $totalChunks = rex_request('totalChunks', 'int', 1);
        $fileId = rex_request('fileId', 'string', '');
        $fileName = rex_request('fileName', 'string', '');
        
        if (empty($fileId) || empty($fileName)) {
            throw new rex_api_exception('Missing chunk information');
        }
        
        $logger = rex_logger::factory();
        $logger->log('info', "FILEPOND: Processing chunk $chunkIndex of $totalChunks for file $fileName (ID: $fileId)");
        
        // Chunk-Datei aus dem Upload holen
        if (!isset($_FILES['filepond'])) {
            rex_response::setStatus(rex_response::HTTP_BAD_REQUEST);
            throw new rex_api_exception('No file chunk uploaded');
        }
        
        $file = $_FILES['filepond'];
        
        // Verzeichnis für die Chunks dieses Files erstellen
        $fileChunkDir = $this->chunksDir . '/' . $fileId;
        if (!file_exists($fileChunkDir)) {
            mkdir($fileChunkDir, 0775, true);
        }
        
        // Chunk speichern
        $chunkPath = $fileChunkDir . '/' . $chunkIndex;
        if (!move_uploaded_file($file['tmp_name'], $chunkPath)) {
            throw new rex_api_exception("Failed to save chunk $chunkIndex");
        }
        
        // Prüfen ob alle Chunks hochgeladen wurden
        if ($chunkIndex == $totalChunks - 1) { // Letzter Chunk
            $logger->log('info', "FILEPOND: All chunks received for $fileName, merging...");
            
            // Temporäre Datei für das zusammengeführte Ergebnis
            $tmpFile = rex_path::pluginData('yform', 'manager', 'upload/filepond/') . $fileId;
            
            // Chunks zusammenführen
            $out = fopen($tmpFile, 'wb');
            if (!$out) {
                throw new rex_api_exception('Could not create output file');
            }
            
            // Chunks in der richtigen Reihenfolge zusammenfügen
            for ($i = 0; $i < $totalChunks; $i++) {
                $chunkPath = $fileChunkDir . '/' . $i;
                if (!file_exists($chunkPath)) {
                    fclose($out);
                    throw new rex_api_exception("Chunk $i missing");
                }
                
                $in = fopen($chunkPath, 'rb');
                if (!$in) {
                    fclose($out);
                    throw new rex_api_exception("Could not open chunk $i");
                }
                
                stream_copy_to_stream($in, $out);
                fclose($in);
            }
            
            fclose($out);
            
            // Dateityp ermitteln
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $type = $finfo->file($tmpFile);
            
            // Datei zum Medienpool hinzufügen
            $uploadedFile = [
                'name' => $fileName,
                'type' => $type,
                'tmp_name' => $tmpFile,
                'size' => filesize($tmpFile)
            ];
            
            // Verarbeite die vollständige Datei
            $result = $this->processUploadedFile($uploadedFile, $categoryId);
            
            // Aufräumen - Chunks löschen
            $this->cleanupChunks($fileChunkDir);
            
            return $result;
        }
        
        // Antwort für erfolgreichen Chunk-Upload
        return [
            'status' => 'chunk-success',
            'chunkIndex' => $chunkIndex,
            'remaining' => $totalChunks - $chunkIndex - 1
        ];
    }

    protected function cleanupChunks($directory)
    {
        if (is_dir($directory)) {
            $files = glob($directory . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($directory);
        }
    }

    protected function handleUpload($categoryId)
    {
        if (!isset($_FILES['filepond'])) {
            rex_response::setStatus(rex_response::HTTP_BAD_REQUEST);
            throw new rex_api_exception('No file uploaded');
        }

        $file = $_FILES['filepond'];
        
        return $this->processUploadedFile($file, $categoryId);
    }
    
    protected function processUploadedFile($file, $categoryId)
    {
        $logger = rex_logger::factory();
        $logger->log('info', 'FILEPOND: Processing file: ' . $file['name']);
        
        // Validierung der Dateigröße
        $maxSize = rex_config::get('filepond_uploader', 'max_filesize', 10) * 1024 * 1024;
        if ($file['size'] > $maxSize) {
            throw new rex_api_exception('File too large');
        }

        // Validierung der Dateitypen
        $allowedTypes = rex_config::get('filepond_uploader', 'allowed_types', 'image/*,video/*,.pdf,.doc,.docx,.txt');
        $allowedTypes = array_map('trim', explode(',', $allowedTypes));
        $isAllowed = false;

        foreach ($allowedTypes as $type) {
            if (strpos($type, '*') !== false) {
                $baseType = str_replace('*', '', $type);
                if (strpos($file['type'], $baseType) === 0) {
                    $isAllowed = true;
                    break;
                }
            } elseif (strpos($type, '.') === 0) {
                if (strtolower(substr($file['name'], -strlen($type))) === strtolower($type)) {
                    $isAllowed = true;
                    break;
                }
            } else {
                if ($file['type'] === $type) {
                    $isAllowed = true;
                    break;
                }
            }
        }

        if (!$isAllowed) {
            throw new rex_api_exception('File type not allowed');
        }

        // Bildoptimierung für unterstützte Formate (keine GIFs)
        if (strpos($file['type'], 'image/') === 0 && $file['type'] !== 'image/gif') {
            $this->processImage($file['tmp_name']);
        }

        $originalName = $file['name'];
        $filename = rex_string::normalize(pathinfo($originalName, PATHINFO_FILENAME));

        // Prüfen ob Metadaten übersprungen werden sollen
        $skipMeta = rex_session('filepond_no_meta', 'boolean', false);
        $metadata = [];

        if (!$skipMeta) {
            $metadata = json_decode(rex_post('metadata', 'string', '{}'), true);
        }

        if (!isset($categoryId) || $categoryId < 0) {
            $categoryId = rex_config::get('filepond_uploader', 'category_id', 0);
        }

        $data = [
            'title' => $metadata['title'] ?? $filename,
            'category_id' => $categoryId,
            'file' => [
                'name' => $originalName,
                'tmp_name' => $file['tmp_name'],
                'type' => $file['type'],
                'size' => filesize($file['tmp_name'])
            ]
        ];

        try {
            $result = rex_media_service::addMedia($data, true);
            if ($result['ok']) {
                if (!$skipMeta) {
                    $sql = rex_sql::factory();
                    $sql->setTable(rex::getTable('media'));
                    $sql->setWhere(['filename' => $result['filename']]);
                    $sql->setValue('title', $metadata['title'] ?? '');
                    $sql->setValue('med_alt', $metadata['alt'] ?? '');
                    $sql->setValue('med_copyright', $metadata['copyright'] ?? '');
                    $sql->setValue('med_description', $metadata['description'] ?? '');
                    $sql->update();
                }

                return $result['filename'];
            }

            throw new rex_api_exception(implode(', ', $result['messages']));
        } catch (Exception $e) {
            throw new rex_api_exception('Upload failed: ' . $e->getMessage());
        } finally {
            // Aufräumen, wenn die Datei eine temporäre war (Chunk-Upload)
            if (strpos($file['tmp_name'], 'upload/filepond/') !== false && file_exists($file['tmp_name'])) {
                unlink($file['tmp_name']);
            }
        }
    }

    protected function processImage($tmpFile)
    {
        $maxPixel = rex_config::get('filepond_uploader', 'max_pixel', 1200);
        $quality = rex_config::get('filepond_uploader', 'image_quality', 90);

        $imageInfo = getimagesize($tmpFile);
        if (!$imageInfo) {
            return;
        }

        list($width, $height, $type) = $imageInfo;

        // Return if image is smaller than max dimensions
        if ($width <= $maxPixel && $height <= $maxPixel) {
            return;
        }

        // Calculate new dimensions
        $ratio = $width / $height;
        if ($width > $height) {
            $newWidth = min($width, $maxPixel);
            $newHeight = floor($newWidth / $ratio);
        } else {
            $newHeight = min($height, $maxPixel);
            $newWidth = floor($newHeight * $ratio);
        }

        // Create new image based on type
        $srcImage = null;
        switch ($type) {
            case IMAGETYPE_JPEG:
                $srcImage = imagecreatefromjpeg($tmpFile);
                break;
            case IMAGETYPE_PNG:
                $srcImage = imagecreatefrompng($tmpFile);
                break;
            case IMAGETYPE_WEBP:
                $srcImage = imagecreatefromwebp($tmpFile);
                break;
            default:
                return;
        }

        if (!$srcImage) {
            return;
        }

        $dstImage = imagecreatetruecolor($newWidth, $newHeight);

        // Preserve transparency for PNG images
        if ($type === IMAGETYPE_PNG) {
            imagealphablending($dstImage, false);
            imagesavealpha($dstImage, true);
            $transparent = imagecolorallocatealpha($dstImage, 255, 255, 255, 127);
            imagefilledrectangle($dstImage, 0, 0, $newWidth, $newHeight, $transparent);
        }

        // Resize image
        imagecopyresampled(
            $dstImage,
            $srcImage,
            0,
            0,
            0,
            0,
            $newWidth,
            $newHeight,
            $width,
            $height
        );

        // Save image
        if ($type === IMAGETYPE_JPEG) {
            imagejpeg($dstImage, $tmpFile, $quality);
        } elseif ($type === IMAGETYPE_PNG) {
            // PNG-Qualität ist 0-9, umrechnen auf 0-9 Skala
            $pngQuality = min(9, floor($quality / 10));
            imagepng($dstImage, $tmpFile, $pngQuality);
        } elseif ($type === IMAGETYPE_WEBP) {
            imagewebp($dstImage, $tmpFile, $quality);
        }

        // Free memory
        imagedestroy($srcImage);
        imagedestroy($dstImage);
    }

    // Rest der Methoden (handleDelete, handleLoad, handleRestore) bleiben unverändert...
    protected function handleDelete()
    {
        $filename = trim(rex_request('filename', 'string', ''));

        if (empty($filename)) {
            throw new rex_api_exception('Missing filename');
        }

        try {
            $media = rex_media::get($filename);
            if ($media) {
                $inUse = false;

                $sql = rex_sql::factory();
                $yformTables = rex_yform_manager_table::getAll();

                foreach ($yformTables as $table) {
                    foreach ($table->getFields() as $field) {
                        if ($field->getType() === 'value' && $field->getTypeName() === 'filepond') {
                            $tableName = $sql->escapeIdentifier($table->getTableName());
                            $fieldName = $sql->escapeIdentifier($field->getName());
                            $filePattern = '%' . str_replace(['%', '_'], ['\%', '\_'], $filename) . '%';
                            $query = "SELECT id FROM $tableName WHERE $fieldName LIKE :filename";

                            try {
                                $result = $sql->getArray($query, [':filename' => $filePattern]);
                                if (count($result) > 0) {
                                    $inUse = true;
                                    break 2;
                                }
                            } catch (Exception $e) {
                                continue;
                            }
                        }
                    }
                }

                if (!$inUse) {
                    if (rex_media_service::deleteMedia($filename)) {
                        rex_response::sendJson(['status' => 'success']);
                        exit;
                    } else {
                        throw new rex_api_exception('Could not delete file from media pool');
                    }
                } else {
                    rex_response::sendJson(['status' => 'success']);
                    exit;
                }
            } else {
                rex_response::sendJson(['status' => 'success']);
                exit;
            }
        } catch (rex_api_exception $e) {
            throw new rex_api_exception('Error deleting file: ' . $e->getMessage());
        }
    }

    protected function handleLoad()
    {
        $filename = rex_request('filename', 'string');
        if (empty($filename)) {
            throw new rex_api_exception('Missing filename');
        }

        $media = rex_media::get($filename);
        if ($media) {
            $file = rex_path::media($filename);
            if (file_exists($file)) {
                rex_response::sendFile(
                    $file,
                    $media->getType(),
                    'inline',
                    $media->getFileName()
                );
                exit;
            }
        }

        throw new rex_api_exception('File not found');
    }

    protected function handleRestore()
    {
        $filename = rex_request('filename', 'string');
        if (empty($filename)) {
            throw new rex_api_exception('Missing filename');
        }

        if (rex_media::get($filename)) {
            rex_response::sendJson(['status' => 'success']);
            exit;
        } else {
            throw new rex_api_exception('File not found in media pool');
        }
    }
}
