<?php
class rex_api_filepond_uploader extends rex_api_function
{
    protected $published = true;
    private const UPLOAD_DIR = 'chunks';
    private const CHUNK_TIMEOUT = 3600; // 1 hour

    public function execute()
    {
        try {
            $logger = rex_logger::factory();
            $logger->log('info', 'FILEPOND: Starting execute()');

            // Authorization checks
            $user = rex_backend_login::createUser();
            $isBackendUser = $user ? true : false;

            $logger->log('info', 'FILEPOND: isBackendUser = ' . ($isBackendUser ? 'true' : 'false'));

            // Token validation
            $apiToken = rex_config::get('filepond_uploader', 'api_token');
            $requestToken = rex_request('api_token', 'string', null);
            $sessionToken = rex_session('filepond_token', 'string', '');

            $isValidToken = ($apiToken && $requestToken && hash_equals($apiToken, $requestToken)) ||
                ($apiToken && $sessionToken && hash_equals($apiToken, $sessionToken));

            $authorized = false;

            // YCom check
            $isYComUser = false;
            if (rex_plugin::get('ycom', 'auth')->isAvailable()) {
                if (rex_ycom_auth::getUser()) {
                    $authorized = true;
                    $isYComUser = true;
                }
            }

            if ($isBackendUser || $isValidToken) {
                $authorized = true;
            }

            if (!$authorized) {
                $errors = [];
                if (!$isYComUser) $errors[] = 'no YCom login';
                if (!$isBackendUser) $errors[] = 'no Backend login';
                if (!$isValidToken) $errors[] = 'invalid API token';
                throw new rex_api_exception('Unauthorized access - ' . implode(', ', $errors));
            }

            // Clean up old chunks periodically
            $this->cleanupOldChunks();

            // Handle request
            $func = rex_request('func', 'string', '');
            $categoryId = rex_request('category_id', 'int', 0);

            switch ($func) {
                case 'upload':
                    $result = $this->handleChunkedUpload($categoryId);
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

    protected function handleChunkedUpload($categoryId)
    {
        if (!isset($_FILES['filepond'])) {
            throw new rex_api_exception('No file uploaded');
        }

        $file = $_FILES['filepond'];
        $chunkIndex = rex_request('chunk_index', 'int', -1);
        $chunkCount = rex_request('chunk_count', 'int', 1);
        $totalSize = rex_request('total_size', 'int', 0);
        $filename = rex_request('filename', 'string');

        // Validate file size
        $maxSize = rex_config::get('filepond_uploader', 'max_filesize', 10) * 1024 * 1024;
        if ($totalSize > $maxSize) {
            throw new rex_api_exception('File too large');
        }

        // Validate file type
        $this->validateFileType($file['type'], $filename);

        // Setup chunks directory
        $chunksDir = rex_path::addonCache('filepond_uploader', self::UPLOAD_DIR);
        if (!file_exists($chunksDir)) {
            mkdir($chunksDir, 0775, true);
        }

        // Generate unique upload ID and create chunk directory
        $uploadId = md5($filename . uniqid());
        $chunkDir = $chunksDir . '/' . $uploadId;

        // Handle first chunk
        if ($chunkIndex === 0) {
            if (file_exists($chunkDir)) {
                rex_dir::delete($chunkDir);
            }
            mkdir($chunkDir, 0775, true);

            // Store metadata and file info
            if ($metadata = rex_request('metadata', 'string', '')) {
                file_put_contents($chunkDir . '/metadata.json', $metadata);
            }
            file_put_contents($chunkDir . '/info.json', json_encode([
                'filename' => $filename,
                'type' => $file['type'],
                'size' => $totalSize,
                'chunks' => $chunkCount,
                'timestamp' => time()
            ]));
        }

        // Validate chunk
        if ($chunkIndex < 0 || $chunkIndex >= $chunkCount) {
            throw new rex_api_exception('Invalid chunk index');
        }

        // Store chunk
        $chunkPath = $chunkDir . '/chunk_' . $chunkIndex;
        if (!move_uploaded_file($file['tmp_name'], $chunkPath)) {
            throw new rex_api_exception('Failed to store chunk');
        }

        // Check if upload is complete
        if ($this->areAllChunksUploaded($chunkDir, $chunkCount)) {
            try {
                // Combine chunks
                $finalPath = $this->combineChunks($chunkDir, $chunkCount, $filename);
                
                // Verify combined file size
                if (filesize($finalPath) !== $totalSize) {
                    throw new rex_api_exception('File size mismatch after combining chunks');
                }

                // Load metadata
                $metadata = [];
                if (file_exists($chunkDir . '/metadata.json')) {
                    $metadata = json_decode(file_get_contents($chunkDir . '/metadata.json'), true);
                }

                // Process image if needed
                if (strpos($file['type'], 'image/') === 0 && $file['type'] !== 'image/gif') {
                    $this->processImage($finalPath);
                }

                // Add to media pool
                $result = $this->addToMediaPool($finalPath, $filename, $metadata, $categoryId);

                // Cleanup
                rex_dir::delete($chunkDir);
                
                return $result;
            } catch (Exception $e) {
                rex_dir::delete($chunkDir);
                throw $e;
            }
        }

        return ['status' => 'chunk_uploaded'];
    }

    private function validateFileType($mimeType, $filename)
    {
        $allowedTypes = rex_config::get('filepond_uploader', 'allowed_types', 'image/*,video/*,.pdf,.doc,.docx,.txt');
        $allowedTypes = array_map('trim', explode(',', $allowedTypes));
        $isAllowed = false;

        foreach ($allowedTypes as $type) {
            if (strpos($type, '*') !== false) {
                $baseType = str_replace('*', '', $type);
                if (strpos($mimeType, $baseType) === 0) {
                    $isAllowed = true;
                    break;
                }
            } elseif (strpos($type, '.') === 0) {
                if (strtolower(substr($filename, -strlen($type))) === strtolower($type)) {
                    $isAllowed = true;
                    break;
                }
            } else {
                if ($mimeType === $type) {
                    $isAllowed = true;
                    break;
                }
            }
        }

        if (!$isAllowed) {
            throw new rex_api_exception('File type not allowed');
        }
    }

    private function areAllChunksUploaded($chunkDir, $chunkCount)
    {
        $uploadedChunks = glob($chunkDir . '/chunk_*');
        return count($uploadedChunks) === $chunkCount;
    }

    private function combineChunks($chunkDir, $chunkCount, $filename)
    {
        $tempPath = rex_path::addonCache('filepond_uploader', 'temp_' . $filename);
        $out = fopen($tempPath, 'wb');

        try {
            for ($i = 0; $i < $chunkCount; $i++) {
                $chunkPath = $chunkDir . '/chunk_' . $i;
                if (!file_exists($chunkPath)) {
                    throw new rex_api_exception("Missing chunk: $i");
                }

                $in = fopen($chunkPath, 'rb');
                if (!$in) {
                    throw new rex_api_exception("Cannot open chunk: $i");
                }

                stream_copy_to_stream($in, $out);
                fclose($in);
            }
        } catch (Exception $e) {
            fclose($out);
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
            throw $e;
        }

        fclose($out);
        return $tempPath;
    }

    private function processImage($filepath)
    {
        $maxPixel = rex_config::get('filepond_uploader', 'max_pixel', 1200);
        
        $imageInfo = getimagesize($filepath);
        if (!$imageInfo) {
            return;
        }

        list($width, $height, $type) = $imageInfo;

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

        // Create new image
        $srcImage = null;
        switch ($type) {
            case IMAGETYPE_JPEG:
                $srcImage = imagecreatefromjpeg($filepath);
                break;
            case IMAGETYPE_PNG:
                $srcImage = imagecreatefrompng($filepath);
                break;
            default:
                return;
        }

        if (!$srcImage) {
            return;
        }

        $dstImage = imagecreatetruecolor($newWidth, $newHeight);

        // Handle transparency for PNG
        if ($type === IMAGETYPE_PNG) {
            imagealphablending($dstImage, false);
            imagesavealpha($dstImage, true);
            $transparent = imagecolorallocatealpha($dstImage, 255, 255, 255, 127);
            imagefilledrectangle($dstImage, 0, 0, $newWidth, $newHeight, $transparent);
        }

        // Resize
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

        // Save
        if ($type === IMAGETYPE_JPEG) {
            imagejpeg($dstImage, $filepath, 90);
        } elseif ($type === IMAGETYPE_PNG) {
            imagepng($dstImage, $filepath, 9);
        }

        // Cleanup
        imagedestroy($srcImage);
        imagedestroy($dstImage);
    }

    private function addToMediaPool($filepath, $filename, $metadata, $categoryId)
    {
        $data = [
            'title' => $metadata['title'] ?? pathinfo($filename, PATHINFO_FILENAME),
            'category_id' => $categoryId,
            'file' => [
                'name' => $filename,
                'tmp_name' => $filepath,
                'type' => mime_content_type($filepath),
                'size' => filesize($filepath)
            ]
        ];

        try {
            $result = rex_media_service::addMedia($data, true);
            
            if ($result['ok']) {
                // Update metadata
                $sql = rex_sql::factory();
                $sql->setTable(rex::getTable('media'));
                $sql->setWhere(['filename' => $result['filename']]);
                $sql->setValue('title', $metadata['title'] ?? '');
                $sql->setValue('med_alt', $metadata['alt'] ?? '');
                $sql->setValue('med_copyright', $metadata['copyright'] ?? '');
                $sql->update();

                unlink($filepath);
                return $result['filename'];
            }

            throw new rex_api_exception(implode(', ', $result['messages']));
        } catch (Exception $e) {
            if (file_exists($filepath)) {
                unlink($filepath);
            }
            throw new rex_api_exception('Upload failed: ' . $e->getMessage());
        }
    }

    private function cleanupOldChunks()
    {
        $chunksDir = rex_path::addonCache('filepond_uploader', self::UPLOAD_DIR);
        if (!file_exists($chunksDir)) {
            return;
        }

        $now = time();
        foreach (glob($chunksDir . '/*', GLOB_ONLYDIR) as $dir) {
            $infoFile = $dir . '/info.json';
            if (file_exists($infoFile)) {
                $info = json_decode(file_get_contents($infoFile), true);
                if ($info && isset($info['timestamp'])) {
                    if ($now - $info['timestamp'] > self::CHUNK_TIMEOUT) {
                        rex_dir::delete($dir);
                    }
                }
            } else {
                // Delete directories without info file
                rex_dir::delete($dir);
            }
        }
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
                    // File is still in use, but we report success to FilePond
                    rex_response::sendJson(['status' => 'success']);
                    exit;
                }
            } else {
                // File doesn't exist anymore, report success
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

    /**
     * Validates security token
     * @return bool
     */
    private function validateToken(): bool
    {
        $apiToken = rex_config::get('filepond_uploader', 'api_token');
        $requestToken = rex_request('api_token', 'string', null);
        $sessionToken = rex_session('filepond_token', 'string', '');

        return ($apiToken && $requestToken && hash_equals($apiToken, $requestToken)) ||
               ($apiToken && $sessionToken && hash_equals($apiToken, $sessionToken));
    }

    /**
     * Validates file type against allowed types configuration
     * @param string $mimeType
     * @param string $filename
     * @return bool
     */
    private function isAllowedFileType(string $mimeType, string $filename): bool
    {
        $allowedTypes = rex_config::get('filepond_uploader', 'allowed_types', 'image/*,video/*,.pdf,.doc,.docx,.txt');
        $allowedTypes = array_map('trim', explode(',', $allowedTypes));

        foreach ($allowedTypes as $type) {
            if (strpos($type, '*') !== false) {
                $baseType = str_replace('*', '', $type);
                if (strpos($mimeType, $baseType) === 0) {
                    return true;
                }
            } elseif (strpos($type, '.') === 0) {
                if (strtolower(substr($filename, -strlen($type))) === strtolower($type)) {
                    return true;
                }
            } else {
                if ($mimeType === $type) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Verifies file integrity after chunk assembly
     * @param string $filepath
     * @param int $expectedSize
     * @return bool
     */
    private function verifyFileIntegrity(string $filepath, int $expectedSize): bool
    {
        if (!file_exists($filepath)) {
            return false;
        }

        $actualSize = filesize($filepath);
        return $actualSize === $expectedSize;
    }
}
