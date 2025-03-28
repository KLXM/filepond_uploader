<?php
class rex_api_filepond_uploader extends rex_api_function
{
    protected $published = true;
    protected $chunksDir = '';
    protected $metadataDir = '';

    // *** GLOBALE DEBUG-VARIABLE ***
    private $debug = false; // Standardmäßig: Debug-Meldungen deaktiviert

    public function __construct()
    {
        // Verzeichnisse erstellen, falls sie nicht existieren
        $baseDir = rex_path::pluginData('yform', 'manager', 'upload/filepond');

        $this->chunksDir = $baseDir . '/chunks';
        if (!file_exists($this->chunksDir)) {
            mkdir($this->chunksDir, 0775, true);
        }

        $this->metadataDir = $baseDir . '/metadata';
        if (!file_exists($this->metadataDir)) {
            mkdir($this->metadataDir, 0775, true);
        }
    }

    private function log($level, $message) {
        if ($this->debug) {
            $logger = rex_logger::factory();
            $logger->log($level, 'FILEPOND: ' . $message);
        }
    }

    public function execute()
    {
        try {
            $this->log('info', 'Starting execute()');

            // Authentifizierung prüfen
            if (!$this->isAuthorized()) {
                throw new rex_api_exception('Unauthorized access');
            }

            $func = rex_request('func', 'string', '');
            $categoryId = rex_request('category_id', 'int', 0);

            switch ($func) {
                case 'prepare':
                    // Vorbereitung eines Uploads - Metadaten speichern
                    $result = $this->handlePrepare();
                    rex_response::cleanOutputBuffers();
                    rex_response::sendJson($result);
                    exit;

                case 'upload':
                    // Standard-Upload für kleine Dateien
                    $result = $this->handleUpload($categoryId);
                    rex_response::cleanOutputBuffers();
                    rex_response::sendJson($result);
                    exit;

                case 'chunk-upload':
                    // Chunk-Upload für große Dateien
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

                case 'cleanup':
                    // Aufräumen temporärer Dateien
                    $result = $this->handleCleanup();
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

    protected function isAuthorized()
    {
        $this->log('info', 'Checking authorization');

        // Backend User Check
        $user = rex_backend_login::createUser();
        $isBackendUser = $user ? true : false;
        $this->log('info', 'isBackendUser = ' . ($isBackendUser ? 'true' : 'false'));

        // Token Check
        $apiToken = rex_config::get('filepond_uploader', 'api_token');
        $requestToken = rex_request('api_token', 'string', null);
        $sessionToken = rex_session('filepond_token', 'string', '');

        $isValidToken = ($apiToken && $requestToken && hash_equals($apiToken, $requestToken)) ||
            ($apiToken && $sessionToken && hash_equals($apiToken, $sessionToken));

        // YCom Check
        $isYComUser = false;
        if (rex_plugin::get('ycom', 'auth')->isAvailable()) {
            if (rex_ycom_auth::getUser()) {
                $isYComUser = true;
            }
        }

        $authorized = $isBackendUser || $isValidToken || $isYComUser;

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
            $this->log('error', 'Unauthorized - ' . implode(', ', $errors));
        }

        return $authorized;
    }

    protected function handlePrepare()
    {
        // Diese Methode wird aufgerufen, bevor ein Upload beginnt
        // Hier werden Metadaten gespeichert und ein eindeutiger fileId zurückgegeben

        $fileId = uniqid('filepond_', true);
        $metadata = json_decode(rex_post('metadata', 'string', '{}'), true);
        $fileName = rex_request('fileName', 'string', '');
        $fieldName = rex_request('fieldName', 'string', 'filepond');

        if (empty($fileName)) {
            throw new rex_api_exception('Missing filename');
        }

        $this->log('info', "Preparing upload for $fileName with ID $fileId");

        // Metadaten speichern
        $metaFile = $this->metadataDir . '/' . $fileId . '.json';
        file_put_contents($metaFile, json_encode([
            'metadata' => $metadata,
            'fileName' => $fileName,
            'fieldName' => $fieldName,
            'timestamp' => time()
        ]));

        // FileId zurückgeben, die für den Upload verwendet wird
        return [
            'fileId' => $fileId,
            'status' => 'ready'
        ];
    }

    protected function handleChunkUpload($categoryId)
    {
        // Chunk-Informationen aus dem Request holen
        $chunkIndex = rex_request('chunkIndex', 'int', 0);
        $totalChunks = rex_request('totalChunks', 'int', 1);
        $fileId = rex_request('fileId', 'string', '');
        $fieldName = rex_request('fieldName', 'string', 'filepond'); // Feldname für die Identifikation

        $logger = rex_logger::factory();

        if (empty($fileId)) {
            throw new rex_api_exception('Missing fileId');
        }

        $metaFile = $this->metadataDir . '/' . $fileId . '.json';

        if (!file_exists($metaFile)) {
            $logger->log('warning', "FILEPOND: Metadata file not found for $fileId, creating fallback metadata");

            // Fallback-Metadaten erstellen
            $fallbackMetadata = [
                'metadata' => [
                    'title' => pathinfo(rex_request('fileName', 'string', 'unknown'), PATHINFO_FILENAME),
                    'alt' => pathinfo(rex_request('fileName', 'string', 'unknown'), PATHINFO_FILENAME),
                    'copyright' => '',
                    'description' => 'Automatically generated metadata'
                ],
                'fileName' => rex_request('fileName', 'string', 'unknown'),
                'fieldName' => $fieldName,
                'timestamp' => time()
            ];

            // Verzeichnis erstellen, wenn es nicht existiert
            if (!file_exists($this->metadataDir)) {
                mkdir($this->metadataDir, 0775, true);
            }

            // Fallback-Metadaten speichern
            file_put_contents($metaFile, json_encode($fallbackMetadata));

            // Lokale Variable setzen
            $metaData = $fallbackMetadata;
        } else {
            $metaData = json_decode(file_get_contents($metaFile), true);
        }

        $fileName = $metaData['fileName'];
        $storedFieldName = $metaData['fieldName'] ?? 'filepond';

        // Überprüfen, ob das Feld übereinstimmt
        if ($fieldName !== $storedFieldName) {
            $logger->log('warning', "FILEPOND: Field name mismatch for $fileId. Expected $storedFieldName, got $fieldName");
        }

        $this->log('info', "Processing chunk $chunkIndex of $totalChunks for $fileName (ID: $fileId)");
        $this->log('debug', "chunkIndex = $chunkIndex, totalChunks = $totalChunks, fileId = $fileId, fieldName = $fieldName");

        // Chunk-Datei aus dem Upload holen
        if (!isset($_FILES[$fieldName])) {
            rex_response::setStatus(rex_response::HTTP_BAD_REQUEST);
            throw new rex_api_exception("No file chunk uploaded for field $fieldName");
        }

        $file = $_FILES[$fieldName];
        $this->log('debug', "\$_FILES[$fieldName] = " . print_r($file, true));

        // Verzeichnis für die Chunks dieses Files erstellen
        $fileChunkDir = $this->chunksDir . '/' . $fileId;
        if (!file_exists($fileChunkDir)) {
            if (!mkdir($fileChunkDir, 0775, true)) {
                throw new rex_api_exception("Failed to create chunk directory: $fileChunkDir");
            }
            $this->log('info', "Created chunk directory: $fileChunkDir");
        }

        // *** LOCK-MECHANISMUS ***
        $lockFile = $fileChunkDir . '/.lock';
        $lock = fopen($lockFile, 'w+');

        if (!flock($lock, LOCK_EX)) {  // Exklusives Lock anfordern
            fclose($lock);
            throw new rex_api_exception("Could not acquire lock for chunk directory: $fileChunkDir");
        }

        try {
            // Chunk speichern
            $chunkPath = $fileChunkDir . '/' . $chunkIndex;
            $this->log('debug', "Saving chunk to: $chunkPath, size = " . $file['size']);
            if (!move_uploaded_file($file['tmp_name'], $chunkPath)) {
                $error = error_get_last();
                $this->log('error', "move_uploaded_file failed: " . print_r($error, true));
                throw new rex_api_exception("Failed to save chunk $chunkIndex");
            }
            $this->log('info', "Saved chunk $chunkIndex successfully");

            // Prüfen ob alle Chunks hochgeladen wurden
            if ($chunkIndex == $totalChunks - 1) { // Letzter Chunk
                $this->log('info', "All chunks received for $fileName, merging...");

                // Temporäre Datei für das zusammengeführte Ergebnis
                $tmpFile = rex_path::pluginData('yform', 'manager', 'upload/filepond/') . $fileId;

                // Chunks zusammenführen
                $out = fopen($tmpFile, 'wb');
                if (!$out) {
                    throw new rex_api_exception('Could not create output file');
                }

                // *** PAUSE VOR DEM AUFLISTEN DER CHUNKS ***
                sleep(1);

                // *** DATEISYSTEM-CACHE LEEREN ***
                clearstatcache();

                //  *** ALTERNATIVE CHUNK-ZÄHLUNG ***
                $files = scandir($fileChunkDir);
                $actualChunks = 0;
                $chunkFiles = [];
                foreach ($files as $f) {
                    if ($f !== '.' && $f !== '..' && $f !== '.lock' && is_file($fileChunkDir . '/' . $f)) {
                        $actualChunks++;
                        $chunkFiles[] = $f;
                    }
                }

                $this->log('info', "Expected $totalChunks chunks, found $actualChunks for $fileName");
                $this->log('debug', "Chunk files: " . print_r($chunkFiles, true));

                if ($actualChunks < $totalChunks) {
                    $this->log('warning', "Expected $totalChunks chunks, but found only $actualChunks for $fileName");

                    fclose($out);
                    flock($lock, LOCK_UN);
                    fclose($lock);
                    @unlink($lockFile);
                    $this->cleanupChunks($fileChunkDir);
                    throw new rex_api_exception("Missing chunks, expected $totalChunks but found only $actualChunks");
                }

                // Chunks in der richtigen Reihenfolge zusammenfügen
                for ($i = 0; $i < $totalChunks; $i++) {
                    $chunkPath = $fileChunkDir . '/' . $i;
                    if (!file_exists($chunkPath)) {
                       fclose($out);
                        flock($lock, LOCK_UN);
                        fclose($lock);
                       @unlink($lockFile);
                        $this->cleanupChunks($fileChunkDir);
                       throw new rex_api_exception("Chunk $i is missing");
                    }

                    $in = fopen($chunkPath, 'rb');
                    if (!$in) {
                        fclose($out);
                        flock($lock, LOCK_UN);
                        fclose($lock);
                       @unlink($lockFile);
                       $this->cleanupChunks($fileChunkDir);
                        throw new rex_api_exception("Could not open chunk $i for reading");
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
                    'size' => filesize($tmpFile),
                    'metadata' => $metaData['metadata'] ?? []
                ];

                // Verarbeite die vollständige Datei
                $result = $this->processUploadedFile($uploadedFile, $categoryId);

                // Aufräumen - Chunks und Metadaten löschen
                $this->cleanupChunks($fileChunkDir);
                @unlink($metaFile);

                flock($lock, LOCK_UN); // Lock freigeben
                fclose($lock);
                @unlink($lockFile);

                // *** KONSISTENTES ANTWORTFORMAT MIT OUTPUT BUFFER LEEREN UND EXIT ***
                rex_response::cleanOutputBuffers();
                rex_response::sendJson([
                    'status' => 'chunk-success',
                    'filename' => $result // oder 'filename' => $result['filename'], je nachdem, was processUploadedFile zurückgibt
                ]);
                exit;
            }

            // Antwort für erfolgreichen Chunk-Upload
            flock($lock, LOCK_UN); // Lock freigeben
            fclose($lock);
            @unlink($lockFile);

            rex_response::cleanOutputBuffers();
            rex_response::sendJson([
                'status' => 'chunk-success',
                'chunkIndex' => $chunkIndex,
                'remaining' => $totalChunks - $chunkIndex - 1
            ]);
            exit;
        } catch (Exception $e) {
            if (isset($lock) && is_resource($lock)) {
                flock($lock, LOCK_UN); // Lock freigeben
                fclose($lock);
                @unlink($lockFile);
            }
            $this->cleanupChunks($fileChunkDir); //Räume die Chunks weg
            rex_response::cleanOutputBuffers();
            rex_response::sendJson(['error' => $e->getMessage()]);
            exit;
        }
    }

    protected function cleanupChunks($directory)
    {
        if (is_dir($directory)) {
            $files = glob($directory . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
            @rmdir($directory);
        }
    }

    protected function handleUpload($categoryId)
    {
        // Standard-Upload (kleine Dateien ohne Chunks)
        if (!isset($_FILES['filepond'])) {
            rex_response::setStatus(rex_response::HTTP_BAD_REQUEST);
            throw new rex_api_exception('No file uploaded');
        }

        $file = $_FILES['filepond'];
        $fileId = rex_request('fileId', 'string', '');

        // Metadaten aus der Vorbereitungsphase laden
        $metadata = [];
        if (!empty($fileId)) {
            $metaFile = $this->metadataDir . '/' . $fileId . '.json';
            if (file_exists($metaFile)) {
                $metaData = json_decode(file_get_contents($metaFile), true);
                $metadata = $metaData['metadata'] ?? [];

                // Metadatendatei löschen, da wir sie jetzt verarbeitet haben
                @unlink($metaFile);
            }
        }

        $file['metadata'] = $metadata;

        return $this->processUploadedFile($file, $categoryId);
    }

    protected function processUploadedFile($file, $categoryId)
    {
        $this->log('info', 'Processing file: ' . $file['name']);

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

        // *** DUPLIKATPRÜFUNG ***
        $media = rex_media::get($originalName);
        if ($media && $media->getSize() == $file['size']) {
            $this->log('info', "Duplicate file found, skipping upload");
            return $originalName; // Gib den vorhandenen Dateinamen zurück
        }

        // ... (Speichern der Datei im Medienpool) ...

        $metadata = $file['metadata'] ?? [];
        $skipMeta = empty($metadata) && rex_session('filepond_no_meta', 'boolean', false);

        if (!isset($categoryId) || $categoryId < 0) {
            $categoryId = rex_config::get('filepond_uploader', 'category_id', 0);
        }

        $data = [
            'title' => $metadata['title'] ?? rex_string::normalize(pathinfo($originalName, PATHINFO_FILENAME)),
            'category_id' => $categoryId,
            'file' => [
                'name' => $originalName,
                'tmp_name' => $file['tmp_name'],
                'type' => $file['type'],
                'size' => $file['size']
            ]
        ];

        try {
            $result = rex_media_service::addMedia($data, true);
            if ($result['ok']) {
                if (!$skipMeta && !empty($metadata)) {
                    $sql = rex_sql::factory();
                    $sql->setTable(rex::getTable('media'));
                    $sql->setWhere(['filename' => $result['filename']]);
                    $sql->setValue('title', $metadata['title'] ?? '');
                    $sql->setValue('med_alt', $metadata['med_alt'] ?? '');
                    $sql->setValue('med_copyright', $metadata['med_copyright'] ?? '');
                    $sql->setValue('med_description', $metadata['med_description'] ?? '');
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
                @unlink($file['tmp_name']);
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

    public function handleCleanup()
    {
        // Nur Backend-Benutzer mit Admin-Rechten dürfen aufräumen
        $user = rex_backend_login::createUser();
        if (!$user || !$user->isAdmin()) {
            throw new rex_api_exception('Unauthorized: Admin privileges required');
        }

        $this->log('info', 'Admin-triggered cleanup of temporary files');

        $cleanedChunks = 0;
        $cleanedMetadata = 0;

        // Alte Chunk-Verzeichnisse löschen (älter als 24h)
        $expireTime = time() - (24 * 60 * 60);
        $chunksDir = $this->chunksDir;

        if (is_dir($chunksDir)) {
            $chunkDirs = glob($chunksDir . '/*', GLOB_ONLYDIR);
            foreach ($chunkDirs as $dir) {
                $dirTime = filemtime($dir);
                if ($dirTime < $expireTime) {
                    $this->cleanupChunks($dir);
                    $cleanedChunks++;
                }
            }
        }

        // Alte Metadaten-Dateien löschen (älter als 24h)
        $metadataDir = $this->metadataDir;

        if (is_dir($metadataDir)) {
            $metaFiles = glob($metadataDir . '/*.json');
            foreach ($metaFiles as $file) {
                $fileTime = filemtime($file);
                if ($fileTime < $expireTime) {
                    @unlink($file);
                    $cleanedMetadata++;
                }
            }
        }

        return [
            'status' => 'success',
            'message' => "Cleanup completed. Removed $cleanedChunks chunk folders and $cleanedMetadata metadata files."
        ];
    }
}
