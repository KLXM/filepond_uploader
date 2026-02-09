<?php

/**
 * Bildverarbeitung für FilePond Uploads.
 *
 * Enthält Resize, EXIF-Orientierungskorrektur und
 * ImageMagick/GD-Verarbeitung.
 */
class filepond_image_processor
{
    private bool $debug = false;

    public function __construct(bool $debug = false)
    {
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
     * Process and optimize an image (resize and EXIF orientation fix).
     */
    public function processImage(string $tmpFile): void
    {
        $maxPixel = (int) rex_config::get('filepond_uploader', 'max_pixel', 1200);
        $quality = (int) rex_config::get('filepond_uploader', 'image_quality', 90);
        $fixExifOrientation = (bool) rex_config::get('filepond_uploader', 'fix_exif_orientation', false);

        $imageInfo = getimagesize($tmpFile);
        if (false === $imageInfo) {
            return;
        }

        [$width, $height, $type] = $imageInfo;

        // Nur unterstützte Bildformate verarbeiten
        if (!in_array($type, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            return;
        }

        // Prüfen ob Resize nötig ist
        $needsResize = ($width > $maxPixel || $height > $maxPixel);

        // Wenn kein Resize nötig und keine EXIF-Korrektur, abbrechen
        if (!$needsResize && !$fixExifOrientation) {
            return;
        }

        // Prüfen ob ImageMagick verfügbar ist
        $convertBin = $this->findImageMagickBinary();
        if (null !== $convertBin) {
            $this->processImageWithImageMagick($tmpFile, $convertBin, $maxPixel, $quality, $fixExifOrientation, $needsResize, $type);
            return;
        }

        // Fallback auf GD
        $this->processImageWithGD($tmpFile, $maxPixel, $quality, $fixExifOrientation, $needsResize, $type, $width, $height);
    }

    /**
     * Find ImageMagick convert binary.
     *
     * @return string|null Path to convert binary or null if not found
     */
    public function findImageMagickBinary(): ?string
    {
        $possiblePaths = [
            '/usr/bin/convert',
            '/usr/local/bin/convert',
            '/opt/homebrew/bin/convert',
            'convert',
        ];

        foreach ($possiblePaths as $path) {
            $output = [];
            $returnCode = 0;
            @exec($path . ' -version 2>&1', $output, $returnCode);
            if (0 === $returnCode && [] !== $output) {
                $versionString = implode(' ', $output);
                if (str_contains($versionString, 'ImageMagick')) {
                    $this->log('info', "Found ImageMagick at: $path");
                    return $path;
                }
            }
        }

        $this->log('warning', 'ImageMagick not found, falling back to GD');
        return null;
    }

    /**
     * Process image with ImageMagick CLI (resize and EXIF orientation fix).
     */
    public function processImageWithImageMagick(string $tmpFile, string $convertBin, int $maxPixel, int $quality, bool $fixExifOrientation, bool $needsResize, int $type): void
    {
        $cmd = escapeshellcmd($convertBin);
        $cmd .= ' ' . escapeshellarg($tmpFile);

        if ($fixExifOrientation) {
            $cmd .= ' -auto-orient';
        }

        if ($needsResize) {
            $cmd .= ' -resize ' . escapeshellarg($maxPixel . 'x' . $maxPixel . '>');
        }

        $cmd .= ' -quality ' . $quality;
        $cmd .= ' -strip';
        $cmd .= ' ' . escapeshellarg($tmpFile);

        $this->log('info', "Executing ImageMagick: $cmd");

        $output = [];
        $returnCode = 0;
        exec($cmd . ' 2>&1', $output, $returnCode);

        if (0 !== $returnCode) {
            $this->log('error', 'ImageMagick error: ' . implode(' ', $output));
        }
    }

    /**
     * Process image with GD (Fallback, resize and EXIF orientation fix).
     */
    public function processImageWithGD(string $tmpFile, int $maxPixel, int $quality, bool $fixExifOrientation, bool $needsResize, int $type, int $width, int $height): void
    {
        // Fix EXIF orientation first, before any other processing
        if ($fixExifOrientation) {
            $this->fixExifOrientation($tmpFile, $type);
            $imageInfo = getimagesize($tmpFile);
            if (false === $imageInfo) {
                $this->log('error', 'Failed to read image info after EXIF orientation fix');
                return;
            }
            [$width, $height, $type] = $imageInfo;
        }

        if (!$needsResize) {
            return;
        }

        // Neue Dimensionen berechnen
        $newWidth = $width;
        $newHeight = $height;
        $ratio = $width / $height;
        if ($width > $height) {
            $newWidth = min($width, $maxPixel);
            $newHeight = max(1, (int) floor($newWidth / $ratio));
        } else {
            $newHeight = min($height, $maxPixel);
            $newWidth = max(1, (int) floor($newHeight * $ratio));
        }

        $srcImage = null;
        switch ($type) {
            case IMAGETYPE_JPEG:
                $srcImage = imagecreatefromjpeg($tmpFile);
                break;
            case IMAGETYPE_PNG:
                $srcImage = imagecreatefrompng($tmpFile);
                break;
            case IMAGETYPE_WEBP:
                if (function_exists('imagecreatefromwebp')) {
                    $srcImage = imagecreatefromwebp($tmpFile);
                }
                break;
            default:
                return;
        }

        if (false === $srcImage || null === $srcImage) {
            return;
        }

        $dstImage = imagecreatetruecolor(max(1, $newWidth), max(1, $newHeight));
        if (false === $dstImage) {
            return;
        }

        // Preserve transparency for PNG images
        if (IMAGETYPE_PNG === $type) {
            imagealphablending($dstImage, false);
            imagesavealpha($dstImage, true);
            $transparent = imagecolorallocatealpha($dstImage, 255, 255, 255, 127);
            if (false !== $transparent) {
                imagefilledrectangle($dstImage, 0, 0, $newWidth, $newHeight, $transparent);
            }
        }

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
            $height,
        );

        if (IMAGETYPE_JPEG === $type) {
            imagejpeg($dstImage, $tmpFile, $quality);
        } elseif (IMAGETYPE_PNG === $type) {
            $pngQuality = (int) min(9, floor($quality / 10));
            imagepng($dstImage, $tmpFile, $pngQuality);
        } elseif (IMAGETYPE_WEBP === $type) {
            imagewebp($dstImage, $tmpFile, $quality);
        }

        imagedestroy($srcImage);
        imagedestroy($dstImage);
    }

    /**
     * Fix image orientation based on EXIF data.
     */
    public function fixExifOrientation(string $tmpFile, int $type): void
    {
        if (IMAGETYPE_JPEG !== $type) {
            return;
        }

        if (!function_exists('exif_read_data')) {
            $this->log('warning', 'EXIF functions not available - cannot fix orientation');
            return;
        }

        if (!function_exists('imageflip')) {
            $this->log('warning', 'imageflip() function not available, skipping EXIF orientation fix');
            return;
        }

        $exif = @exif_read_data($tmpFile);
        if (false === $exif || !isset($exif['Orientation'])) {
            return;
        }

        $orientation = $exif['Orientation'];

        if (1 === $orientation) {
            return;
        }

        $this->log('info', "Fixing EXIF orientation: $orientation for file: $tmpFile");

        $image = @imagecreatefromjpeg($tmpFile);
        if (false === $image) {
            $this->log('error', 'Failed to load image for EXIF orientation fix');
            return;
        }

        switch ($orientation) {
            case 2:
                if (!imageflip($image, IMG_FLIP_HORIZONTAL)) {
                    $this->log('error', 'Failed to flip image horizontally (orientation 2)');
                    imagedestroy($image);
                    return;
                }
                break;
            case 3:
                $rotated = imagerotate($image, 180, 0);
                if (false === $rotated) {
                    $this->log('error', 'Failed to rotate image 180 degrees');
                    imagedestroy($image);
                    return;
                }
                imagedestroy($image);
                $image = $rotated;
                break;
            case 4:
                if (!imageflip($image, IMG_FLIP_VERTICAL)) {
                    $this->log('error', 'Failed to flip image vertically (orientation 4)');
                    imagedestroy($image);
                    return;
                }
                break;
            case 5:
                if (!imageflip($image, IMG_FLIP_VERTICAL)) {
                    $this->log('error', 'Failed to flip image vertically before rotation (orientation 5)');
                    imagedestroy($image);
                    return;
                }
                $rotated = imagerotate($image, -90, 0);
                if (false === $rotated) {
                    $this->log('error', 'Failed to rotate image -90 degrees after vertical flip');
                    imagedestroy($image);
                    return;
                }
                imagedestroy($image);
                $image = $rotated;
                break;
            case 6:
                $rotated = imagerotate($image, -90, 0);
                if (false === $rotated) {
                    $this->log('error', 'Failed to rotate image -90 degrees');
                    imagedestroy($image);
                    return;
                }
                imagedestroy($image);
                $image = $rotated;
                break;
            case 7:
                if (!imageflip($image, IMG_FLIP_HORIZONTAL)) {
                    $this->log('error', 'Failed to flip image horizontally before rotation (orientation 7)');
                    imagedestroy($image);
                    return;
                }
                $rotated = imagerotate($image, -90, 0);
                if (false === $rotated) {
                    $this->log('error', 'Failed to rotate image -90 degrees after horizontal flip');
                    imagedestroy($image);
                    return;
                }
                imagedestroy($image);
                $image = $rotated;
                break;
            case 8:
                $rotated = imagerotate($image, 90, 0);
                if (false === $rotated) {
                    $this->log('error', 'Failed to rotate image 90 degrees');
                    imagedestroy($image);
                    return;
                }
                imagedestroy($image);
                $image = $rotated;
                break;
        }

        $quality = rex_config::get('filepond_uploader', 'image_quality', 90);
        $qualityInt = is_numeric($quality) ? (int) $quality : 90;

        if (!@imagejpeg($image, $tmpFile, $qualityInt)) {
            $this->log('error', 'Failed to save EXIF-corrected image to file: ' . $tmpFile);
            imagedestroy($image);
            return;
        }

        imagedestroy($image);
        $this->log('info', 'EXIF orientation corrected successfully');
    }
}
