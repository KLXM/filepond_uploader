<?php

/**
 * Bulk Resize - Batch-Verkleinerung von Bildern im Medienpool
 * 
 * @package filepond_uploader
 */
class filepond_bulk_resize
{
    // Unterstützte Bildformate
    const SUPPORTED_FORMATS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    
    // Formate die nur mit ImageMagick funktionieren
    const IMAGEMAGICK_ONLY_FORMATS = ['psd', 'bmp'];
    
    // Übersprungene Formate
    const SKIPPED_FORMATS = ['tif', 'tiff', 'svg', 'heic', 'ico'];
    
    // Batch Cache Key Prefix
    const BATCH_CACHE_KEY = 'filepond_bulk_resize_';
    
    // Max parallele Prozesse
    const MAX_PARALLEL = 3;

    /**
     * Prüft ob ImageMagick verfügbar ist
     */
    public static function hasImageMagick(): bool
    {
        if (class_exists('Imagick')) {
            return true;
        }
        
        if (function_exists('exec')) {
            $out = [];
            exec('command -v convert || which convert 2>/dev/null', $out, $ret);
            return $ret === 0 && !empty($out[0]);
        }
        
        return false;
    }

    /**
     * Prüft ob GD verfügbar ist
     */
    public static function hasGD(): bool
    {
        return extension_loaded('gd');
    }

    /**
     * Prüft ob ein Bild verarbeitet werden kann
     */
    public static function canProcessImage(string $filename): array
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        $result = [
            'canProcess' => false,
            'needsImageMagick' => false,
            'format' => $extension,
            'reason' => ''
        ];
        
        if (in_array($extension, self::SKIPPED_FORMATS)) {
            $result['reason'] = 'Format wird bei Bulk-Verarbeitung übersprungen';
            return $result;
        }
        
        if (in_array($extension, self::SUPPORTED_FORMATS)) {
            $result['canProcess'] = true;
        } elseif (in_array($extension, self::IMAGEMAGICK_ONLY_FORMATS)) {
            if (self::hasImageMagick()) {
                $result['canProcess'] = true;
                $result['needsImageMagick'] = true;
            } else {
                $result['reason'] = 'Format benötigt ImageMagick';
            }
        } else {
            $result['reason'] = 'Nicht unterstütztes Format';
        }
        
        return $result;
    }

    /**
     * Findet alle Bilder die größer als die max. Werte sind
     */
    public static function findOversizedImages(int $maxWidth, int $maxHeight, array $filters = []): array
    {
        $where = ['filetype LIKE "image/%"'];
        
        // Größenfilter
        $sizeConditions = [];
        if ($maxWidth > 0) {
            $sizeConditions[] = 'width > ' . $maxWidth;
        }
        if ($maxHeight > 0) {
            $sizeConditions[] = 'height > ' . $maxHeight;
        }
        if (!empty($sizeConditions)) {
            $where[] = '(' . implode(' OR ', $sizeConditions) . ')';
        }
        
        // Zusätzliche Filter
        if (!empty($filters['filename'])) {
            $where[] = 'filename LIKE ' . rex_sql::factory()->escape('%' . $filters['filename'] . '%');
        }
        if (!empty($filters['category_id'])) {
            $categories = array_map('intval', explode(',', $filters['category_id']));
            $where[] = 'category_id IN (' . implode(',', $categories) . ')';
        }
        if (!empty($filters['min_filesize'])) {
            $where[] = 'CAST(filesize as SIGNED) >= ' . (intval($filters['min_filesize']) * 1024);
        }
        if (!empty($filters['min_width'])) {
            $where[] = 'width >= ' . intval($filters['min_width']);
        }
        if (!empty($filters['min_height'])) {
            $where[] = 'height >= ' . intval($filters['min_height']);
        }
        
        $sql = rex_sql::factory();
        $sql->setQuery('
            SELECT id, filename, category_id, filesize, width, height, title, createdate, createuser
            FROM ' . rex::getTable('media') . '
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY filesize DESC
        ');
        
        return $sql->getArray();
    }

    /**
     * Startet einen Batch-Verarbeitungsvorgang
     */
    public static function startBatch(array $filenames, int $maxWidth, int $maxHeight, int $quality = 85): string
    {
        $batchId = uniqid('batch_', true);
        
        $batchData = [
            'id' => $batchId,
            'filenames' => $filenames,
            'maxWidth' => $maxWidth,
            'maxHeight' => $maxHeight,
            'quality' => $quality,
            'total' => count($filenames),
            'processed' => 0,
            'successful' => 0,
            'savedBytes' => 0,
            'errors' => [],
            'skipped' => [],
            'results' => [],
            'status' => 'running',
            'processQueue' => array_values($filenames),
            'currentFiles' => [],
            'startTime' => time()
        ];
        
        self::saveBatchStatus($batchId, $batchData);
        
        return $batchId;
    }

    /**
     * Speichert den Batch-Status
     */
    private static function saveBatchStatus(string $batchId, array $data): void
    {
        $cacheDir = rex_path::addonCache('filepond_uploader');
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0775, true);
        }
        
        rex_file::put(
            $cacheDir . '/' . self::BATCH_CACHE_KEY . $batchId . '.json',
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    /**
     * Holt den Batch-Status
     */
    public static function getBatchStatus(string $batchId): ?array
    {
        $cacheFile = rex_path::addonCache('filepond_uploader') . '/' . self::BATCH_CACHE_KEY . $batchId . '.json';
        
        if (!file_exists($cacheFile)) {
            return null;
        }
        
        return json_decode(rex_file::get($cacheFile), true);
    }

    /**
     * Holt erweiterten Status mit UI-Infos
     */
    public static function getBatchStatusExtended(string $batchId): ?array
    {
        $status = self::getBatchStatus($batchId);
        if (!$status) {
            return null;
        }
        
        $progress = $status['total'] > 0 
            ? round(($status['processed'] / $status['total']) * 100, 1) 
            : 0;
        
        $elapsed = time() - ($status['startTime'] ?? time());
        $remainingTime = null;
        
        if ($status['processed'] > 0 && $elapsed > 0) {
            $avgTime = $elapsed / $status['processed'];
            $remaining = $status['total'] - $status['processed'];
            $remainingTime = round($avgTime * $remaining);
        }
        
        $currentlyProcessing = [];
        if (!empty($status['currentFiles'])) {
            foreach ($status['currentFiles'] as $process) {
                if (is_array($process) && isset($process['filename'])) {
                    $currentlyProcessing[] = [
                        'filename' => $process['filename'],
                        'duration' => isset($process['startTime']) 
                            ? round(microtime(true) - $process['startTime'], 1) 
                            : 0
                    ];
                }
            }
        }
        
        return array_merge($status, [
            'progress' => $progress,
            'remainingTime' => $remainingTime,
            'elapsedTime' => $elapsed,
            'currentlyProcessing' => $currentlyProcessing,
            'queueLength' => count($status['processQueue'] ?? []),
            'activeProcesses' => count($status['currentFiles'] ?? []),
            'savedBytesFormatted' => rex_formatter::bytes($status['savedBytes'] ?? 0)
        ]);
    }

    /**
     * Verarbeitet die nächsten Bilder im Batch
     */
    public static function processNextBatchItems(string $batchId): array
    {
        $status = self::getBatchStatus($batchId);
        
        if (!$status || $status['status'] !== 'running') {
            return ['status' => 'error', 'message' => 'Batch nicht gefunden oder beendet'];
        }
        
        $queue = $status['processQueue'] ?? [];
        
        if (empty($queue)) {
            $status['status'] = 'completed';
            $status['endTime'] = time();
            $status['currentFiles'] = [];
            self::saveBatchStatus($batchId, $status);
            return ['status' => 'completed', 'batch' => self::getBatchStatusExtended($batchId)];
        }
        
        // Bis zu MAX_PARALLEL Dateien verarbeiten
        $filesToProcess = array_slice($queue, 0, self::MAX_PARALLEL);
        $remainingQueue = array_slice($queue, self::MAX_PARALLEL);
        
        // Aktuelle Dateien markieren
        $currentFiles = [];
        foreach ($filesToProcess as $filename) {
            $currentFiles[uniqid('p_')] = [
                'filename' => $filename,
                'startTime' => microtime(true),
                'status' => 'processing'
            ];
        }
        
        $status['processQueue'] = $remainingQueue;
        $status['currentFiles'] = $currentFiles;
        self::saveBatchStatus($batchId, $status);
        
        // Dateien verarbeiten
        $results = [];
        foreach ($filesToProcess as $filename) {
            $result = self::resizeFile(
                $filename,
                $status['maxWidth'],
                $status['maxHeight'],
                $status['quality']
            );
            $results[] = $result;
        }
        
        // Status aktualisieren
        $status = self::getBatchStatus($batchId);
        $status['processed'] += count($results);
        $status['currentFiles'] = [];
        
        foreach ($results as $result) {
            if ($result['success']) {
                $status['successful']++;
                $status['savedBytes'] += $result['savedBytes'] ?? 0;
                $status['results'][] = $result;
            } elseif ($result['skipped'] ?? false) {
                $status['skipped'][$result['filename']] = $result['reason'] ?? 'Übersprungen';
            } else {
                $status['errors'][$result['filename']] = $result['error'] ?? 'Unbekannter Fehler';
            }
        }
        
        // Prüfen ob fertig
        if (empty($status['processQueue'])) {
            $status['status'] = 'completed';
            $status['endTime'] = time();
        }
        
        self::saveBatchStatus($batchId, $status);
        
        return [
            'status' => $status['status'] === 'completed' ? 'completed' : 'processing',
            'batch' => self::getBatchStatusExtended($batchId),
            'processedInThisStep' => count($results)
        ];
    }

    /**
     * Verkleinert eine einzelne Datei
     */
    public static function resizeFile(string $filename, int $maxWidth, int $maxHeight, int $quality = 85): array
    {
        try {
            $canProcess = self::canProcessImage($filename);
            
            if (!$canProcess['canProcess']) {
                return [
                    'success' => false,
                    'skipped' => true,
                    'reason' => $canProcess['reason'],
                    'filename' => $filename
                ];
            }
            
            $media = rex_media::get($filename);
            if (!$media || !$media->isImage()) {
                return [
                    'success' => false,
                    'skipped' => true,
                    'reason' => 'Keine gültige Mediendatei',
                    'filename' => $filename
                ];
            }
            
            $imagePath = rex_path::media($filename);
            $originalSize = filesize($imagePath);
            $imageInfo = @getimagesize($imagePath);
            
            if (!$imageInfo || $imageInfo[0] == 0 || $imageInfo[1] == 0) {
                return [
                    'success' => false,
                    'skipped' => true,
                    'reason' => 'Bilddimensionen nicht lesbar',
                    'filename' => $filename
                ];
            }
            
            $origWidth = $imageInfo[0];
            $origHeight = $imageInfo[1];
            
            // Prüfen ob Resize nötig
            if (($maxWidth == 0 || $origWidth <= $maxWidth) && ($maxHeight == 0 || $origHeight <= $maxHeight)) {
                return [
                    'success' => false,
                    'skipped' => true,
                    'reason' => 'Bild ist bereits klein genug',
                    'filename' => $filename
                ];
            }
            
            // Verarbeitung mit ImageMagick oder GD
            if ($canProcess['needsImageMagick'] || (self::hasImageMagick() && class_exists('Imagick'))) {
                $result = self::resizeWithImageMagick($imagePath, $maxWidth, $maxHeight, $quality);
            } else {
                $result = self::resizeWithGD($imagePath, $maxWidth, $maxHeight, $quality, $imageInfo[2]);
            }
            
            if (!$result) {
                return [
                    'success' => false,
                    'error' => 'Bildverarbeitung fehlgeschlagen',
                    'filename' => $filename
                ];
            }
            
            // Neue Größen ermitteln
            clearstatcache(true, $imagePath);
            $newSize = filesize($imagePath);
            $newImageInfo = @getimagesize($imagePath);
            
            // Datenbank aktualisieren
            $sql = rex_sql::factory();
            $sql->setTable(rex::getTable('media'));
            $sql->setWhere(['filename' => $filename]);
            $sql->setValue('filesize', $newSize);
            $sql->setValue('width', $newImageInfo[0]);
            $sql->setValue('height', $newImageInfo[1]);
            $sql->setValue('updatedate', date('Y-m-d H:i:s'));
            $sql->setValue('updateuser', rex::getUser() ? rex::getUser()->getLogin() : 'system');
            $sql->update();
            
            // Cache löschen
            rex_media_cache::delete($filename);
            
            return [
                'success' => true,
                'filename' => $filename,
                'originalSize' => $originalSize,
                'newSize' => $newSize,
                'savedBytes' => $originalSize - $newSize,
                'originalDimensions' => $origWidth . 'x' . $origHeight,
                'newDimensions' => $newImageInfo[0] . 'x' . $newImageInfo[1],
                'method' => class_exists('Imagick') ? 'ImageMagick' : 'GD'
            ];
            
        } catch (Exception $e) {
            rex_logger::logException($e);
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'filename' => $filename
            ];
        }
    }

    /**
     * Resize mit ImageMagick
     */
    private static function resizeWithImageMagick(string $imagePath, int $maxWidth, int $maxHeight, int $quality): bool
    {
        if (class_exists('Imagick')) {
            try {
                $imagick = new Imagick($imagePath);
                
                // EXIF-Orientierung korrigieren (falls Methode existiert)
                if (method_exists($imagick, 'autoOrientImage')) {
                    $imagick->autoOrientImage();
                } else {
                    // Manuelle Orientierung basierend auf EXIF
                    $orientation = $imagick->getImageOrientation();
                    switch ($orientation) {
                        case Imagick::ORIENTATION_BOTTOMRIGHT:
                            $imagick->rotateImage('#000', 180);
                            break;
                        case Imagick::ORIENTATION_RIGHTTOP:
                            $imagick->rotateImage('#000', 90);
                            break;
                        case Imagick::ORIENTATION_LEFTBOTTOM:
                            $imagick->rotateImage('#000', -90);
                            break;
                    }
                    $imagick->setImageOrientation(Imagick::ORIENTATION_TOPLEFT);
                }
                
                $imagick->resizeImage($maxWidth, $maxHeight, Imagick::FILTER_LANCZOS, 1, true);
                
                $format = strtolower($imagick->getImageFormat());
                if (in_array($format, ['jpeg', 'jpg'])) {
                    $imagick->setImageCompressionQuality($quality);
                } elseif ($format === 'png') {
                    $imagick->setImageCompressionQuality(round($quality / 10));
                } elseif ($format === 'webp') {
                    $imagick->setImageCompressionQuality($quality);
                }
                
                $imagick->writeImage($imagePath);
                $imagick->clear();
                return true;
            } catch (Exception $e) {
                rex_logger::logException($e);
                return false;
            }
        }
        
        // Fallback zu Binary
        $tempPath = $imagePath . '.tmp';
        $cmd = sprintf(
            'convert %s -auto-orient -resize %dx%d\> -quality %d %s 2>&1',
            escapeshellarg($imagePath),
            $maxWidth,
            $maxHeight,
            $quality,
            escapeshellarg($tempPath)
        );
        
        exec($cmd, $output, $returnVar);
        
        if ($returnVar !== 0 || !file_exists($tempPath)) {
            @unlink($tempPath);
            return false;
        }
        
        if (!rename($tempPath, $imagePath)) {
            @unlink($tempPath);
            return false;
        }
        
        return true;
    }

    /**
     * Resize mit GD
     */
    private static function resizeWithGD(string $imagePath, int $maxWidth, int $maxHeight, int $quality, int $imageType): bool
    {
        // Bild laden
        switch ($imageType) {
            case IMAGETYPE_JPEG:
                $source = @imagecreatefromjpeg($imagePath);
                break;
            case IMAGETYPE_PNG:
                $source = @imagecreatefrompng($imagePath);
                break;
            case IMAGETYPE_GIF:
                $source = @imagecreatefromgif($imagePath);
                break;
            case IMAGETYPE_WEBP:
                $source = @imagecreatefromwebp($imagePath);
                break;
            default:
                return false;
        }
        
        if (!$source) {
            return false;
        }
        
        $origWidth = imagesx($source);
        $origHeight = imagesy($source);
        
        // Neue Dimensionen berechnen
        $ratio = min(
            $maxWidth > 0 ? $maxWidth / $origWidth : 1,
            $maxHeight > 0 ? $maxHeight / $origHeight : 1
        );
        
        if ($ratio >= 1) {
            imagedestroy($source);
            return false; // Kein Resize nötig
        }
        
        $newWidth = round($origWidth * $ratio);
        $newHeight = round($origHeight * $ratio);
        
        // Neues Bild erstellen
        $destination = imagecreatetruecolor($newWidth, $newHeight);
        
        // Transparenz für PNG/GIF erhalten
        if ($imageType === IMAGETYPE_PNG || $imageType === IMAGETYPE_GIF) {
            imagealphablending($destination, false);
            imagesavealpha($destination, true);
            $transparent = imagecolorallocatealpha($destination, 0, 0, 0, 127);
            imagefill($destination, 0, 0, $transparent);
        }
        
        // Resamplen
        imagecopyresampled($destination, $source, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);
        
        // Speichern
        $result = false;
        switch ($imageType) {
            case IMAGETYPE_JPEG:
                $result = imagejpeg($destination, $imagePath, $quality);
                break;
            case IMAGETYPE_PNG:
                $result = imagepng($destination, $imagePath, round(9 - ($quality / 11)));
                break;
            case IMAGETYPE_GIF:
                $result = imagegif($destination, $imagePath);
                break;
            case IMAGETYPE_WEBP:
                $result = imagewebp($destination, $imagePath, $quality);
                break;
        }
        
        imagedestroy($source);
        imagedestroy($destination);
        
        return $result;
    }

    /**
     * Alte Batch-Dateien aufräumen
     */
    public static function cleanupOldBatches(): void
    {
        $cacheDir = rex_path::addonCache('filepond_uploader');
        $files = glob($cacheDir . '/' . self::BATCH_CACHE_KEY . '*.json');
        
        if (!$files) return;
        
        foreach ($files as $file) {
            if (filemtime($file) < time() - 3600) { // älter als 1 Stunde
                @unlink($file);
            }
        }
    }

    /**
     * Formatiert Bytes human-readable
     */
    public static function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2, ',', '.') . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2, ',', '.') . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2, ',', '.') . ' KB';
        }
        return $bytes . ' Bytes';
    }

    /**
     * Lightens/darkens a color
     */
    public static function adjustColor(string $hexColor, float $factor = 1.0): string
    {
        $color = ltrim($hexColor, '#');
        if (strlen($color) == 3) {
            $color = $color[0] . $color[0] . $color[1] . $color[1] . $color[2] . $color[2];
        }
        
        $factor = max(0, min(2, $factor));
        
        $r = hexdec(substr($color, 0, 2));
        $g = hexdec(substr($color, 2, 2));
        $b = hexdec(substr($color, 4, 2));
        
        if ($factor < 1) {
            $r = round($r * $factor);
            $g = round($g * $factor);
            $b = round($b * $factor);
        } elseif ($factor > 1) {
            $adj = $factor - 1;
            $r = round($r + (255 - $r) * $adj);
            $g = round($g + (255 - $g) * $adj);
            $b = round($b + (255 - $b) * $adj);
        }
        
        return sprintf("#%02x%02x%02x", $r, $g, $b);
    }
}
