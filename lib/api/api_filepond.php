<?php
class rex_api_filepond_uploader extends rex_api_function
{
    protected $published = true;

    public function execute()
    {
        try {
            if (rex::isBackend()) {
                if (!rex::getUser()) {
                    throw new rex_api_exception('Backend user must be logged in');
                }
            } else {
                $isYComUser = false;
                if (rex_plugin::get('ycom', 'auth')->isAvailable()) {
                    $ycomUser = rex_ycom_auth::getUser();
                    $isYComUser = $ycomUser && $ycomUser->getValue('status') == 1;
                }
                
                $apiToken = rex_config::get('filepond_uploader', 'api_token');
                $requestToken = rex_request('api_token', 'string', null);
                $isValidToken = $requestToken && hash_equals($apiToken, $requestToken);

                if (!$isValidToken && !$isYComUser) {
                    throw new rex_api_exception('Unauthorized access - requires valid API token or YCom login');
                }
            }

            $func = rex_request('func', 'string', '');
            $categoryId = rex_request('category_id', 'int', 0);  
        
            switch ($func) {
                case 'upload':
                    $result = $this->handleUpload($categoryId);
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
        // error_log('FILEPOND: Uploaded file type: ' . $file['type'] . ', name: ' . $file['name']);
        
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
            // error_log('FILEPOND: Starting image processing for: ' . $file['type'] . ' - ' . $file['name']);
            $this->processImage($file['tmp_name']);
        } else {
            // error_log('FILEPOND: Skipping image processing - file type: ' . $file['type']);
        }
        
        $originalName = $file['name'];
        $filename = rex_string::normalize(pathinfo($originalName, PATHINFO_FILENAME));
        
        $metadata = json_decode(rex_post('metadata', 'string', '{}'), true);
        
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
                'size' => filesize($file['tmp_name']) // Update filesize after potential resize
            ]
        ];

        try {
            $result = rex_media_service::addMedia($data, true);
            if ($result['ok']) {
                $sql = rex_sql::factory();
                $sql->setTable(rex::getTable('media'));
                $sql->setWhere(['filename' => $result['filename']]);
                $sql->setValue('title', $metadata['title'] ?? '');
                $sql->setValue('med_alt', $metadata['alt'] ?? '');
                $sql->setValue('med_copyright', $metadata['copyright'] ?? '');
                $sql->update();

                return $result['filename'];
            }
            
            throw new rex_api_exception(implode(', ', $result['messages']));

        } catch (Exception $e) {
            throw new rex_api_exception('Upload failed: ' . $e->getMessage());
        }
    }

    protected function processImage($tmpFile)
    {
        // error_log('FILEPOND: Processing image: ' . $tmpFile);
        
        $imageInfo = getimagesize($tmpFile);
        if (!$imageInfo) {
            // error_log('FILEPOND: Could not get image size for file: ' . $tmpFile);
            return;
        }

        list($width, $height, $type) = $imageInfo;
        // error_log("FILEPOND: Image dimensions: {$width}x{$height}, type: {$type}");
        
        // Return if image is smaller than max dimensions
        if ($width <= 1200 && $height <= 1200) {
            // error_log('FILEPOND: Image is already small enough, skipping resize');
            return;
        }
        // error_log('FILEPOND: Image needs resizing');
        
        // Calculate new dimensions
        $ratio = $width / $height;
        if ($width > $height) {
            $newWidth = min($width, 4000);
            $newHeight = floor($newWidth / $ratio);
        } else {
            $newHeight = min($height, 4000);
            $newWidth = floor($newHeight * $ratio);
        }
        
        // Create new image based on type
        $srcImage = null;
        switch ($type) {
            case IMAGETYPE_JPEG:
                // error_log('FILEPOND: Processing as JPEG');
                $srcImage = imagecreatefromjpeg($tmpFile);
                break;
            case IMAGETYPE_PNG:
                // error_log('FILEPOND: Processing as PNG');
                $srcImage = imagecreatefrompng($tmpFile);
                break;
            default:
                // error_log('FILEPOND: Unsupported image type: ' . $type);
                return;
        }

        if (!$srcImage) {
            // error_log('FILEPOND: Could not create image resource');
            return;
        }

        $dstImage = imagecreatetruecolor($newWidth, $newHeight);
        // error_log("FILEPOND: Creating new image with dimensions: {$newWidth}x{$newHeight}");
        
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
            0, 0, 0, 0,
            $newWidth,
            $newHeight,
            $width,
            $height
        );
        
        // Save image
        $success = false;
        if ($type === IMAGETYPE_JPEG) {
            $success = imagejpeg($dstImage, $tmpFile, 90);
        } elseif ($type === IMAGETYPE_PNG) {
            $success = imagepng($dstImage, $tmpFile, 9);
        }
        
        /*if ($success) {
            error_log('FILEPOND: Successfully saved resized image');
        } else {
            error_log('FILEPOND: Failed to save resized image');
        }*/
        
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
}
