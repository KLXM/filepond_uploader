class rex_api_filepond_uploader extends rex_api_function
{
    protected $published = true;
    protected $imageProcessor = 'gd'; // 'convert', 'imagick', 'gd'

    public function __construct()
    {
        // 1. Prüfe ob ImageMagick CLI verfügbar ist
        $convertPath = rex_addon::get('filepond_uploader')->getConfig('convert_path', '');
            
        if (!$convertPath) {
            // Versuche convert im PATH zu finden
            if (rex_system_config::get('os', 'linux')) {
                exec('which convert', $output, $returnVal);
                if ($returnVal === 0 && isset($output[0]) && is_executable($output[0])) {
                    $convertPath = $output[0];
                }
            } else {
                // Typische Pfade prüfen
                $commonPaths = [
                    '/usr/bin/convert',
                    '/usr/local/bin/convert',
                    'C:\\Program Files\\ImageMagick\\convert.exe',
                    'C:\\Program Files (x86)\\ImageMagick\\convert.exe'
                ];
                
                foreach ($commonPaths as $path) {
                    if (file_exists($path) && is_executable($path)) {
                        $convertPath = $path;
                        break;
                    }
                }
            }
        }
            
        if ($convertPath && is_executable($convertPath)) {
            $this->imageProcessor = 'convert';
            // Pfad in den Einstellungen speichern
            rex_config::set('filepond_uploader', 'convert_path', $convertPath);
        } 
        // 2. Prüfe ob PHP-Imagick verfügbar ist
        elseif (class_exists('Imagick')) {
            $this->imageProcessor = 'imagick';
        }
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
                case 'getSettings':
                    $result = $this->handleGetSettings();
                    rex_response::cleanOutputBuffers();
                    rex_response::sendJson($result);
                    exit;

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

    protected function handleGetSettings()
    {
        return [
            'imageMaxWidth' => (int)rex_config::get('filepond_uploader', 'image_max_width', 1920),
            'imageMaxHeight' => (int)rex_config::get('filepond_uploader', 'image_max_height', 1080),
            'imageQuality' => (int)rex_config::get('filepond_uploader', 'image_quality', 90),
            'maxFiles' => (int)rex_config::get('filepond_uploader', 'max_files', 30),
            'maxFileSize' => (int)rex_config::get('filepond_uploader', 'max_filesize', 10),
            'allowedTypes' => rex_config::get('filepond_uploader', 'allowed_types', 'image/*,video/*,.pdf,.doc,.docx,.txt'),
            'imageProcessor' => $this->imageProcessor
        ];
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

        // Prüfe erlaubte Dateitypen
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

        // Bildverarbeitung für Bilder
        if (strpos($file['type'], 'image/') === 0) {
            try {
                $this->processImage($file['tmp_name']);
            } catch (Exception $e) {
                throw new rex_api_exception('Image processing failed: ' . $e->getMessage());
            }
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
            $result = rex_media_service::addMedia($data, true);
            if ($result['ok']) {
                $sql = rex_sql::factory();
                $sql->setTable(rex::getTable('media'));
                $sql->setWhere(['filename' => $result['filename']]);
                $sql->setValue('title', $metadata['title'] ?? '');
                $sql->setValue('med_alt', $metadata['alt'] ?? '');
                $sql->setValue('med_copyright', $metadata['copyright'] ?? '');
                $sql->update();

                rex_media_cache::delete($result['filename']);

                return $result['filename'];
            }
            
            throw new rex_api_exception(implode(', ', $result['messages']));

        } catch (Exception $e) {
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
                // YForm Tabellen durchsuchen
                $yformTables = rex_yform_manager_table::getAll();
                
                foreach ($yformTables as $table) {
                    foreach ($table->getFields() as $field) {
                        if ($field->getType() === 'value' && $field->getTypeName() === 'filepond') {
                            $tableName = $table->getTableName();
                            $fieldName = $field->getName();
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
                        rex_media_cache::delete($filename);
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
        $maxWidth = (int)rex_config::get('filepond_uploader', 'image_max_width', 1920);
        $maxHeight = (int)rex_config::get('filepond_uploader', 'image_max_height', 1080);
        $quality = (int)rex_config::get('filepond_uploader', 'image_quality', 90);

        switch ($this->imageProcessor) {
            case 'convert':
                return $this->processImageWithConvert($tmpFile, $maxWidth, $maxHeight, $quality);
            case 'imagick':
                return $this->processImageWithImageMagick($tmpFile, $maxWidth, $maxHeight, $quality);
            default:
                return $this->processImageWithGD($tmpFile, $maxWidth, $maxHeight, $quality);
        }
    }
protected function processImageWithConvert($tmpFile, $maxWidth, $maxHeight, $quality)
    {
        $convertPath = rex_config::get('filepond_uploader', 'convert_path');
        
        // Temporäre Datei für die Ausgabe
        $outputFile = $tmpFile . '.tmp';
        
        try {
            // Bildgröße ermitteln
            $imageInfo = getimagesize($tmpFile);
            if (!$imageInfo) {
                throw new rex_api_exception('Invalid image file');
            }
            
            $srcWidth = $imageInfo[0];
            $srcHeight = $imageInfo[1];
            
            // Neue Dimensionen berechnen
            $scale = min($maxWidth/$srcWidth, $maxHeight/$srcHeight);
            
            // Nur verarbeiten wenn das Bild größer ist als erlaubt
            if ($scale < 1) {
                $newWidth = round($srcWidth * $scale);
                $newHeight = round($srcHeight * $scale);

                // Command zusammenbauen
                $cmd = [
                    escapeshellcmd($convertPath),
                    escapeshellarg($tmpFile),
                    '-auto-orient',          // EXIF-Orientation berücksichtigen
                    '-strip',                // Metadata entfernen, aber...
                    '-define profile:skip=ICC',  // ... ICC Profile behalten
                    '-resize', escapeshellarg($newWidth . 'x' . $newHeight),
                    '-colorspace', 'sRGB',   // Farbraum vereinheitlichen
                ];

                // Format-spezifische Optimierungen
                switch ($imageInfo[2]) {
                    case IMAGETYPE_JPEG:
                        $cmd[] = '-sampling-factor 4:2:0';  // Chroma subsampling
                        $cmd[] = '-quality ' . escapeshellarg($quality);
                        $cmd[] = '-interlace Plane';        // Progressive JPEG
                        break;
                        
                    case IMAGETYPE_PNG:
                        // PNG Optimierungen
                        $cmd[] = '-depth 8';
                        $cmd[] = '-define png:compression-filter=5';
                        $cmd[] = '-define png:compression-level=9';
                        $cmd[] = '-define png:compression-strategy=1';
                        $cmd[] = '-define png:exclude-chunk=all';
                        // PNG hat keine JPEG-artige Qualität
                        $pngQuality = max(0, min(9, round($quality / 10)));
                        $cmd[] = '-quality ' . escapeshellarg($pngQuality);
                        break;
                        
                    case IMAGETYPE_WEBP:
                        $cmd[] = '-quality ' . escapeshellarg($quality);
                        $cmd[] = '-define webp:lossless=false';
                        $cmd[] = '-define webp:method=6';   // Beste Kompression
                        $cmd[] = '-define webp:alpha-compression=1';
                        $cmd[] = '-define webp:alpha-quality=85';
                        $cmd[] = '-define webp:target-size=0';
                        break;

                    case IMAGETYPE_GIF:
                        // GIF Optimierungen
                        $cmd[] = '-layers optimize';
                        $cmd[] = '-fuzz 5%';
                        break;
                }

                // Output File
                $cmd[] = escapeshellarg($outputFile);

                // Kommando ausführen
                $command = implode(' ', $cmd);
                $output = [];
                $returnVar = 0;
                
                rex_logger::factory()->info('ImageMagick command: ' . $command);
                exec($command . ' 2>&1', $output, $returnVar);

                if ($returnVar !== 0) {
                    throw new rex_api_exception('ImageMagick CLI failed: ' . implode("\n", $output));
                }

                // Erfolgreich verarbeitet - alte Datei ersetzen
                if (file_exists($outputFile)) {
                    rename($outputFile, $tmpFile);
                } else {
                    throw new rex_api_exception('ImageMagick CLI failed: Output file not created');
                }
            }
        } catch (Exception $e) {
            // Aufräumen bei Fehler
            if (file_exists($outputFile)) {
                unlink($outputFile);
            }
            throw $e;
        }
    }

    protected function processImageWithImageMagick($tmpFile, $maxWidth, $maxHeight, $quality)
    {
        try {
            $image = new Imagick($tmpFile);

            // Auto-orientieren basierend auf EXIF
            $image->autoOrient();

            // Ursprüngliche Dimensionen
            $srcWidth = $image->getImageWidth();
            $srcHeight = $image->getImageHeight();

            // Neue Dimensionen berechnen
            $scale = min($maxWidth/$srcWidth, $maxHeight/$srcHeight);
            
            // Nur verarbeiten wenn das Bild größer ist als erlaubt
            if ($scale < 1) {
                $newWidth = round($srcWidth * $scale);
                $newHeight = round($srcHeight * $scale);

                // Bild resizen mit hoher Qualität
                $image->resizeImage($newWidth, $newHeight, Imagick::FILTER_LANCZOS, 1);

                // In sRGB Farbraum konvertieren
                $image->transformImageColorspace(Imagick::COLORSPACE_SRGB);

                // JPEG Optimierungen
                if ($image->getImageFormat() == 'JPEG') {
                    $image->setImageCompression(Imagick::COMPRESSION_JPEG);
                    $image->setImageCompressionQuality($quality);
                    $image->setSamplingFactors(['2x2', '1x1', '1x1']);
                    $image->setInterlaceScheme(Imagick::INTERLACE_PLANE);
                    
                    // EXIF-Daten und Profile erhalten
                    $profiles = $image->getImageProfiles("icc", true);
                    $image->stripImage();
                    if(!empty($profiles)) {
                        $image->profileImage("icc", $profiles['icc']);
                    }
                }
                
                // PNG Optimierungen
                if ($image->getImageFormat() == 'PNG') {
                    $image->setImageCompression(Imagick::COMPRESSION_ZIP);
                    // PNG hat keine JPEG-artige Qualität
                    $pngQuality = max(0, min(9, round($quality / 10)));
                    $image->setImageCompressionQuality($pngQuality * 10);
                    $image->setOption('png:compression-level', '9');
                    $image->setOption('png:compression-strategy', '1');
                    $image->stripImage();
                    // Transparenz erhalten
                    $image->setImageAlphaChannel(Imagick::ALPHACHANNEL_ACTIVATE);
                }

                // WebP Optimierungen
                if ($image->getImageFormat() == 'WEBP') {
                    $image->setImageCompressionQuality($quality);
                    $image->setOption('webp:method', '6');
                    $image->setOption('webp:lossless', 'false');
                }

                // GIF Optimierungen
                if ($image->getImageFormat() == 'GIF') {
                    $image->optimizeImageLayers();
                }

                // Bild speichern
                $image->writeImage($tmpFile);
            }

            $image->destroy();

        } catch (ImagickException $e) {
            throw new rex_api_exception('ImageMagick processing failed: ' . $e->getMessage());
        }
    }

    protected function processImageWithGD($tmpFile, $maxWidth, $maxHeight, $quality)
    {
        if (!function_exists('imagecreatefromstring')) {
            throw new rex_api_exception('GD library is not installed');
        }

        // Bild einlesen
        $imageInfo = getimagesize($tmpFile);
        if (!$imageInfo) {
            throw new rex_api_exception('Invalid image file');
        }

        $sourceImage = imagecreatefromstring(file_get_contents($tmpFile));
        if (!$sourceImage) {
            throw new rex_api_exception('Could not create image');
        }

        // Originale Dimensionen
        $srcWidth = imagesx($sourceImage);
        $srcHeight = imagesy($sourceImage);

        // Neue Dimensionen berechnen
        $scale = min($maxWidth/$srcWidth, $maxHeight/$srcHeight);
        if ($scale >= 1) {
            // Bild ist kleiner als die Maximalgröße
            imagedestroy($sourceImage);
            return;
        }

        $newWidth = round($srcWidth * $scale);
        $newHeight = round($srcHeight * $scale);

        // Neues Bild erstellen
        $newImage = imagecreatetruecolor($newWidth, $newHeight);
        if (!$newImage) {
            imagedestroy($sourceImage);
            throw new rex_api_exception('Could not create resized image');
        }

        // Transparenz erhalten für PNG
        if ($imageInfo[2] === IMAGETYPE_PNG) {
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);
            // Transparenten Hintergrund setzen
            $transparent = imagecolorallocatealpha($newImage, 0, 0, 0, 127);
            imagefilledrectangle($newImage, 0, 0, $newWidth, $newHeight, $transparent);
        }

        // Bild resizen
        if (!imagecopyresampled($newImage, $sourceImage, 0, 0, 0, 0, 
            $newWidth, $newHeight, $srcWidth, $srcHeight)) {
            imagedestroy($sourceImage);
            imagedestroy($newImage);
            throw new rex_api_exception('Could not resize image');
        }

        // Bild speichern
        ob_start();
        $success = false;
        switch ($imageInfo[2]) {
            case IMAGETYPE_JPEG:
                $success = imagejpeg($newImage, $tmpFile, $quality);
                break;
            case IMAGETYPE_PNG:
                // PNG Qualität ist 0-9, daher umrechnen
                $pngQuality = max(0, min(9, round($quality / 10)));
                $success = imagepng($newImage, $tmpFile, $pngQuality);
                break;
            case IMAGETYPE_GIF:
                $success = imagegif($newImage, $tmpFile);
                break;
            case IMAGETYPE_WEBP:
                if (function_exists('imagewebp')) {
                    $success = imagewebp($newImage, $tmpFile, $quality);
                } else {
                    throw new rex_api_exception('WebP support not available in GD');
                }
                break;
            default:
                throw new rex_api_exception('Unsupported image type');
        }
        ob_end_clean();

        imagedestroy($sourceImage);
        imagedestroy($newImage);

        if (!$success) {
            throw new rex_api_exception('Could not save processed image');
        }
    }
}
