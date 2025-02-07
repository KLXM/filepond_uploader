<?php
class rex_api_filepond_uploader extends rex_api_function
{
    protected $published = true;
    private $chunkDir;

    public function __construct()
    {
        parent::__construct();
        // Initialize chunk directory
        $this->chunkDir = rex_path::addonCache('filepond_uploader', 'chunks');
        if (!file_exists($this->chunkDir)) {
            rex_dir::create($this->chunkDir);
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

                case 'chunk':
                    $result = $this->handleChunk();
                    rex_response::cleanOutputBuffers();
                    rex_response::sendJson($result);
                    exit;

                case 'complete':
                    $result = $this->handleCompleteUpload($categoryId);
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

    protected function handleUpload($categoryId)
    {
        if (!isset($_FILES['filepond'])) {
            rex_response::setStatus(rex_response::HTTP_BAD_REQUEST);
            throw new rex_api_exception('No file uploaded');
        }

        $file = $_FILES['filepond'];

        $maxSize = rex_config::get('filepond_uploader', 'max_filesize', 10) * 1024 * 1024;
        if ($file['size'] > $maxSize) {
            throw new rex_api_exception('File too large');
        }

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

        // Process image if it's not a GIF
        if (strpos($file['type'], 'image/') === 0 && $file['type'] !== 'image/gif') {
            $this->processImage($file['tmp_name']);
        }

        $originalName = $file['name'];
        $filename = rex_string::normalize(pathinfo($originalName, PATHINFO_FILENAME));

        // Skip meta check
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
                    $sql->update();
                }

                return $result['filename'];
            }

            throw new rex_api_exception(implode(', ', $result['messages']));
        } catch (Exception $e) {
            throw new rex_api_exception('Upload failed: ' . $e->getMessage());
        }
    }

    protected function handleChunk()
    {
        if (!isset($_FILES['chunk'])) {
            throw new rex_api_exception('No chunk uploaded');
        }

        $chunkFile = $_FILES['chunk'];
        $chunkNumber = rex_request('chunkNumber', 'int');
        $totalChunks = rex_request('totalChunks', 'int');
        $fileId = rex_request('fileId', 'string');
        
        // Validate chunk data
        if ($chunkNumber >= $totalChunks) {
            throw new rex_api_exception('Invalid chunk number');
        }

        // Create directory for this file's chunks
        $fileChunkDir = $this->chunkDir . '/' . $fileId;
        if (!file_exists($fileChunkDir)) {
            rex_dir::create($fileChunkDir);
        }

        // Move chunk to storage
        $chunkPath = $fileChunkDir . '/' . $chunkNumber;
        if (!move_uploaded_file($chunkFile['tmp_name'], $chunkPath)) {
            throw new rex_api_exception('Failed to store chunk');
        }

        return ['success' => true];
    }

    protected function handleCompleteUpload($categoryId)
    {
        $fileId = rex_request('fileId', 'string');
        $originalName = rex_request('originalName', 'string');
        
        $fileChunkDir = $this->chunkDir . '/' . $fileId;
        if (!file_exists($fileChunkDir)) {
            throw new rex_api_exception('No chunks found for file');
        }

        // Get all chunks and sort them
        $chunks = glob($fileChunkDir . '/*');
        sort($chunks, SORT_NUMERIC);

        // Create temporary file for combined chunks
        $tmpFile = rex_path::addonCache('filepond_uploader', 'tmp_' . $fileId);
        $out = fopen($tmpFile, 'wb');

        foreach ($chunks as $chunk) {
            $in = fopen($chunk, 'rb');
            stream_copy_to_stream($in, $out);
            fclose($in);
        }
        fclose($out);

        // Process metadata
        $skipMeta = rex_session('filepond_no_meta', 'boolean', false);
        $metadata = [];
        if (!$skipMeta) {
            $metadata = json_decode(rex_post('metadata', 'string', '{}'), true);
        }

        // Process image if it's a large image
        $mimeType = mime_content_type($tmpFile);
        if (strpos($mimeType, 'image/') === 0 && $mimeType !== 'image/gif') {
            $this->processImage($tmpFile);
        }

        if (!isset($categoryId) || $categoryId < 0) {
            $categoryId = rex_config::get('filepond_uploader', 'category_id', 0);
        }

        // Add to media pool
        $data = [
            'title' => $metadata['title'] ?? pathinfo($originalName, PATHINFO_FILENAME),
            'category_id' => $categoryId,
            'file' => [
                'name' => $originalName,
                'tmp_name' => $tmpFile,
                'type' => $mimeType,
                'size' => filesize($tmpFile)
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
                    $sql->update();
                }

                // Cleanup
                rex_dir::delete($fileChunkDir);
                unlink($tmpFile);

                return $result['filename'];
            }

            throw new rex_api_exception(implode(', ', $result['messages']));
        } catch (Exception $e) {
            // Cleanup on error
            rex_dir::delete($fileChunkDir);
            if (file_exists($tmpFile)) {
                unlink($tmpFile);
            }
            throw new rex_api_exception('Upload failed: ' . $e->getMessage());
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

    protected function processImage($tmpFile)
    {
        $maxPixel = rex_config::get('filepond_uploader', 'max_pixel', 1200);

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
            imagejpeg($dstImage, $tmpFile, 90);
        } elseif ($type === IMAGETYPE_PNG) {
            imagepng($dstImage, $tmpFile, 9);
        }

        // Free memory
        imagedestroy($srcImage);
        imagedestroy($dstImage);
    }
}
