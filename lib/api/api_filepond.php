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
                'size' => $file['size']
            ]
        ];

        try {
            // Resize the image before adding it to the media pool
            $result = $this->resizeImageBeforeUpload($data);

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

    protected function resizeImageBeforeUpload($data)
    {
        $file = $data['file'];

        if ($file['type'] !== 'application/pdf' && $file['type'] !== 'application/msword' && $file['type'] !== 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' && $file['type'] !== 'text/plain') {
            try {
                if (class_exists(Imagick::class)) {
                    $image = new Imagick();
                    $image->readImage($file['tmp_name']);

                    $width = $image->getImageWidth();
                    $height = $image->getImageHeight();

                    if ($width > 320 || $height > 320) {
                        if ($width > $height) {
                            $image->thumbnailImage(320, 0);
                        } else {
                            $image->thumbnailImage(0, 320);
                        }
                        $image->writeImage($file['tmp_name']);
                    }
                } else {
                    // Use command-line tools if Imagick is not available
                    $convertPath = $this->getConvertPath();
                    if ($convertPath !== '') {
                        $filename = $file['name'];
                        $filenameWoExt = substr($filename, 0, strlen($filename) - strrpos($filename, '.'));
                        $toPath = rex_path::addonCache('media_manager', 'media_manager__resized_' . md5($file['tmp_name']) . '_' . $filenameWoExt . '.jpg');

                        $cmd = $convertPath . ' -resize 320x320 ' . escapeshellarg($file['tmp_name']) . ' ' . escapeshellarg($toPath);
                        exec($cmd, $out, $ret);

                        if ($ret !== 0) {
                            throw new rex_exception('Unable to exec command ' . $cmd);
                        }

                        $file['tmp_name'] = $toPath;
                    } else {
                        // Log a message if ImageMagick is not available
                        rex_logger::factory()->info('ImageMagick not found, unable to resize images.');
                    }
                }
            } catch (Exception $e) {
                throw new rex_api_exception('Error resizing image: ' . $e->getMessage());
            }
        }

        return rex_media_service::addMedia($data, true);
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

    private function getConvertPath()
    {
        $path = '';

        if (function_exists('exec')) {
            $out = [];
            $cmd = 'command -v convert || which convert';
            exec($cmd, $out, $ret);

            if ($ret === 0) {
                $path = (string) $out[0];
            }
        }
        return $path;
    }
}
