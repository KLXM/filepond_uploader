<?php
// rex_api_filepond_uploader.php

class rex_api_filepond_uploader extends rex_api_function
{
    // API ist nicht öffentlich zugänglich
    protected $published = false;

    /**
     * Check API permission
     * @return bool
     */
    public function hasPermission()
    {
        // Get API token from config
        $apiToken = rex_config::get('filepond_uploader', 'api_token');
        
        // Check if request has valid API token
        $requestToken = rex_request('api_token', 'string', null);
        $isValidToken = $requestToken && hash_equals($apiToken, $requestToken);

        // Check if user is authenticated backend user
        $isBackendUser = rex::isBackend() && rex::getUser();
        
        // Check if user is authenticated YCom user
        $isYComUser = false;
        if (rex_plugin::get('ycom', 'auth')->isAvailable()) {
            $ycomUser = rex_ycom_auth::getUser();
            $isYComUser = $ycomUser && $ycomUser->getValue('status') == 1;
        }

        // Log access type for debugging
        if ($isValidToken) {
            rex_logger::factory()->log('debug', 'FilePond API: Access via token');
        } elseif ($isBackendUser) {
            rex_logger::factory()->log('debug', 'FilePond API: Access via backend user: ' . rex::getUser()->getLogin());
        } elseif ($isYComUser) {
            rex_logger::factory()->log('debug', 'FilePond API: Access via YCom user: ' . $ycomUser->getValue('login'));
        } else {
            rex_logger::factory()->log('warning', 'FilePond API: Unauthorized access attempt');
        }

        // Return true if any authentication method is valid
        return $isValidToken || $isBackendUser || $isYComUser;
    }

    public function execute()
    {
        try {
            $func = rex_request('func', 'string', '');
            $categoryId = rex_request('category_id', 'int', 0);  
            rex_logger::factory()->log('debug', 'FilePond API called with category_id: ' . $categoryId . ' and func: ' . $func);
        
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
            rex_logger::factory()->log('error', 'API Error: ' . $e->getMessage());
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
        
        // Validate file size
        $maxSize = rex_config::get('filepond_uploader', 'max_filesize', 10) * 1024 * 1024; // Convert MB to bytes
        if ($file['size'] > $maxSize) {
            throw new rex_api_exception('File too large');
        }

        // Validate file type
        $allowedTypes = rex_config::get('filepond_uploader', 'allowed_types', 'image/*,video/*,.pdf,.doc,.docx,.txt');
        $allowedTypes = array_map('trim', explode(',', $allowedTypes));
        $isAllowed = false;
        
        foreach ($allowedTypes as $type) {
            if (strpos($type, '*') !== false) {
                // Handle wildcard mime types (e.g., image/*)
                $baseType = str_replace('*', '', $type);
                if (strpos($file['type'], $baseType) === 0) {
                    $isAllowed = true;
                    break;
                }
            } elseif (strpos($type, '.') === 0) {
                // Handle file extensions (e.g., .pdf)
                if (strtolower(substr($file['name'], -strlen($type))) === strtolower($type)) {
                    $isAllowed = true;
                    break;
                }
            } else {
                // Handle exact mime types
                if ($file['type'] === $type) {
                    $isAllowed = true;
                    break;
                }
            }
        }

        if (!$isAllowed) {
            throw new rex_api_exception('File type not allowed');
        }
        
        // Generate unique filename
        $originalName = $file['name'];
        $filename = rex_string::normalize(pathinfo($originalName, PATHINFO_FILENAME));
        
        // Get metadata
        $metadata = json_decode(rex_post('metadata', 'string', '{}'), true);
        
        // Use provided category if valid, otherwise fall back to config default
        if (!isset($categoryId) || $categoryId < 0) {
            $categoryId = rex_config::get('filepond_uploader', 'category_id', 0);
        }

        // Add to media pool
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

                rex_logger::factory()->log('info', 'File uploaded successfully: ' . $result['filename']);
                return $result['filename'];
            }
            
            throw new rex_api_exception(implode(', ', $result['messages']));

        } catch (Exception $e) {
            rex_logger::factory()->log('error', 'Upload failed: ' . $e->getMessage());
            throw new rex_api_exception('Upload failed: ' . $e->getMessage());
        }
    }

    protected function handleDelete()
    {
        $filename = trim(rex_request('filename', 'string', ''));
        rex_logger::factory()->log('debug', 'Attempting to delete file: ' . $filename);
        
        if (empty($filename)) {
            throw new rex_api_exception('Missing filename');
        }

        try {
            // Check if file exists in mediapool
            $media = rex_media::get($filename);
            if ($media) {
                // Check if file is still used in other records
                $inUse = false;
                
                // Search all YForm tables
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

                // Only delete if file is not used anymore
                if (!$inUse) {
                    if (rex_media_service::deleteMedia($filename)) {
                        rex_response::sendJson(['status' => 'success']);
                        exit;
                    } else {
                        throw new rex_api_exception('Could not delete file from media pool');
                    }
                } else {
                    // If file is still in use, send success anyway
                    rex_response::sendJson(['status' => 'success']);
                    exit;
                }
            } else {
                // File doesn't exist in mediapool
                rex_response::sendJson(['status' => 'success']);
                exit;
            }
        } catch (rex_api_exception $e) {
            rex_logger::factory()->log('error', 'Error deleting file: ' . $e->getMessage());
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

        // Check if file exists in mediapool
        if (rex_media::get($filename)) {
            rex_response::sendJson(['status' => 'success']);
            exit;
        } else {
            throw new rex_api_exception('File not found in media pool');
        }
    }
}
