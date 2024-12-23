<?php
class rex_api_filepond_uploader extends rex_api_function
{
    protected $published = true;
    private $logger;

    public function __construct() {
         // Logger initialisieren
        $this->logger = rex_logger::factory('filepond', [
            'file' => rex_path::log('filepond.log'),
            'level' => 'debug',
        ]);
    }

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
        $this->logger->debug('Start upload', ['file_name' => $file['name'], 'file_size' => $file['size'], 'file_type' => $file['type']]);

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

        $tmpFilePath = $file['tmp_name'];

        // ImageMagick processing
        $maxWidth = 1200;
        $maxHeight = 1200;
        $isImage = strpos($file['type'], 'image/') === 0;
        $isAnimatedGif = $file['type'] === 'image/gif' && $this->isAnimatedGif($tmpFilePath);


        if ($isImage && !$isAnimatedGif) {
           $this->logger->debug('Image processing', ['isImage' => $isImage, 'isAnimatedGif' => $isAnimatedGif, 'tmpFilePath' => $tmpFilePath]);

            if ($this->isImagemagickAvailable()) {
                  try {
                    $originalFileSize = filesize($tmpFilePath);

                    $newTmpFile = tempnam(sys_get_temp_dir(), 'resized_');
                    $cmd = sprintf(
                         'convert %s -resize %dx%d\> %s',
                        escapeshellarg($tmpFilePath),
                        $maxWidth,
                        $maxHeight,
                        escapeshellarg($newTmpFile)
                    );

                    $this->logger->debug('ImageMagick command', ['cmd' => $cmd]);
                    exec($cmd, $output, $return_var);


                    if ($return_var !== 0) {
                       unlink($newTmpFile);
                        throw new Exception('ImageMagick processing failed: ' .  implode(" ", $output) . ' return_var: ' . $return_var);
                    }

                    $newFileSize = filesize($newTmpFile);
                    $this->logger->debug('ImageMagick resize', ['originalFileSize' => $originalFileSize, 'newFileSize' => $newFileSize, 'output' => $output, 'cmd' => $cmd]);

                    $tmpFilePath = $newTmpFile;

                    $file['size'] = $newFileSize;

                } catch (Exception $e) {
                    unlink($tmpFilePath);
                    throw new rex_api_exception('Image processing failed: ' . $e->getMessage());
                }
            } else {
               // If Imagemagick is not available, check image dimensions
               list($width, $height) = getimagesize($tmpFilePath);
               if ($width > $maxWidth || $height > $maxHeight) {
                    unlink($tmpFilePath);
                   throw new rex_api_exception('Image dimensions too large. Please install ImageMagick or resize manually.');
               }
                $this->logger->debug('No ImageMagick, checking image dimensions', ['width' => $width, 'height' => $height]);
            }
        }

        $data = [
            'title' => $metadata['title'] ?? $filename,
            'category_id' => $categoryId,
            'file' => [
                'name' => $originalName,
                'tmp_name' => $tmpFilePath,
                'type' => $file['type'],
                'size' => $file['size']
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

                if (isset($newTmpFile)) {
                    unlink($newTmpFile);
                }
                 $this->logger->debug('Upload successful', ['filename' => $result['filename']]);
                return $result['filename'];
            }

            if (isset($newTmpFile)) {
                unlink($newTmpFile);
            }
            $this->logger->debug('Upload failed', ['messages' => $result['messages']]);

            throw new rex_api_exception(implode(', ', $result['messages']));

        } catch (Exception $e) {
           if (isset($newTmpFile)) {
                unlink($newTmpFile);
           }
            $this->logger->error('Upload Exception', ['message' => $e->getMessage()]);
            throw new rex_api_exception('Upload failed: ' . $e->getMessage());
        }
    }


    private function isImagemagickAvailable()
    {
        exec('command -v convert', $output, $return_var);
         $this->logger->debug('Check ImageMagick', ['return_var' => $return_var, 'output' => $output]);
        return $return_var === 0;
    }

    private function isAnimatedGif($filePath) {
        $fileContent = file_get_contents($filePath);
         return strpos($fileContent, 'NETSCAPE2.0') !== false;
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
