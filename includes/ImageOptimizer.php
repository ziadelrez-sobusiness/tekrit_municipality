<?php
/**
 * ImageOptimizer Helper Class
 * Intercepts oversized uploads and converts them to responsive WebP format under 300KB
 */
class ImageOptimizer {
    
    /**
     * Optimizes an image and saves it as WebP.
     * 
     * @param string $sourcePath Local path to original uploaded image
     * @param string $targetPath Destination path for optimized WebP image
     * @param int $maxDimension Maximum width or height boundary (default 1200px)
     * @param int $quality Compression quality level (1-100, default 80)
     * @return bool True if successful, false otherwise
     */
    public static function optimizeToWebP($sourcePath, $targetPath, $maxDimension = 1200, $quality = 80) {
        if (!file_exists($sourcePath)) {
            return false;
        }

        $imageInfo = getimagesize($sourcePath);
        if (!$imageInfo) {
            return false;
        }

        $mime = $imageInfo['mime'];
        $width = $imageInfo[0];
        $height = $imageInfo[1];

        // 1. Create image resource based on mime type
        switch ($mime) {
            case 'image/jpeg':
            case 'image/jpg':
                $image = imagecreatefromjpeg($sourcePath);
                break;
            case 'image/png':
                $image = imagecreatefrompng($sourcePath);
                // Preserve transparency for PNG conversions if needed
                imagepalettetotruecolor($image);
                imagealphablending($image, true);
                imagesavealpha($image, true);
                break;
            case 'image/gif':
                $image = imagecreatefromgif($sourcePath);
                imagepalettetotruecolor($image);
                break;
            case 'image/webp':
                $image = imagecreatefromwebp($sourcePath);
                break;
            default:
                return false; // Unsupported format
        }

        if (!$image) {
            return false;
        }

        // 2. Downsize if the dimensions exceed max limit
        if ($width > $maxDimension || $height > $maxDimension) {
            if ($width > $height) {
                $newWidth = $maxDimension;
                $newHeight = (int)($height * ($maxDimension / $width));
            } else {
                $newHeight = $maxDimension;
                $newWidth = (int)($width * ($maxDimension / $height));
            }

            $resizedImage = imagecreatetruecolor($newWidth, $newHeight);
            
            // Preserve transparency for WebP destination
            imagealphablending($resizedImage, false);
            imagesavealpha($resizedImage, true);
            
            imagecopyresampled($resizedImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagedestroy($image);
            $image = $resizedImage;
        }

        // 3. Save as WebP
        $result = imagewebp($image, $targetPath, $quality);
        imagedestroy($image);

        // 4. Double check file size constraint (<300KB). If still too large, compress further.
        if ($result && file_exists($targetPath) && filesize($targetPath) > 300 * 1024) {
            // Re-optimize with lower quality limit
            $image = imagecreatefromwebp($targetPath);
            if ($image) {
                imagewebp($image, $targetPath, 60); // Drop quality to 60 for smaller footprint
                imagedestroy($image);
            }
        }

        return $result;
    }
}
