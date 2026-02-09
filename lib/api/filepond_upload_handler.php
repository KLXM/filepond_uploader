<?php

/**
 * Upload-Verarbeitung für FilePond.
 *
 * Enthält Prepare, Upload, Chunk-Upload, Finalize und
 * alle upload-bezogenen Operationen.
 */
class filepond_upload_handler
{
    private bool $debug = false;
    private string $chunksDir;
    private string $metadataDir;
    private filepond_image_processor $imageProcessor;
    private filepond_metadata_handler $metadataHandler;
    private filepond_file_manager $fileManager;

    public function __construct(
        string $chunksDir,
        string $metadataDir,
        filepond_image_processor $imageProcessor,
        filepond_metadata_handler $metadataHandler,
        filepond_file_manager $fileManager,
        bool $debug = false,
    ) {
        $this->chunksDir = $chunksDir;
        $this->metadataDir = $metadataDir;
        $this->imageProcessor = $imageProcessor;
        $this->metadataHandler = $metadataHandler;
        $this->fileManager = $fileManager;
        $this->debug = $debug;
    }

    private function log(string $level, string $message): void
    {
        if ($this->debug) {
            $logger = rex_logger::factory();
            /** @phpstan-ignore psr3.interpolated */
            $logger->log($level, 'FILEPOND: {message}', ['message' => $message]);
        }
    }

    /**
     * Normalisiert einen Dateinamen, während die Dateiendung beibehalten wird.
     */
    public function normalizeFilename(string $filename): string
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $basename = pathinfo($filename, PATHINFO_FILENAME);
        $normalizedBasename = rex_string::normalize($basename);

        if ('' !== $extension) {
            return $normalizedBasename . '.' . $extension;
        }

        return $normalizedBasename;
    }

    /**
     * @return array{fileId: string, status: string}
     */
    public function handlePrepare(): array
    {
        $fileId = uniqid('filepond_', true);
        $metadata = json_decode(rex_post('metadata', 'string', '{}'), true);
        $fileName = rex_request('fileName', 'string', '');
        $fieldName = rex_request('fieldName', 'string', 'filepond');

        if ('' === $fileName) {
            throw new rex_api_exception('Missing filename');
        }

        $originalFileName = $fileName;
        $fileName = $this->normalizeFilename($fileName);
        $this->log('info', "Preparing upload for $fileName with ID $fileId");

        if (!rex_dir::create($this->metadataDir)) {
            throw new rex_api_exception("Failed to create metadata directory: {$this->metadataDir}");
        }

        $metaFile = $this->metadataDir . '/' . $fileId . '.json';
        $metaData = [
            'metadata' => $metadata,
            'fileName' => $fileName,
            'originalFileName' => $originalFileName,
            'fieldName' => $fieldName,
            'timestamp' => time(),
        ];

        if (!rex_file::put($metaFile, (string) json_encode($metaData))) {
            throw new rex_api_exception("Failed to write metadata file: $metaFile");
        }

        return [
            'fileId' => $fileId,
            'status' => 'ready',
        ];
    }

    /**
     * @return array{status: string, filename: string}
     */
    public function handleUpload(int $categoryId): array
    {
        $file = rex_request::files('filepond', 'array', []);
        if (!isset($file['tmp_name']) || '' === $file['tmp_name']) {
            throw new rex_api_exception('No file uploaded');
        }

        $fileId = rex_request('fileId', 'string', '');
        $fieldName = rex_request('fieldName', 'string', 'filepond');

        $this->log('info', "Processing standard upload for file: {$file['name']}, ID: $fileId");

        $metadata = [];
        if ('' !== $fileId) {
            $metaFile = $this->metadataDir . '/' . $fileId . '.json';
            if (file_exists($metaFile)) {
                $fileContent = rex_file::get($metaFile);
                $metaData = (null !== $fileContent) ? json_decode($fileContent, true) : [];
                $metadata = is_array($metaData) ? ($metaData['metadata'] ?? []) : [];
                rex_file::delete($metaFile);
            }
        }

        $file['metadata'] = $metadata;

        try {
            /** @var array{name: string, tmp_name: string, type: string, size: int, metadata?: array<string, mixed>} $file */
            $result = $this->processUploadedFile($file, $categoryId);
            return [
                'status' => 'success',
                'filename' => $result,
            ];
        } catch (Exception $e) {
            $this->log('error', 'Upload error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * @param array{name: string, tmp_name: string, type: string, size: int, metadata?: array<string, mixed>} $file
     */
    public function processUploadedFile(array $file, int $categoryId): string
    {
        $this->log('info', 'Processing file: ' . $file['name']);

        // Validierung der Dateigröße
        $maxSize = (int) rex_config::get('filepond_uploader', 'max_filesize', 10) * 1024 * 1024;
        if ($file['size'] > $maxSize) {
            throw new rex_api_exception('File too large');
        }

        if (!file_exists($file['tmp_name'])) {
            $this->log('error', "Temporary file not found: {$file['tmp_name']} - skipping upload");
            return $file['name'];
        }

        // Dateiendung prüfen und ggf. aus MIME-Typ ableiten
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ('' === $fileExtension) {
            $this->log('warning', "Missing file extension in: {$file['name']}");
            $mimeExtensionMap = $this->getMimeExtensionMap();
            $detectedMimeType = rex_file::mimeType($file['tmp_name']);
            if (isset($mimeExtensionMap[$detectedMimeType])) {
                $fileExtension = $mimeExtensionMap[$detectedMimeType];
                $file['name'] = $file['name'] . '.' . $fileExtension;
                $this->log('info', "Added file extension based on MIME type: {$file['name']}");
            }
        }

        // Verbesserte MIME-Typ-Erkennung für Chunk-Uploads
        if (str_contains($file['tmp_name'], 'upload/filepond/')) {
            $detectedMimeType = rex_file::mimeType($file['tmp_name']);
            $this->log('debug', "MIME detection: extension=$fileExtension, original={$file['type']}, detected=$detectedMimeType");

            if (null !== $detectedMimeType) {
                $file['type'] = $detectedMimeType;
                $this->log('info', "Using detected MIME type: {$file['type']}");
            }

            $allowedMimeTypes = rex_mediapool::getAllowedMimeTypes();
            if (isset($allowedMimeTypes[$fileExtension]) && !in_array($file['type'], $allowedMimeTypes[$fileExtension], true)) {
                $file['type'] = $allowedMimeTypes[$fileExtension][0];
                $this->log('info', "Corrected MIME type to {$file['type']} based on mediapool configuration");
            }
        }

        if (!rex_mediapool::isAllowedExtension($file['name'])) {
            $this->log('error', "File extension not allowed: .$fileExtension");
            throw new rex_api_exception('File type not allowed');
        }

        if (!rex_mediapool::isAllowedMimeType($file['tmp_name'], $file['name'])) {
            $this->log('error', "File MIME type not allowed: {$file['type']} for extension .$fileExtension");
            throw new rex_api_exception('File type not allowed');
        }

        // Bildoptimierung
        $serverImageProcessing = ('|1|' === (string) rex_config::get('filepond_uploader', 'server_image_processing', ''));
        if ($serverImageProcessing && str_starts_with($file['type'], 'image/') && 'image/gif' !== $file['type']) {
            $this->imageProcessor->processImage($file['tmp_name']);
        }

        $originalName = $file['name'];
        $metadata = $file['metadata'] ?? [];
        $skipMeta = rex_session('filepond_no_meta', 'boolean', false);

        if ('1' === rex_request('skipMeta', 'string', '')) {
            $skipMeta = true;
        }

        if ($categoryId < 0) {
            $categoryId = (int) rex_config::get('filepond_uploader', 'category_id', 0);
        }

        $data = [
            'title' => $metadata['title'] ?? rex_string::normalize(pathinfo($originalName, PATHINFO_FILENAME)),
            'category_id' => $categoryId,
            'file' => [
                'name' => $originalName,
                'tmp_name' => $file['tmp_name'],
                'type' => $file['type'],
                'size' => $file['size'],
            ],
        ];

        try {
            if (!file_exists($file['tmp_name'])) {
                $this->log('error', "File not found before upload: {$file['tmp_name']}");
                return $file['name'];
            }

            $result = rex_media_service::addMedia($data, true);
            if ($result['ok']) {
                if (!$skipMeta && [] !== $metadata) {
                    $sql = rex_sql::factory();
                    $sql->setTable(rex::getTable('media'));
                    $sql->setWhere(['filename' => $result['filename']]);

                    $sql->setValue('title', $metadata['title'] ?? '');

                    $isDecorative = isset($metadata['decorative']) && true === $metadata['decorative'];

                    if (!$isDecorative) {
                        if (isset($metadata['med_alt']) && is_array($metadata['med_alt'])) {
                            $langData = $this->metadataHandler->convertToMetaInfoLangFormat($metadata['med_alt']);
                            $sql->setValue('med_alt', json_encode($langData));
                        } else {
                            $sql->setValue('med_alt', $metadata['alt'] ?? $metadata['med_alt'] ?? '');
                        }

                        if (isset($metadata['med_copyright']) && is_array($metadata['med_copyright'])) {
                            $langData = $this->metadataHandler->convertToMetaInfoLangFormat($metadata['med_copyright']);
                            $sql->setValue('med_copyright', json_encode($langData));
                        } else {
                            $sql->setValue('med_copyright', $metadata['copyright'] ?? $metadata['med_copyright'] ?? '');
                        }

                        $this->metadataHandler->processAdditionalMetaInfoFields($sql, $metadata);
                    } elseif ($isDecorative) {
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
            if (str_contains($file['tmp_name'], 'upload/filepond/') && file_exists($file['tmp_name'])) {
                rex_file::delete($file['tmp_name']);
            }
        }
    }

    public function handleChunkUpload(int $categoryId): void
    {
        $chunkIndex = rex_request('chunkIndex', 'int', 0);
        $totalChunks = rex_request('totalChunks', 'int', 1);
        $fileId = rex_request('fileId', 'string', '');
        $fieldName = rex_request('fieldName', 'string', 'filepond');

        $logger = rex_logger::factory();

        if ('' === $fileId) {
            throw new rex_api_exception('Missing fileId');
        }

        $metaFile = $this->metadataDir . '/' . $fileId . '.json';

        if (!file_exists($metaFile)) {
            $logger->log('warning', "FILEPOND: Metadata file not found for $fileId, creating fallback metadata");

            $fallbackMetadata = [
                'metadata' => [
                    'title' => pathinfo(rex_request('fileName', 'string', 'unknown'), PATHINFO_FILENAME),
                    'alt' => pathinfo(rex_request('fileName', 'string', 'unknown'), PATHINFO_FILENAME),
                    'copyright' => '',
                ],
                'fileName' => rex_request('fileName', 'string', 'unknown'),
                'fieldName' => $fieldName,
                'timestamp' => time(),
            ];

            rex_dir::create($this->metadataDir);
            rex_file::put($metaFile, (string) json_encode($fallbackMetadata));
            $metaData = $fallbackMetadata;
        } else {
            $metaContent = rex_file::get($metaFile);
            if (null === $metaContent) {
                throw new rex_api_exception('Could not read metadata file for chunk upload');
            }
            $decoded = json_decode($metaContent, true);
            $metaData = is_array($decoded) ? $decoded : [];
        }

        $fileName = $metaData['fileName'];
        $storedFieldName = $metaData['fieldName'] ?? 'filepond';

        if ($fieldName !== $storedFieldName) {
            $logger->log('warning', "FILEPOND: Field name mismatch for $fileId. Expected $storedFieldName, got $fieldName");
        }

        $this->log('info', "Processing chunk $chunkIndex of $totalChunks for $fileName (ID: $fileId)");
        $this->log('debug', "chunkIndex = $chunkIndex, totalChunks = $totalChunks, fileId = $fileId, fieldName = $fieldName");

        $file = rex_request::files($fieldName, 'array', []);
        if (!isset($file['tmp_name']) || '' === $file['tmp_name']) {
            rex_response::setStatus(rex_response::HTTP_BAD_REQUEST);
            throw new rex_api_exception("No file chunk uploaded for field $fieldName");
        }

        $this->log('debug', "\$_FILES[$fieldName] = " . print_r($file, true));

        $fileChunkDir = $this->chunksDir . '/' . $fileId;
        if (!file_exists($fileChunkDir)) {
            if (!rex_dir::create($fileChunkDir)) {
                throw new rex_api_exception("Failed to create chunk directory: $fileChunkDir");
            }
            $this->log('info', "Created chunk directory: $fileChunkDir");
        }

        // LOCK-MECHANISMUS
        $lockFile = $fileChunkDir . '/.lock';
        $lock = fopen($lockFile, 'w+');
        if (false === $lock) {
            throw new rex_api_exception("Could not create lock file: $lockFile");
        }

        if (!flock($lock, LOCK_EX)) {
            fclose($lock);
            throw new rex_api_exception("Could not acquire lock for chunk directory: $fileChunkDir");
        }

        try {
            $chunkPath = $fileChunkDir . '/' . $chunkIndex;
            $this->log('debug', "Saving chunk to: $chunkPath, size = " . $file['size']);
            if (!move_uploaded_file($file['tmp_name'], $chunkPath)) {
                $error = error_get_last();
                $this->log('error', 'move_uploaded_file failed: ' . print_r($error, true));
                throw new rex_api_exception("Failed to save chunk $chunkIndex");
            }
            $this->log('info', "Saved chunk $chunkIndex successfully");

            if ($chunkIndex === $totalChunks - 1) {
                $this->log('info', "Last chunk received for $fileName, merging chunks...");

                $tmpFile = rex_path::addonData('filepond_uploader', 'upload/') . $fileId;
                rex_file::delete($tmpFile);

                $out = fopen($tmpFile, 'w');
                if (false === $out) {
                    throw new rex_api_exception('Could not create output file');
                }

                clearstatcache();

                $files = scandir($fileChunkDir);
                $actualChunks = 0;
                $chunkFiles = [];
                foreach ($files as $f) {
                    if ('.' !== $f && '..' !== $f && '.lock' !== $f && is_file($fileChunkDir . '/' . $f)) {
                        ++$actualChunks;
                        $chunkFiles[] = $f;
                    }
                }

                $this->log('info', "Expected $totalChunks chunks, found $actualChunks for $fileName");

                sort($chunkFiles, SORT_NUMERIC);
                $this->log('debug', 'Chunk files (sorted): ' . implode(', ', $chunkFiles));

                if ($actualChunks < $totalChunks) {
                    $this->log('warning', "Expected $totalChunks chunks, but found only $actualChunks for $fileName");

                    fclose($out);
                    flock($lock, LOCK_UN);
                    fclose($lock);
                    rex_file::delete($lockFile);
                    $missingChunks = [];
                    for ($i = 0; $i < $totalChunks; ++$i) {
                        if (!in_array((string) $i, $chunkFiles, true)) {
                            $missingChunks[] = $i;
                        }
                    }

                    $this->fileManager->cleanupChunks($fileChunkDir);
                    throw new rex_api_exception('Missing chunks: ' . implode(', ', $missingChunks) .
                        ". Expected $totalChunks chunks but found only $actualChunks");
                }

                for ($i = 0; $i < $totalChunks; ++$i) {
                    $chunkPath = $fileChunkDir . '/' . $i;
                    if (!file_exists($chunkPath)) {
                        fclose($out);
                        flock($lock, LOCK_UN);
                        fclose($lock);
                        rex_file::delete($lockFile);
                        $this->fileManager->cleanupChunks($fileChunkDir);
                        throw new rex_api_exception("Chunk $i is missing despite previous validation");
                    }

                    $in = fopen($chunkPath, 'r');
                    if (false === $in) {
                        fclose($out);
                        flock($lock, LOCK_UN);
                        fclose($lock);
                        rex_file::delete($lockFile);
                        $this->fileManager->cleanupChunks($fileChunkDir);
                        throw new rex_api_exception("Could not open chunk $i for reading");
                    }

                    $bytesWritten = stream_copy_to_stream($in, $out);
                    fclose($in);
                    $this->log('debug', "Added chunk $i to result file, $bytesWritten bytes written");
                }

                fclose($out);
                $this->log('info', 'All chunks merged successfully');

                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $type = $finfo->file($tmpFile);
                $finalSize = filesize($tmpFile);

                $this->log('info', "Final file type: $type, size: $finalSize bytes");

                flock($lock, LOCK_UN);
                fclose($lock);
                rex_file::delete($lockFile);

                $this->sendResponse([
                    'status' => 'chunk-success',
                    'chunkIndex' => $chunkIndex,
                    'remaining' => 0,
                ]);
            }

            flock($lock, LOCK_UN);
            fclose($lock);
            rex_file::delete($lockFile);

            $this->sendResponse([
                'status' => 'chunk-success',
                'chunkIndex' => $chunkIndex,
                'remaining' => $totalChunks - $chunkIndex - 1,
            ]);
        } catch (Exception $e) {
            if (is_resource($lock)) {
                flock($lock, LOCK_UN);
                fclose($lock);
                rex_file::delete($lockFile);
            }
            $this->fileManager->cleanupChunks($fileChunkDir);
            $this->log('error', 'Chunk upload error: ' . $e->getMessage());
            $this->sendResponse(['error' => $e->getMessage()], rex_response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Behandelt die Finalisierung eines Chunk-Uploads.
     *
     * @return array<string, string>
     */
    public function handleFinalizeUpload(int $categoryId): array
    {
        $fileId = rex_request('fileId', 'string', '');
        $fieldName = rex_request('fieldName', 'string', 'filepond');
        $fileName = rex_request('fileName', 'string', '');
        $totalChunks = rex_request('totalChunks', 'int', 0);

        $this->log('info', "Finalizing chunk upload for file: $fileName, ID: $fileId, total chunks: $totalChunks");

        if ('' === $fileId) {
            throw new rex_api_exception('Missing fileId');
        }

        $metaFile = $this->metadataDir . '/' . $fileId . '.json';

        if (!file_exists($metaFile)) {
            $this->log('warning', "Metadata file not found for $fileId, creating fallback metadata");

            $fallbackMetadata = [
                'metadata' => [
                    'title' => pathinfo($fileName, PATHINFO_FILENAME),
                    'alt' => pathinfo($fileName, PATHINFO_FILENAME),
                    'copyright' => '',
                ],
                'fileName' => $fileName,
                'fieldName' => $fieldName,
                'timestamp' => time(),
            ];

            rex_dir::create($this->metadataDir);
            rex_file::put($metaFile, (string) json_encode($fallbackMetadata));
            $metaData = $fallbackMetadata;
        } else {
            $metaContentFinalize = rex_file::get($metaFile);
            if (null === $metaContentFinalize) {
                throw new rex_api_exception('Could not read metadata file');
            }
            $decodedFinalize = json_decode($metaContentFinalize, true);
            $metaData = is_array($decodedFinalize) ? $decodedFinalize : [];
        }

        $tmpFile = rex_path::addonData('filepond_uploader', 'upload/') . $fileId;
        $fileChunkDir = $this->chunksDir . '/' . $fileId;

        if (!file_exists($tmpFile)) {
            $this->log('info', 'Merged file does not exist yet, merging chunks now');

            $out = fopen($tmpFile, 'w');
            if (false === $out) {
                throw new rex_api_exception('Could not create output file');
            }

            clearstatcache();

            if (!file_exists($fileChunkDir)) {
                throw new rex_api_exception("Chunk directory not found: $fileChunkDir");
            }

            $files = scandir($fileChunkDir);
            $actualChunks = 0;
            $chunkFiles = [];
            foreach ($files as $f) {
                if ('.' !== $f && '..' !== $f && '.lock' !== $f && is_file($fileChunkDir . '/' . $f)) {
                    ++$actualChunks;
                    $chunkFiles[] = $f;
                }
            }

            $this->log('info', "Expected $totalChunks chunks, found $actualChunks");

            sort($chunkFiles, SORT_NUMERIC);
            $this->log('debug', 'Chunk files (sorted): ' . implode(', ', $chunkFiles));

            if ($actualChunks < $totalChunks) {
                fclose($out);
                throw new rex_api_exception("Expected $totalChunks chunks, but found only $actualChunks");
            }

            for ($i = 0; $i < $totalChunks; ++$i) {
                $chunkPath = $fileChunkDir . '/' . $i;
                if (!file_exists($chunkPath)) {
                    fclose($out);
                    throw new rex_api_exception("Chunk $i is missing");
                }

                $in = fopen($chunkPath, 'r');
                if (false === $in) {
                    fclose($out);
                    throw new rex_api_exception("Could not open chunk $i for reading");
                }

                stream_copy_to_stream($in, $out);
                fclose($in);
            }

            fclose($out);
            $this->log('info', 'All chunks merged successfully');
        } else {
            $this->log('info', "Using existing merged file: $tmpFile");
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $type = $finfo->file($tmpFile);
        if (false === $type) {
            $type = 'application/octet-stream';
        }
        $finalSize = filesize($tmpFile);
        if (false === $finalSize) {
            $finalSize = 0;
        }

        $this->log('info', "Final file type: $type, size: $finalSize bytes");

        $uploadedFile = [
            'name' => (string) ($metaData['fileName'] ?? $fileName),
            'type' => $type,
            'tmp_name' => $tmpFile,
            'size' => $finalSize,
            'metadata' => $metaData['metadata'] ?? [],
        ];

        if ('1' === rex_request('skipMeta', 'string', '')) {
            /** @phpstan-ignore disallowed.variable */
            $_REQUEST['skipMeta'] = '1';
        }

        $result = $this->processUploadedFile($uploadedFile, $categoryId);

        $this->fileManager->cleanupChunks($fileChunkDir);
        rex_file::delete($metaFile);

        return [
            'status' => 'success',
            'filename' => $result,
            'originalname' => $fileName,
        ];
    }

    /**
     * Zentrale Methode für das Senden von JSON-Antworten.
     *
     * @param mixed $data Die zu sendenden Daten
     * @return never
     */
    private function sendResponse(mixed $data, string $statusCode = rex_response::HTTP_OK): void
    {
        rex_response::cleanOutputBuffers();
        if (rex_response::HTTP_OK !== $statusCode) {
            rex_response::setStatus($statusCode);
        }
        rex_response::sendJson($data);
        exit;
    }

    /**
     * MIME-Extension-Map für fehlende Dateiendungen.
     *
     * @return array<string, string>
     */
    private function getMimeExtensionMap(): array
    {
        return [
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
            'video/mp4' => 'mp4',
        ];
    }
}
