<?php
namespace AssistedAutocataloguing\Ocr;

/**
 * Image Preprocessor for OCR and Security
 */
class ImagePreprocessor
{
    private array $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
    private int $maxDimension = 2400;

    public function validateUploadedFile(array $fileInfo, int $maxSizeBytes = 10485760): array
    {
        if (!isset($fileInfo['tmp_name']) || !is_uploaded_file($fileInfo['tmp_name'])) {
            return ['valid' => false, 'error' => 'File tidak valid atau tidak terunggah dengan benar'];
        }

        if ($fileInfo['size'] > $maxSizeBytes) {
            return ['valid' => false, 'error' => 'Ukuran file melebihi batas maksimal (' . round($maxSizeBytes / 1048576, 1) . ' MB)'];
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $fileInfo['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, $this->allowedMimes, true)) {
            return ['valid' => false, 'error' => 'Format file tidak diizinkan. Hanya JPEG, PNG, dan WebP yang didukung.'];
        }

        $imageSize = @getimagesize($fileInfo['tmp_name']);
        if ($imageSize === false) {
            return ['valid' => false, 'error' => 'File bukan gambar valid atau rusak'];
        }

        return ['valid' => true, 'mime' => $mime, 'width' => $imageSize[0], 'height' => $imageSize[1]];
    }

    /**
     * Preprocess image for OCR (auto-orient, resize if too large, contrast enhancement)
     */
    public function preprocessForOcr(string $sourcePath, string $targetPath): bool
    {
        if (!extension_loaded('gd')) {
            return copy($sourcePath, $targetPath);
        }

        $info = @getimagesize($sourcePath);
        if (!$info) {
            return false;
        }

        $mime = $info['mime'];
        $srcImg = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($sourcePath),
            'image/png' => @imagecreatefrompng($sourcePath),
            'image/webp' => @imagecreatefromwebp($sourcePath),
            default => null,
        };

        if (!$srcImg) {
            return copy($sourcePath, $targetPath);
        }

        // Auto orientation via EXIF if JPEG
        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $exif = @exif_read_data($sourcePath);
            if (!empty($exif['Orientation'])) {
                $srcImg = match ($exif['Orientation']) {
                    3 => imagerotate($srcImg, 180, 0),
                    6 => imagerotate($srcImg, -90, 0),
                    8 => imagerotate($srcImg, 90, 0),
                    default => $srcImg,
                };
            }
        }

        $width = imagesx($srcImg);
        $height = imagesy($srcImg);

        // Resize down if too large
        $targetWidth = $width;
        $targetHeight = $height;
        if ($width > $this->maxDimension || $height > $this->maxDimension) {
            if ($width > $height) {
                $targetWidth = $this->maxDimension;
                $targetHeight = (int)round(($height * $this->maxDimension) / $width);
            } else {
                $targetHeight = $this->maxDimension;
                $targetWidth = (int)round(($width * $this->maxDimension) / $height);
            }
        }

        $dstImg = imagecreatetruecolor($targetWidth, $targetHeight);

        // Handle transparency for PNG/WebP
        imagealphablending($dstImg, false);
        imagesavealpha($dstImg, true);
        $white = imagecolorallocate($dstImg, 255, 255, 255);
        imagefilledrectangle($dstImg, 0, 0, $targetWidth, $targetHeight, $white);

        imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        // Apply Grayscale filter for OCR contrast
        imagefilter($dstImg, IMG_FILTER_GRAYSCALE);
        imagefilter($dstImg, IMG_FILTER_CONTRAST, -15);

        // Save as clean JPEG
        $success = imagejpeg($dstImg, $targetPath, 92);

        imagedestroy($srcImg);
        imagedestroy($dstImg);

        return $success;
    }

    /**
     * Create smaller thumbnail for web preview
     */
    public function createThumbnail(string $sourcePath, string $thumbPath, int $thumbWidth = 320): bool
    {
        if (!extension_loaded('gd')) {
            return false;
        }

        $info = @getimagesize($sourcePath);
        if (!$info) {
            return false;
        }

        $srcImg = match ($info['mime']) {
            'image/jpeg' => @imagecreatefromjpeg($sourcePath),
            'image/png' => @imagecreatefrompng($sourcePath),
            'image/webp' => @imagecreatefromwebp($sourcePath),
            default => null,
        };

        if (!$srcImg) {
            return false;
        }

        $width = imagesx($srcImg);
        $height = imagesy($srcImg);
        $thumbHeight = (int)round(($height * $thumbWidth) / $width);

        $thumb = imagecreatetruecolor($thumbWidth, $thumbHeight);
        imagecopyresampled($thumb, $srcImg, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $width, $height);

        $saved = imagejpeg($thumb, $thumbPath, 85);
        imagedestroy($srcImg);
        imagedestroy($thumb);

        return $saved;
    }
}
