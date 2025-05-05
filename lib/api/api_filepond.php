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
        $baseDir = rex_path::addonData('filepond_uploader', 'upload');

        $this->chunksDir = $baseDir . '/chunks';
        if (!file_exists($this->chunksDir)) {
            mkdir($this->chunksDir, 0775, true);
        }

        $this->metadataDir = $baseDir . '/metadata';
        if (!file_exists($this->metadataDir)) {
            mkdir($this->metadataDir, 0775, true);
        }
    }

    /**
     * Zentrale Methode für das Senden von JSON-Antworten
     * Stellt sicher, dass immer erst der Output Buffer geleert wird
     * und dass jede Antwort mit exit beendet wird
     *
     * @param mixed $data Die zu sendenden Daten
     * @param int $statusCode HTTP-Statuscode
     * @return void Diese Methode kehrt nicht zurück (exit)
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
                    $this->sendResponse($result);

                case 'upload':
                    // Standard-Upload für kleine Dateien
                    $result = $this->handleUpload($categoryId);
                    $this->sendResponse($result);

                case 'chunk-upload':
                    // Chunk-Upload für große Dateien
                    $this->handleChunkUpload($categoryId);
                    // Keine Rückkehr nötig, da sendResponse in handleChunkUpload bereits aufgerufen wird
                
                case 'finalize-upload':
                    // Finalisierung des Chunk-Uploads ohne einen weiteren Chunk zu senden
                    $result = $this->handleFinalizeUpload($categoryId);
                    $this->sendResponse($result);

                case 'delete':
                    $result = $this->handleDelete();
                    $this->sendResponse($result);
                
                case 'cancel-upload':
                    // Abbrechen des Metadaten-Dialogs: Hochgeladene Datei entfernen
                    $result = $this->handleCancelUpload();
                    $this->sendResponse($result);

                case 'load':
                    // Spezialfall: Sendet die Datei direkt
                    $this->handleLoad();
                    // Keine Rückkehr nötig, da sendFile in handleLoad bereits aufgerufen wird

                case 'restore':
                    $result = $this->handleRestore();
                    $this->sendResponse($result);

                case 'cleanup':
                    // Aufräumen temporärer Dateien
                    $result = $this->handleCleanup();
                    $this->sendResponse($result);

                default:
                    throw new rex_api_exception('Invalid function: ' . $func);
            }
        } catch (Exception $e) {
            rex_logger::logException($e);
            $this->sendResponse(['error' => $e->getMessage()], rex_response::HTTP_INTERNAL_ERROR);
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

        // Speichere den originalen Dateinamen für später
        $originalFileName = $fileName;

        // Eigene Normalisierung, die die Dateiendung behält
        $fileName = $this->normalizeFilename($fileName);
        $this->log('info', "Preparing upload for $fileName with ID $fileId");

        // Verzeichnis für Metadaten sicherstellen
        if (!file_exists($this->metadataDir)) {
            if (!mkdir($this->metadataDir, 0775, true)) {
                throw new rex_api_exception("Failed to create metadata directory: {$this->metadataDir}");
            }
        }

        // Metadaten speichern
        $metaFile = $this->metadataDir . '/' . $fileId . '.json';
        $metaData = [
            'metadata' => $metadata,
            'fileName' => $fileName,
            'originalFileName' => $originalFileName,
            'fieldName' => $fieldName,
            'timestamp' => time()
        ];
        
        if (!file_put_contents($metaFile, json_encode($metaData))) {
            throw new rex_api_exception("Failed to write metadata file: $metaFile");
        }

        // Erfolg zurückgeben
        return [
            'fileId' => $fileId,
            'status' => 'ready'
        ];
    }
    
    /**
     * Normalisiert einen Dateinamen, während die Dateiendung beibehalten wird
     */
    protected function normalizeFilename($filename)
    {
        // Dateiendung extrahieren
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $basename = pathinfo($filename, PATHINFO_FILENAME);
        
        // Basename normalisieren mit rex_string::normalize
        $normalizedBasename = rex_string::normalize($basename);
        
        // Wenn eine Endung vorhanden ist, wieder anhängen
        if ($extension) {
            return $normalizedBasename . '.' . $extension;
        }
        
        return $normalizedBasename;
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
                    'copyright' => ''
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

        // LOCK-MECHANISMUS: Stellt sicher, dass nur ein Prozess auf Chunks zugreift
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
                $this->log('info', "Last chunk received for $fileName, merging chunks...");

                // Temporäre Datei für das zusammengeführte Ergebnis im Addon-Data-Verzeichnis
                $tmpFile = rex_path::addonData('filepond_uploader', 'upload/') . $fileId;

                // Ältere temporäre Datei entfernen falls vorhanden
                if (file_exists($tmpFile)) {
                    @unlink($tmpFile);
                }

                // Chunks zusammenführen
                $out = fopen($tmpFile, 'wb');
                if (!$out) {
                    throw new rex_api_exception('Could not create output file');
                }

                // DATEISYSTEM-CACHE LEEREN vor dem Auflisten der Chunks
                clearstatcache();

                // Chunk-Zählung und Validierung
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
                
                // Sortierte Auflistung der gefundenen Chunks
                sort($chunkFiles, SORT_NUMERIC);
                $this->log('debug', "Chunk files (sorted): " . implode(', ', $chunkFiles));

                // Überprüfen ob Chunks fehlen
                if ($actualChunks < $totalChunks) {
                    $this->log('warning', "Expected $totalChunks chunks, but found only $actualChunks for $fileName");
                    
                    // Ressourcen freigeben
                    fclose($out);
                    flock($lock, LOCK_UN);
                    fclose($lock);
                    @unlink($lockFile);
                    
                    // Fehlende Chunks identifizieren
                    $missingChunks = [];
                    for ($i = 0; $i < $totalChunks; $i++) {
                        if (!in_array((string)$i, $chunkFiles)) {
                            $missingChunks[] = $i;
                        }
                    }
                    
                    $this->cleanupChunks($fileChunkDir);
                    throw new rex_api_exception("Missing chunks: " . implode(', ', $missingChunks) . 
                        ". Expected $totalChunks chunks but found only $actualChunks");
                }

                // Chunks in der richtigen Reihenfolge zusammenfügen
                for ($i = 0; $i < $totalChunks; $i++) {
                    $chunkPath = $fileChunkDir . '/' . $i;
                    if (!file_exists($chunkPath)) {
                        // Dieser Fall sollte nach der vorherigen Überprüfung eigentlich nie eintreten
                        fclose($out);
                        flock($lock, LOCK_UN);
                        fclose($lock);
                        @unlink($lockFile);
                        $this->cleanupChunks($fileChunkDir);
                        throw new rex_api_exception("Chunk $i is missing despite previous validation");
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

                    // Chunk zum Gesamtergebnis hinzufügen
                    $bytesWritten = stream_copy_to_stream($in, $out);
                    fclose($in);
                    $this->log('debug', "Added chunk $i to result file, $bytesWritten bytes written");
                }

                fclose($out);
                $this->log('info', "All chunks merged successfully");

                // Dateityp ermitteln
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $type = $finfo->file($tmpFile);
                $finalSize = filesize($tmpFile);
                
                $this->log('info', "Final file type: $type, size: $finalSize bytes");

                // WICHTIG: Die Datei wird NICHT mehr hier zum Medienpool hinzugefügt,
                // sondern erst in handleFinalizeUpload, um doppelte Einträge zu vermeiden

                flock($lock, LOCK_UN); // Lock freigeben
                fclose($lock);
                @unlink($lockFile);

                $this->sendResponse([
                    'status' => 'chunk-success',
                    'chunkIndex' => $chunkIndex,
                    'remaining' => 0
                ]);
            }

            // Antwort für erfolgreichen Chunk-Upload
            flock($lock, LOCK_UN); // Lock freigeben
            fclose($lock);
            @unlink($lockFile);

            $this->sendResponse([
                'status' => 'chunk-success',
                'chunkIndex' => $chunkIndex,
                'remaining' => $totalChunks - $chunkIndex - 1
            ]);
        } catch (Exception $e) {
            if (isset($lock) && is_resource($lock)) {
                flock($lock, LOCK_UN); // Lock freigeben
                fclose($lock);
                @unlink($lockFile);
            }
            $this->cleanupChunks($fileChunkDir); // Räume die Chunks weg
            $this->log('error', 'Chunk upload error: ' . $e->getMessage());
            $this->sendResponse(['error' => $e->getMessage()], rex_response::HTTP_BAD_REQUEST);
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
            throw new rex_api_exception('No file uploaded');
        }

        $file = $_FILES['filepond'];
        $fileId = rex_request('fileId', 'string', '');
        $fieldName = rex_request('fieldName', 'string', 'filepond');

        $this->log('info', "Processing standard upload for file: {$file['name']}, ID: $fileId");

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

        try {
            $result = $this->processUploadedFile($file, $categoryId);
            return [
                'status' => 'success',
                'filename' => $result
            ];
        } catch (Exception $e) {
            $this->log('error', 'Upload error: ' . $e->getMessage());
            throw $e;
        }
    }

    protected function processUploadedFile($file, $categoryId)
    {
        $this->log('info', 'Processing file: ' . $file['name']);

        // Validierung der Dateigröße
        $maxSize = rex_config::get('filepond_uploader', 'max_filesize', 10) * 1024 * 1024;
        if ($file['size'] > $maxSize) {
            throw new rex_api_exception('File too large');
        }

        // Sicherstellen, dass die temporäre Datei existiert
        if (!file_exists($file['tmp_name'])) {
            $this->log('error', "Temporary file not found: {$file['tmp_name']} - skipping upload");
            
            // Statt eines Exceptions geben wir einen "Erfolg" zurück,
            // aber mit dem ursprünglichen Dateinamen, damit FilePond nicht irritiert wird
            return $file['name']; // Erfolg zurückmelden, aber Upload überspringen
        }

        // Sicherstellen, dass der Dateiname eine Erweiterung hat
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (empty($fileExtension)) {
            $this->log('warning', "Missing file extension in: {$file['name']}");
            // Versuche, die Erweiterung aus dem MIME-Typ abzuleiten
            $mimeExtensionMap = [
                // Bilder
                'image/jpeg' => 'jpg',
                'image/pjpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp',
                'image/avif' => 'avif',
                'image/tiff' => 'tiff',
                'image/svg+xml' => 'svg',
                'application/postscript' => 'eps',
                
                // Dokumente
                'application/pdf' => 'pdf',
                'application/rtf' => 'rtf',
                'text/plain' => 'txt',
                'application/octet-stream' => 'bin',
                
                // Microsoft Office
                'application/msword' => 'doc',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
                'application/vnd.ms-excel' => 'xls',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
                'application/vnd.ms-powerpoint' => 'ppt',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
                'application/vnd.openxmlformats-officedocument.presentationml.template' => 'potx',
                'application/vnd.openxmlformats-officedocument.presentationml.slideshow' => 'ppsx',
                
                // Archive
                'application/x-zip-compressed' => 'zip',
                'application/zip' => 'zip',
                'application/x-gzip' => 'gz',
                'application/x-tar' => 'tar',
                
                // Audio/Video
                'video/quicktime' => 'mov',
                'audio/mpeg' => 'mp3',
                'video/mpeg' => 'mpg',
                'video/mp4' => 'mp4'
            ];
            
            $detectedMimeType = rex_file::mimeType($file['tmp_name']);
            if (isset($mimeExtensionMap[$detectedMimeType])) {
                $fileExtension = $mimeExtensionMap[$detectedMimeType];
                $file['name'] = $file['name'] . '.' . $fileExtension;
                $this->log('info', "Added file extension based on MIME type: {$file['name']}");
            } else {
               # throw new rex_api_exception('Dateiendung konnte nicht erkannt werden');
            }
        }
        
        // Verbesserte MIME-Typ-Erkennung für Chunk-Uploads mit REDAXO-eigenen Methoden
        if (strpos($file['tmp_name'], 'upload/filepond/') !== false) {
            // Dateityp neu bestimmen mit rex_file::mimeType (genauer als finfo)
            $detectedMimeType = rex_file::mimeType($file['tmp_name']);
            $this->log('debug', "MIME detection: extension=$fileExtension, original={$file['type']}, detected=$detectedMimeType");
            
            // Den erkannten MIME-Typ verwenden
            if ($detectedMimeType) {
                $file['type'] = $detectedMimeType;
                $this->log('info', "Using detected MIME type: {$file['type']}");
            }
            
            // Wenn der MIME-Typ immer noch nicht richtig ist, aus dem Mediapool die erlaubten MIME-Types holen
            $allowedMimeTypes = rex_mediapool::getAllowedMimeTypes();
            if (isset($allowedMimeTypes[$fileExtension]) && !in_array($file['type'], $allowedMimeTypes[$fileExtension])) {
                // Ersten erlaubten MIME-Typ für diese Dateiendung verwenden
                $file['type'] = $allowedMimeTypes[$fileExtension][0];
                $this->log('info', "Corrected MIME type to {$file['type']} based on mediapool configuration");
            }
        }
        
        // Bei Validierung zunächst prüfen, ob die Dateiendung überhaupt erlaubt ist
        if (!rex_mediapool::isAllowedExtension($file['name'])) {
            $this->log('error', "File extension not allowed: .$fileExtension");
            throw new rex_api_exception('File type not allowed');
        }

        // Dann prüfen, ob der MIME-Typ zur Dateiendung passt
        if (!rex_mediapool::isAllowedMimeType($file['tmp_name'], $file['name'])) {
            $this->log('error', "File MIME type not allowed: {$file['type']} for extension .$fileExtension");
            throw new rex_api_exception('File type not allowed');
        }

        // Bildoptimierung für unterstützte Formate (keine GIFs)
        if (strpos($file['type'], 'image/') === 0 && $file['type'] !== 'image/gif') {
            $this->processImage($file['tmp_name']);
        }

        $originalName = $file['name'];
        $metadata = $file['metadata'] ?? [];
        $skipMeta = rex_session('filepond_no_meta', 'boolean', false);
        
        // Direkt übergebenen Parameter mit höherer Priorität berücksichtigen
        if (rex_request('skipMeta', 'string', '') === '1') {
            $skipMeta = true;
        }

        if ($categoryId === null || $categoryId < 0) {
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
            // Erneut prüfen, ob die Datei noch existiert, direkt vor dem Upload
            if (!file_exists($file['tmp_name'])) {
                $this->log('error', "File not found before upload: {$file['tmp_name']}");
                return $file['name']; // Erfolg zurückmelden, aber Upload überspringen
            }
            
            // Übergebe die Datei an den MediaPool, der sich um Dateinamen-Duplizierung kümmert
            $result = rex_media_service::addMedia($data, true);
            if ($result['ok']) {
                if (!$skipMeta && !empty($metadata)) {
                    $sql = rex_sql::factory();
                    $sql->setTable(rex::getTable('media'));
                    $sql->setWhere(['filename' => $result['filename']]);
                    $sql->setValue('title', $metadata['title'] ?? '');
                    
                    // Prüfen, ob Bild als dekorativ markiert ist
                    $isDecorative = isset($metadata['decorative']) && $metadata['decorative'] === true;
                    
                    // Alt-Text und Copyright nur setzen, wenn nicht übersprungen und nicht dekorativ
                    if (!$skipMeta && !$isDecorative) {
                        $sql->setValue('med_alt', $metadata['alt'] ?? '');
                        $sql->setValue('med_copyright', $metadata['copyright'] ?? '');
                    } elseif ($isDecorative) {
                        // Bei dekorativen Bildern leeren Alt-Text setzen
                        $sql->setValue('med_alt', '');
                    }
                    
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
                        $this->sendResponse(['status' => 'success']);
                    } else {
                        throw new rex_api_exception('Could not delete file from media pool');
                    }
                } else {
                    $this->sendResponse(['status' => 'success']);
                }
            } else {
                $this->sendResponse(['status' => 'success']);
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
                rex_response::cleanOutputBuffers();
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
            $this->sendResponse(['status' => 'success']);
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
        
        // Debug-Logging NICHT temporär aktivieren, sondern nur verwenden, wenn es global aktiviert ist
        $this->log('info', 'Admin-triggered cleanup of temporary files');

        $cleanedChunks = 0;
        $cleanedMetadata = 0;
        $errors = [];
        $debugInfo = [];

        // Alte Chunk-Verzeichnisse löschen (älter als 1h statt 24h)
        $expireTime = time() - (60 * 60); // 1 Stunde
        $chunksDir = $this->chunksDir;
        
        $debugInfo['chunks_dir'] = $chunksDir;
        $debugInfo['metadata_dir'] = $this->metadataDir;
        $debugInfo['expire_time'] = date('Y-m-d H:i:s', $expireTime);
        $debugInfo['current_time'] = date('Y-m-d H:i:s');

        if (is_dir($chunksDir)) {
            $chunkDirs = glob($chunksDir . '/*', GLOB_ONLYDIR);
            $debugInfo['found_chunk_dirs'] = count($chunkDirs);
            
            foreach ($chunkDirs as $dir) {
                $dirTime = filemtime($dir);
                $dirAge = time() - $dirTime;
                $debugInfo['chunk_dirs'][] = [
                    'path' => $dir,
                    'modified' => date('Y-m-d H:i:s', $dirTime),
                    'age_seconds' => $dirAge,
                    'is_expired' => ($dirTime < $expireTime)
                ];
                
                if ($dirTime < $expireTime) {
                    try {
                        $this->log('info', "Cleaning up chunk directory: $dir (modified: ".date('Y-m-d H:i:s', $dirTime).")");
                        $this->cleanupChunks($dir);
                        $cleanedChunks++;
                    } catch (Exception $e) {
                        $errors[] = "Failed to clean chunk directory $dir: " . $e->getMessage();
                        $this->log('error', "Failed to clean chunk directory $dir: " . $e->getMessage());
                    }
                }
            }
        } else {
            $errors[] = "Chunks directory does not exist: $chunksDir";
            $this->log('error', "Chunks directory does not exist: $chunksDir");
            
            // Versuchen, das Verzeichnis zu erstellen
            try {
                mkdir($chunksDir, 0775, true);
                $this->log('info', "Created chunks directory: $chunksDir");
            } catch (Exception $e) {
                $errors[] = "Failed to create chunks directory: " . $e->getMessage();
                $this->log('error', "Failed to create chunks directory: " . $e->getMessage());
            }
        }

        // Alte Metadaten-Dateien löschen (älter als 24h)
        $metadataDir = $this->metadataDir;

        if (is_dir($metadataDir)) {
            $metaFiles = glob($metadataDir . '/*.json');
            $debugInfo['found_meta_files'] = count($metaFiles);
            
            foreach ($metaFiles as $file) {
                $fileTime = filemtime($file);
                $fileAge = time() - $fileTime;
                $debugInfo['meta_files'][] = [
                    'path' => $file,
                    'modified' => date('Y-m-d H:i:s', $fileTime),
                    'age_seconds' => $fileAge,
                    'is_expired' => ($fileTime < $expireTime)
                ];
                
                if ($fileTime < $expireTime) {
                    try {
                        $this->log('info', "Deleting metadata file: $file (modified: ".date('Y-m-d H:i:s', $fileTime).")");
                        if (!@unlink($file)) {
                            $errors[] = "Failed to delete metadata file: $file";
                            $this->log('error', "Failed to delete metadata file: $file");
                        } else {
                            $cleanedMetadata++;
                        }
                    } catch (Exception $e) {
                        $errors[] = "Failed to delete metadata file $file: " . $e->getMessage();
                        $this->log('error', "Failed to delete metadata file $file: " . $e->getMessage());
                    }
                }
            }
        } else {
            $errors[] = "Metadata directory does not exist: $metadataDir";
            $this->log('error', "Metadata directory does not exist: $metadataDir");
            
            // Versuchen, das Verzeichnis zu erstellen
            try {
                mkdir($metadataDir, 0775, true);
                $this->log('info', "Created metadata directory: $metadataDir");
            } catch (Exception $e) {
                $errors[] = "Failed to create metadata directory: " . $e->getMessage();
                $this->log('error', "Failed to create metadata directory: " . $e->getMessage());
            }
        }
        
        // Debug-Logging zurücksetzen
        $this->debug = $originalDebug;

        // Antwort mit detaillierten Informationen
        $response = [
            'status' => empty($errors) ? 'success' : 'partial_success',
            'message' => "Cleanup completed. Removed $cleanedChunks chunk folders and $cleanedMetadata metadata files."
        ];
        
        if (!empty($errors)) {
            $response['errors'] = $errors;
            $response['message'] .= " Encountered " . count($errors) . " errors.";
        }
        
        // Debug-Info nur im Backend anzeigen
        if (rex::isBackend() && rex::getUser() && rex::getUser()->isAdmin()) {
            $response['debug'] = $debugInfo;
        }

        return $response;
    }

    /**
     * Behandelt die Finalisierung eines Chunk-Uploads ohne neuen Chunk zu senden
     */
    protected function handleFinalizeUpload($categoryId)
    {
        // Dateiinformationen aus dem Request holen
        $fileId = rex_request('fileId', 'string', '');
        $fieldName = rex_request('fieldName', 'string', 'filepond');
        $fileName = rex_request('fileName', 'string', '');
        $totalChunks = rex_request('totalChunks', 'int', 0);

        $this->log('info', "Finalizing chunk upload for file: $fileName, ID: $fileId, total chunks: $totalChunks");

        if (empty($fileId)) {
            throw new rex_api_exception('Missing fileId');
        }

        // Metadaten laden
        $metaFile = $this->metadataDir . '/' . $fileId . '.json';
        
        if (!file_exists($metaFile)) {
            $this->log('warning', "Metadata file not found for $fileId, creating fallback metadata");
            
            // Fallback-Metadaten erstellen
            $fallbackMetadata = [
                'metadata' => [
                    'title' => pathinfo($fileName, PATHINFO_FILENAME),
                    'alt' => pathinfo($fileName, PATHINFO_FILENAME),
                    'copyright' => ''
                ],
                'fileName' => $fileName,
                'fieldName' => $fieldName,
                'timestamp' => time()
            ];

            // Verzeichnis erstellen, wenn es nicht existiert
            if (!file_exists($this->metadataDir)) {
                mkdir($this->metadataDir, 0775, true);
            }

            // Fallback-Metadaten speichern
            file_put_contents($metaFile, json_encode($fallbackMetadata));
            
            $metaData = $fallbackMetadata;
        } else {
            $metaData = json_decode(file_get_contents($metaFile), true);
        }

        // Temporäre Datei, die alle zusammengeführten Chunks enthält
        $tmpFile = rex_path::addonData('filepond_uploader', 'upload/') . $fileId;
        $fileChunkDir = $this->chunksDir . '/' . $fileId;

        // Überprüfen, ob die zusammengeführte Datei bereits existiert
        if (!file_exists($tmpFile)) {
            $this->log('info', "Merged file does not exist yet, merging chunks now");

            // Chunks zusammenführen
            $out = fopen($tmpFile, 'wb');
            if (!$out) {
                throw new rex_api_exception('Could not create output file');
            }

            // Dateisystem-Cache leeren vor dem Auflisten der Chunks
            clearstatcache();

            // Chunk-Zählung und Validierung
            if (!file_exists($fileChunkDir)) {
                throw new rex_api_exception("Chunk directory not found: $fileChunkDir");
            }

            $files = scandir($fileChunkDir);
            $actualChunks = 0;
            $chunkFiles = [];
            foreach ($files as $f) {
                if ($f !== '.' && $f !== '..' && $f !== '.lock' && is_file($fileChunkDir . '/' . $f)) {
                    $actualChunks++;
                    $chunkFiles[] = $f;
                }
            }

            $this->log('info', "Expected $totalChunks chunks, found $actualChunks");
            
            // Sortierte Auflistung der gefundenen Chunks
            sort($chunkFiles, SORT_NUMERIC);
            $this->log('debug', "Chunk files (sorted): " . implode(', ', $chunkFiles));

            if ($actualChunks < $totalChunks) {
                fclose($out);
                throw new rex_api_exception("Expected $totalChunks chunks, but found only $actualChunks");
            }

            // Chunks in der richtigen Reihenfolge zusammenfügen
            for ($i = 0; $i < $totalChunks; $i++) {
                $chunkPath = $fileChunkDir . '/' . $i;
                if (!file_exists($chunkPath)) {
                    fclose($out);
                    throw new rex_api_exception("Chunk $i is missing");
                }

                $in = fopen($chunkPath, 'rb');
                if (!$in) {
                    fclose($out);
                    throw new rex_api_exception("Could not open chunk $i for reading");
                }

                stream_copy_to_stream($in, $out);
                fclose($in);
            }

            fclose($out);
            $this->log('info', "All chunks merged successfully");
        } else {
            $this->log('info', "Using existing merged file: $tmpFile");
        }

        // Dateityp und Größe ermitteln
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $type = $finfo->file($tmpFile);
        $finalSize = filesize($tmpFile);
        
        $this->log('info', "Final file type: $type, size: $finalSize bytes");

        // Datei zum Medienpool hinzufügen
        $uploadedFile = [
            'name' => $metaData['fileName'] ?? $fileName,
            'type' => $type,
            'tmp_name' => $tmpFile,
            'size' => $finalSize,
            'metadata' => $metaData['metadata'] ?? []
        ];

        // skipMeta-Parameter berücksichtigen
        if (rex_request('skipMeta', 'string', '') === '1') {
            $_REQUEST['skipMeta'] = '1'; // Sicherstellen, dass der Parameter auch für processUploadedFile verfügbar ist
        }

        // Verarbeite die vollständige Datei
        $result = $this->processUploadedFile($uploadedFile, $categoryId);

        // Aufräumen - Chunks und Metadaten löschen
        $this->cleanupChunks($fileChunkDir);
        @unlink($metaFile);

        return [
            'status' => 'success',
            'filename' => $result, // Der tatsächliche Dateiname im Medienpool
            'originalname' => $fileName // Der ursprüngliche Dateiname
        ];
    }

    /**
     * Löscht eine Datei aus dem Medienpool, wenn der Metadaten-Dialog abgebrochen wurde
     * Diese Methode wird aufgerufen, wenn eine Datei zwar hochgeladen, aber der Metadaten-Dialog abgebrochen wurde
     * Die Datei soll dann nicht im Medienpool bleiben, sondern komplett gelöscht werden
     */
    protected function handleCancelUpload()
    {
        $filename = trim(rex_request('filename', 'string', ''));

        if (empty($filename)) {
            throw new rex_api_exception('Missing filename');
        }

        $this->log('info', "Removing file after metadata dialog was cancelled: $filename");

        try {
            $media = rex_media::get($filename);
            if ($media) {
                // Prüfen, ob die Datei in Verwendung ist, sollte normalerweise nicht der Fall sein
                // da sie gerade erst hochgeladen wurde und der Dialog abgebrochen wurde
                $inUse = false;

                // Lösche die Datei aus dem Medienpool
                if (rex_media_service::deleteMedia($filename)) {
                    $this->log('info', "Successfully removed file from media pool: $filename");
                    return [
                        'status' => 'success',
                        'message' => "File $filename removed successfully"
                    ];
                } else {
                    $this->log('warning', "Could not remove file from media pool: $filename");
                    return [
                        'status' => 'error',
                        'message' => "Could not remove file $filename from media pool"
                    ];
                }
            } else {
                $this->log('warning', "File not found in media pool: $filename");
                return [
                    'status' => 'success',
                    'message' => "File $filename not found in media pool"
                ];
            }
        } catch (Exception $e) {
            $this->log('error', 'Error removing file: ' . $e->getMessage());
            throw new rex_api_exception('Error removing file: ' . $e->getMessage());
        }
    }
}
