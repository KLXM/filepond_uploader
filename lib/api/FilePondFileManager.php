<?php

/**
 * Dateiverwaltung für FilePond Uploads.
 *
 * Enthält Delete, Load, Restore, Cleanup und Chunk-Bereinigung.
 */
class filepond_file_manager
{
    private bool $debug = false;
    private string $chunksDir;
    private string $metadataDir;

    public function __construct(string $chunksDir, string $metadataDir, bool $debug = false)
    {
        $this->chunksDir = $chunksDir;
        $this->metadataDir = $metadataDir;
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
     * @return never
     */
    public function handleDelete(): void
    {
        $filename = trim(rex_request('filename', 'string', ''));

        if ('' === $filename) {
            throw new rex_api_exception('Missing filename');
        }

        try {
            $media = rex_media::get($filename);
            if (null !== $media) {
                $inUse = false;

                $sql = rex_sql::factory();
                $yformTables = rex_yform_manager_table::getAll();

                foreach ($yformTables as $table) {
                    foreach ($table->getFields() as $field) {
                        if ('value' === $field->getType() && 'filepond' === $field->getTypeName()) {
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
                    rex_media_service::deleteMedia($filename);
                }
            }

            $this->sendResponse(['status' => 'success']);
        } catch (rex_api_exception $e) {
            throw new rex_api_exception('Error deleting file: ' . $e->getMessage());
        }
    }

    /**
     * @return never
     */
    public function handleLoad(): void
    {
        $filename = rex_request('filename', 'string');
        if ('' === $filename) {
            throw new rex_api_exception('Missing filename');
        }

        $media = rex_media::get($filename);
        if (null !== $media) {
            $file = rex_path::media($filename);
            if (file_exists($file)) {
                rex_response::cleanOutputBuffers();
                rex_response::sendFile(
                    $file,
                    $media->getType(),
                    'inline',
                    $media->getFileName(),
                );
                exit;
            }
        }

        throw new rex_api_exception('File not found');
    }

    /**
     * @return never
     */
    public function handleRestore(): void
    {
        $filename = rex_request('filename', 'string');
        if ('' === $filename) {
            throw new rex_api_exception('Missing filename');
        }

        if (null !== rex_media::get($filename)) {
            $this->sendResponse(['status' => 'success']);
        } else {
            throw new rex_api_exception('File not found in media pool');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function handleCleanup(): array
    {
        $user = rex_backend_login::createUser();
        if (null === $user || !$user->isAdmin()) {
            throw new rex_api_exception('Unauthorized: Admin privileges required');
        }

        $this->log('info', 'Admin-triggered cleanup of temporary files');

        $cleanedChunks = 0;
        $cleanedMetadata = 0;
        $errors = [];
        $debugInfo = [];

        // Alte Chunk-Verzeichnisse löschen (älter als 1h)
        $expireTime = time() - (60 * 60);

        $debugInfo['chunks_dir'] = $this->chunksDir;
        $debugInfo['metadata_dir'] = $this->metadataDir;
        $debugInfo['expire_time'] = date('Y-m-d H:i:s', $expireTime);
        $debugInfo['current_time'] = date('Y-m-d H:i:s');

        if (is_dir($this->chunksDir)) {
            $globResult = glob($this->chunksDir . '/*', GLOB_ONLYDIR);
            $chunkDirs = false !== $globResult ? $globResult : [];
            $debugInfo['found_chunk_dirs'] = count($chunkDirs);

            foreach ($chunkDirs as $dir) {
                $dirTime = filemtime($dir);
                if (false === $dirTime) {
                    continue;
                }
                $dirAge = time() - $dirTime;
                $debugInfo['chunk_dirs'][] = [
                    'path' => $dir,
                    'modified' => date('Y-m-d H:i:s', $dirTime),
                    'age_seconds' => $dirAge,
                    'is_expired' => ($dirTime < $expireTime),
                ];

                if ($dirTime < $expireTime) {
                    try {
                        $this->log('info', "Cleaning up chunk directory: $dir (modified: " . date('Y-m-d H:i:s', $dirTime) . ')');
                        $this->cleanupChunks($dir);
                        ++$cleanedChunks;
                    } catch (Exception $e) {
                        $errors[] = "Failed to clean chunk directory $dir: " . $e->getMessage();
                        $this->log('error', "Failed to clean chunk directory $dir: " . $e->getMessage());
                    }
                }
            }
        } else {
            $errors[] = "Chunks directory does not exist: {$this->chunksDir}";
            $this->log('error', "Chunks directory does not exist: {$this->chunksDir}");

            try {
                rex_dir::create($this->chunksDir);
                $this->log('info', "Created chunks directory: {$this->chunksDir}");
            } catch (Exception $e) {
                $errors[] = 'Failed to create chunks directory: ' . $e->getMessage();
                $this->log('error', 'Failed to create chunks directory: ' . $e->getMessage());
            }
        }

        // Alte Metadaten-Dateien löschen (älter als 1h)
        if (is_dir($this->metadataDir)) {
            $globResult = glob($this->metadataDir . '/*.json');
            $metaFiles = false !== $globResult ? $globResult : [];
            $debugInfo['found_meta_files'] = count($metaFiles);

            foreach ($metaFiles as $file) {
                $fileTime = filemtime($file);
                if (false === $fileTime) {
                    continue;
                }
                $fileAge = time() - $fileTime;
                $debugInfo['meta_files'][] = [
                    'path' => $file,
                    'modified' => date('Y-m-d H:i:s', $fileTime),
                    'age_seconds' => $fileAge,
                    'is_expired' => ($fileTime < $expireTime),
                ];

                if ($fileTime < $expireTime) {
                    try {
                        $this->log('info', "Deleting metadata file: $file (modified: " . date('Y-m-d H:i:s', $fileTime) . ')');
                        if (!rex_file::delete($file)) {
                            $errors[] = "Failed to delete metadata file: $file";
                            $this->log('error', "Failed to delete metadata file: $file");
                        } else {
                            ++$cleanedMetadata;
                        }
                    } catch (Exception $e) {
                        $errors[] = "Failed to delete metadata file $file: " . $e->getMessage();
                        $this->log('error', "Failed to delete metadata file $file: " . $e->getMessage());
                    }
                }
            }
        } else {
            $errors[] = "Metadata directory does not exist: {$this->metadataDir}";
            $this->log('error', "Metadata directory does not exist: {$this->metadataDir}");

            try {
                rex_dir::create($this->metadataDir);
                $this->log('info', "Created metadata directory: {$this->metadataDir}");
            } catch (Exception $e) {
                $errors[] = 'Failed to create metadata directory: ' . $e->getMessage();
                $this->log('error', 'Failed to create metadata directory: ' . $e->getMessage());
            }
        }

        $response = [
            'status' => [] === $errors ? 'success' : 'partial_success',
            'message' => "Cleanup completed. Removed $cleanedChunks chunk folders and $cleanedMetadata metadata files.",
        ];

        if ([] !== $errors) {
            $response['errors'] = $errors;
            $response['message'] .= ' Encountered ' . count($errors) . ' errors.';
        }

        $currentUser = rex::getUser();
        if (rex::isBackend() && null !== $currentUser && $currentUser->isAdmin()) {
            $response['debug'] = $debugInfo;
        }

        return $response;
    }

    /**
     * Bereinigt ein Chunk-Verzeichnis.
     */
    public function cleanupChunks(string $directory): void
    {
        if (is_dir($directory)) {
            $globResult = glob($directory . '/*');
            $files = false !== $globResult ? $globResult : [];
            foreach ($files as $file) {
                if (is_file($file)) {
                    rex_file::delete($file);
                }
            }
            rex_dir::delete($directory);
        }
    }

    /**
     * Löscht eine Datei aus dem Medienpool nach Abbruch des Metadaten-Dialogs.
     *
     * @return array<string, string>
     */
    public function handleCancelUpload(): array
    {
        $filename = trim(rex_request('filename', 'string', ''));

        if ('' === $filename) {
            throw new rex_api_exception('Missing filename');
        }

        $this->log('info', "Removing file after metadata dialog was cancelled: $filename");

        try {
            $media = rex_media::get($filename);
            if (null !== $media) {
                rex_media_service::deleteMedia($filename);
                $this->log('info', "Successfully removed file from media pool: $filename");
                return [
                    'status' => 'success',
                    'message' => "File $filename removed successfully",
                ];
            }
            $this->log('warning', "File not found in media pool: $filename");
            return [
                'status' => 'success',
                'message' => "File $filename not found in media pool",
            ];
        } catch (Exception $e) {
            $this->log('error', 'Error removing file: ' . $e->getMessage());
            throw new rex_api_exception('Error removing file: ' . $e->getMessage());
        }
    }

    /**
     * Zentrale Methode für das Senden von JSON-Antworten.
     *
     * @param mixed $data Die zu sendenden Daten
     * @return never
     */
    private function sendResponse(mixed $data): void
    {
        rex_response::cleanOutputBuffers();
        rex_response::sendJson($data);
        exit;
    }
}
