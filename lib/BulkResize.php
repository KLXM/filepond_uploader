<?php

namespace FriendsOfRedaxo\FilePondUploader;

use rex;
use rex_addon;
use rex_file;
use rex_logger;
use rex_managed_media;
use rex_media;
use rex_media_cache;
use rex_path;
use Exception;

/**
 * Bulk Resize - Performante Bildverarbeitung
 *
 * @package filepond_uploader
 */
class BulkResize
{
    const SUPPORTED_FORMATS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
    const IMAGEMAGICK_ONLY_FORMATS = ['psd'];
    const SKIPPED_FORMATS = ['tif', 'tiff', 'svg', 'heic'];
    const BATCH_CACHE_KEY = 'filepond_bulk_batch_';
    const MAX_PARALLEL_PROCESSES = 1; // Reduziert auf 1 für bessere Kompatibilität mit Shared Hosting
    const MAX_EXECUTION_TIME_PER_IMAGE = 30; // Maximale Zeit pro Bild in Sekunden

    /**
     * Maximale parallele Prozesse (konfigurierbar)
     */
    public static function getMaxParallelProcesses(): int
    {
        return (int) rex_addon::get('filepond_uploader')->getConfig('bulk-max-parallel', self::MAX_PARALLEL_PROCESSES);
    }

    /**
     * Prüft ob Bild verarbeitet werden kann
     */
    public static function canProcessImage(string $filename): array
    {
        $extension = strtolower(rex_file::extension($filename));

        $result = [
            'canProcess' => false,
            'needsImageMagick' => false,
            'format' => $extension,
            'reason' => '',
        ];

        if (in_array($extension, self::SKIPPED_FORMATS)) {
            $result['reason'] = 'Format wird übersprungen';
            return $result;
        }

        if (in_array($extension, self::SUPPORTED_FORMATS)) {
            $result['canProcess'] = true;
        } elseif (in_array($extension, self::IMAGEMAGICK_ONLY_FORMATS)) {
            if (self::hasImageMagick()) {
                $result['canProcess'] = true;
                $result['needsImageMagick'] = true;
            } else {
                $result['reason'] = 'Benötigt ImageMagick';
            }
        } else {
            $result['reason'] = 'Nicht unterstützt';
        }

        return $result;
    }

    /**
     * Prüft ImageMagick Verfügbarkeit
     */
    public static function hasImageMagick(): bool
    {
        return class_exists('Imagick') || !empty(self::getConvertPath());
    }

    /**
     * Ermittelt convert Binary Pfad
     */
    private static function getConvertPath(): string
    {
        $path = '';
        if (function_exists('exec')) {
            $out = [];
            exec('command -v convert || which convert', $out, $ret);
            if (0 === $ret && !empty($out[0])) {
                $path = (string) $out[0];
            }
        }
        return $path;
    }

    /**
     * Prüft GD Verfügbarkeit
     */
    public static function hasGD(): bool
    {
        return extension_loaded('gd');
    }

    /**
     * Startet Batch-Verarbeitung
     */
    public static function startBatchProcessing(array $filenames, ?int $maxWidth = null, ?int $maxHeight = null): string
    {
        $batchId = uniqid('batch_', true);

        $batchData = [
            'id' => $batchId,
            'filenames' => $filenames,
            'maxWidth' => $maxWidth,
            'maxHeight' => $maxHeight,
            'total' => count($filenames),
            'processed' => 0,
            'successful' => 0,
            'errors' => [],
            'skipped' => [],
            'savedBytes' => 0,
            'status' => 'running',
            'currentFiles' => [],
            'processQueue' => array_values($filenames),
            'startTime' => time(),
        ];

        rex_file::put(
            rex_path::addonCache('filepond_uploader', self::BATCH_CACHE_KEY . $batchId . '.json'),
            json_encode($batchData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        return $batchId;
    }

    /**
     * Holt Batch Status
     */
    public static function getBatchStatus(string $batchId): ?array
    {
        $cacheFile = rex_path::addonCache('filepond_uploader', self::BATCH_CACHE_KEY . $batchId . '.json');

        if (!file_exists($cacheFile)) {
            return null;
        }

        $content = rex_file::get($cacheFile);
        return json_decode($content, true);
    }

    /**
     * Aktualisiert Batch Status
     */
    private static function updateBatchStatus(string $batchId, array $updates): bool
    {
        $status = self::getBatchStatus($batchId);
        if (!$status) {
            return false;
        }

        $status = array_merge($status, $updates);

        return rex_file::put(
            rex_path::addonCache('filepond_uploader', self::BATCH_CACHE_KEY . $batchId . '.json'),
            json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    /**
     * Verarbeitet nächste Batch Items (parallel)
     */
    public static function processNextBatchItems(string $batchId): array
    {
        // Erhöhe Memory Limit falls möglich
        @ini_set('memory_limit', '256M');
        
        // Setze Execution Time Limit
        @set_time_limit(60);
        
        $status = self::getBatchStatus($batchId);

        if (!$status) {
            return ['error' => 'Batch nicht gefunden', 'finished' => true];
        }

        if ('completed' === $status['status']) {
            return ['finished' => true, 'status' => $status];
        }

        $maxParallel = self::getMaxParallelProcesses();
        $processed = 0;
        $results = [];
        $startTime = time();

        while ($processed < $maxParallel && !empty($status['processQueue'])) {
            // Timeout-Check: Breche ab wenn zu lange
            if (time() - $startTime > 45) {
                rex_logger::factory()->log('warning', 'Bulk Resize: Timeout nach 45 Sekunden erreicht');
                break;
            }
            $filename = array_shift($status['processQueue']);
            $status['currentFiles'][] = $filename;

            $result = self::processImage($filename, $status['maxWidth'], $status['maxHeight']);

            if ($result['success']) {
                ++$status['successful'];
                $status['savedBytes'] += $result['savedBytes'] ?? 0;
            } else {
                if ('skipped' === $result['reason']) {
                    $status['skipped'][] = [
                        'filename' => $filename,
                        'reason' => $result['message'],
                    ];
                } else {
                    $status['errors'][] = [
                        'filename' => $filename,
                        'error' => $result['error'],
                    ];
                }
            }

            ++$status['processed'];
            $results[] = $result;
            ++$processed;

            $status['currentFiles'] = array_values(array_diff($status['currentFiles'], [$filename]));
        }

        if (empty($status['processQueue'])) {
            $status['status'] = 'completed';
            $status['endTime'] = time();
        }

        self::updateBatchStatus($batchId, $status);

        return [
            'finished' => 'completed' === $status['status'],
            'status' => $status,
            'results' => $results,
        ];
    }

    /**
     * Verarbeitet einzelnes Bild
     */
    public static function processImage(string $filename, ?int $maxWidth, ?int $maxHeight): array
    {
        $media = rex_media::get($filename);

        if (!$media) {
            return [
                'success' => false,
                'filename' => $filename,
                'error' => 'Datei nicht gefunden',
            ];
        }

        $filePath = rex_path::media($filename);

        if (!is_file($filePath)) {
            return [
                'success' => false,
                'filename' => $filename,
                'error' => 'Datei existiert nicht',
            ];
        }

        $canProcess = self::canProcessImage($filename);
        if (!$canProcess['canProcess']) {
            return [
                'success' => false,
                'filename' => $filename,
                'reason' => 'skipped',
                'message' => $canProcess['reason'],
            ];
        }

        $originalSize = filesize($filePath);
        $width = $media->getWidth();
        $height = $media->getHeight();

        // Prüfe ob Resize nötig
        if (($maxWidth && $width > $maxWidth) || ($maxHeight && $height > $maxHeight)) {
            try {
                $resized = self::resizeImage($filePath, $maxWidth, $maxHeight);

                if ($resized) {
                    // Neue Datei-Informationen ermitteln
                    $newSize = filesize($filePath);
                    $savedBytes = max(0, $originalSize - $newSize);
                    
                    // Neue Bildabmessungen ermitteln
                    $newDimensions = @getimagesize($filePath);
                    $newWidth = $newDimensions ? $newDimensions[0] : $width;
                    $newHeight = $newDimensions ? $newDimensions[1] : $height;

                    // Datenbank aktualisieren (OHNE updatedate/updateuser zu ändern)
                    $sql = \rex_sql::factory();
                    $sql->setTable(\rex::getTablePrefix() . 'media');
                    $sql->setWhere(['filename' => $filename]);
                    $sql->setValue('filesize', $newSize);
                    $sql->setValue('width', $newWidth);
                    $sql->setValue('height', $newHeight);
                    // NICHT addGlobalUpdateFields() aufrufen - würde updatedate/updateuser ändern
                    $sql->update();

                    // Cache löschen damit Media-Objekt neu geladen wird
                    rex_media_cache::delete($filename);

                    return [
                        'success' => true,
                        'filename' => $filename,
                        'originalSize' => $originalSize,
                        'newSize' => $newSize,
                        'savedBytes' => $savedBytes,
                        'width' => $newWidth,
                        'height' => $newHeight,
                    ];
                }

                return [
                    'success' => false,
                    'filename' => $filename,
                    'error' => 'Resize fehlgeschlagen',
                ];
            } catch (Exception $e) {
                rex_logger::logException($e);

                return [
                    'success' => false,
                    'filename' => $filename,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'success' => false,
            'filename' => $filename,
            'reason' => 'skipped',
            'message' => 'Bereits klein genug',
        ];
    }

    /**
     * Resize Bild mit GD oder ImageMagick
     */
    private static function resizeImage(string $filePath, ?int $maxWidth, ?int $maxHeight): bool
    {
        $imageSize = getimagesize($filePath);
        if (!$imageSize) {
            return false;
        }

        [$width, $height] = $imageSize;

        // Berechne neue Dimensionen
        $ratio = $width / $height;

        if ($maxWidth && $maxHeight) {
            if ($width / $maxWidth > $height / $maxHeight) {
                $newWidth = $maxWidth;
                $newHeight = (int) ($maxWidth / $ratio);
            } else {
                $newHeight = $maxHeight;
                $newWidth = (int) ($maxHeight * $ratio);
            }
        } elseif ($maxWidth) {
            $newWidth = $maxWidth;
            $newHeight = (int) ($maxWidth / $ratio);
        } elseif ($maxHeight) {
            $newHeight = $maxHeight;
            $newWidth = (int) ($maxHeight * $ratio);
        } else {
            return false;
        }

        // Versuche ImageMagick CLI für große Bilder
        if ($width > 3000 || $height > 3000) {
            $convertPath = self::getConvertPath();
            if ($convertPath) {
                $tempFile = $filePath . '.tmp';
                $cmd = sprintf(
                    '%s %s -resize %dx%d\> -quality 85 %s',
                    escapeshellcmd($convertPath),
                    escapeshellarg($filePath),
                    $newWidth,
                    $newHeight,
                    escapeshellarg($tempFile)
                );

                exec($cmd, $output, $returnVar);

                if (0 === $returnVar && is_file($tempFile)) {
                    rename($tempFile, $filePath);
                    return true;
                }
            }
        }

        // Fallback: GD
        return self::resizeWithGD($filePath, $newWidth, $newHeight, $imageSize[2]);
    }

    /**
     * Resize mit GD Library
     */
    private static function resizeWithGD(string $filePath, int $newWidth, int $newHeight, int $imageType): bool
    {
        // Lade Bild
        switch ($imageType) {
            case IMAGETYPE_JPEG:
                $source = imagecreatefromjpeg($filePath);
                break;
            case IMAGETYPE_PNG:
                $source = imagecreatefrompng($filePath);
                break;
            case IMAGETYPE_GIF:
                $source = imagecreatefromgif($filePath);
                break;
            case IMAGETYPE_WEBP:
                $source = imagecreatefromwebp($filePath);
                break;
            default:
                return false;
        }

        if (!$source) {
            return false;
        }

        // Erstelle neues Bild mit besserer Qualität
        $destination = imagecreatetruecolor($newWidth, $newHeight);

        // Transparenz für PNG/GIF/WebP
        if (IMAGETYPE_PNG === $imageType || IMAGETYPE_GIF === $imageType || IMAGETYPE_WEBP === $imageType) {
            imagealphablending($destination, false);
            imagesavealpha($destination, true);
            $transparent = imagecolorallocatealpha($destination, 0, 0, 0, 127);
            imagefill($destination, 0, 0, $transparent);
        }

        // Aktiviere beste Interpolation (falls verfügbar)
        if (function_exists('imagesetinterpolation')) {
            imagesetinterpolation($destination, IMG_BICUBIC);
        }

        // Resize mit bester Qualität
        imagecopyresampled($destination, $source, 0, 0, 0, 0, $newWidth, $newHeight, imagesx($source), imagesy($source));

        // Speichere mit hoher Qualität
        $result = false;
        switch ($imageType) {
            case IMAGETYPE_JPEG:
                // JPEG: Qualität 95 für beste Bildqualität
                $result = imagejpeg($destination, $filePath, 95);
                break;
            case IMAGETYPE_PNG:
                // PNG: Kompression Level 6 ist gut (0=keine, 9=max)
                $result = imagepng($destination, $filePath, 6);
                break;
            case IMAGETYPE_GIF:
                $result = imagegif($destination, $filePath);
                break;
            case IMAGETYPE_WEBP:
                // WebP: Qualität 95 für beste Bildqualität
                $result = imagewebp($destination, $filePath, 95);
                break;
        }

        imagedestroy($source);
        imagedestroy($destination);

        return $result;
    }

    /**
     * Bereinigt alte Batch-Dateien (älter als 24h)
     */
    public static function cleanupOldBatches(): void
    {
        $cacheDir = rex_path::addonCache('filepond_uploader');
        $files = glob($cacheDir . self::BATCH_CACHE_KEY . '*.json');

        foreach ($files as $file) {
            if (filemtime($file) < time() - 86400) {
                @unlink($file);
            }
        }
    }

    /**
     * Hellt Farbe auf (für UI-Visualisierung)
     */
    public static function lightenColor(string $hex, float $factor): string
    {
        $hex = ltrim($hex, '#');

        if (3 === strlen($hex)) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        $r = min(255, (int) ($r + (255 - $r) * (1 - $factor)));
        $g = min(255, (int) ($g + (255 - $g) * (1 - $factor)));
        $b = min(255, (int) ($b + (255 - $b) * (1 - $factor)));

        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }
}
