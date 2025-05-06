<?php

namespace Xoixoi\IMagify\Watermark;

use GdImage;
use Xoixoi\IMagify\Exception\ImageProcessorException;

/**
 * Class Watermark
 *
 * Áp dụng watermark lên ảnh sử dụng thư viện GD.
 */
class Watermark implements WatermarkInterface
{
    private bool $enabled = false;
    private string $image = '';
    private string|int $width = '100px';
    private string|int $height = '100px';
    private string|int $maxWidth = '20%';
    private string|int $maxHeight = '20%';
    private string|int $minWidth = '50px';
    private string|int $minHeight = '50px';
    private string $position = 'bottom-right';
    private int $opacity = 60;
    private int $margin = 10;

    /**
     * @param array<string,mixed> $options
     */
    public function __construct(array $options = [])
    {
        $this->enabled = $options['enabled'] ?? $this->enabled;
        $this->image = $options['image'] ?? $this->image;
        $this->width = $options['width'] ?? $this->width;
        $this->height = $options['height'] ?? $this->height;
        $this->maxWidth = $options['max_width'] ?? $this->maxWidth;
        $this->maxHeight = $options['max_height'] ?? $this->maxHeight;
        $this->minWidth = $options['min_width'] ?? $this->minWidth;
        $this->minHeight = $options['min_height'] ?? $this->minHeight;
        $this->position = $options['position'] ?? $this->position;
        $this->opacity = $options['opacity'] ?? $this->opacity;
        $this->margin = $options['margin'] ?? $this->margin;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getImage(): string
    {
        return $this->image;
    }

    public function getWidth(): string|int
    {
        return $this->width;
    }

    public function getHeight(): string|int
    {
        return $this->height;
    }

    public function getMaxWidth(): string|int
    {
        return $this->maxWidth;
    }

    public function getMaxHeight(): string|int
    {
        return $this->maxHeight;
    }

    public function getMinWidth(): string|int
    {
        return $this->minWidth;
    }

    public function getMinHeight(): string|int
    {
        return $this->minHeight;
    }

    public function getPosition(): string
    {
        return $this->position;
    }

    public function getOpacity(): int
    {
        return $this->opacity;
    }

    public function getMargin(): int
    {
        return $this->margin;
    }

    public function convertSizeToPixels(string|int $value, int $base): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (str_ends_with($value, 'px')) {
            return (int)str_replace('px', '', $value);
        }

        if (str_ends_with($value, '%')) {
            $percent = (int)str_replace('%', '', $value);
            return (int)($base * $percent / 100);
        }

        return (int)$value;
    }

    public function calculateSize(int $imageWidth, int $imageHeight): array
    {
        if (!file_exists($this->image)) {
            throw ImageProcessorException::watermarkNotFound($this->image);
        }

        $watermarkInfo = getimagesize($this->image);
        if ($watermarkInfo === false) {
            throw ImageProcessorException::processingFailed('Không thể đọc thông tin ảnh watermark');
        }

        $watermarkWidth = $watermarkInfo[0];
        $watermarkHeight = $watermarkInfo[1];

        // Nếu có kích thước cố định
        if (!empty($this->width) && !empty($this->height)) {
            $width = $this->convertSizeToPixels($this->width, $imageWidth);
            $height = $this->convertSizeToPixels($this->height, $imageHeight);
            
            // Kiểm tra kích thước tối thiểu
            $minWidth = $this->convertSizeToPixels($this->minWidth, $imageWidth);
            $minHeight = $this->convertSizeToPixels($this->minHeight, $imageHeight);
            
            return [
                'width' => max($width, $minWidth),
                'height' => max($height, $minHeight)
            ];
        }

        // Nếu có kích thước tối đa
        $maxWidth = $this->convertSizeToPixels($this->maxWidth, $imageWidth);
        $maxHeight = $this->convertSizeToPixels($this->maxHeight, $imageHeight);
        $minWidth = $this->convertSizeToPixels($this->minWidth, $imageWidth);
        $minHeight = $this->convertSizeToPixels($this->minHeight, $imageHeight);

        if ($maxWidth > 0 || $maxHeight > 0) {
            $ratio = $watermarkWidth / $watermarkHeight;

            if ($maxWidth > 0 && $maxHeight > 0) {
                $newRatio = min($maxWidth / $watermarkWidth, $maxHeight / $watermarkHeight);
            } elseif ($maxWidth > 0) {
                $newRatio = $maxWidth / $watermarkWidth;
            } else {
                $newRatio = $maxHeight / $watermarkHeight;
            }

            $width = (int)($watermarkWidth * $newRatio);
            $height = (int)($watermarkHeight * $newRatio);

            return [
                'width' => max($width, $minWidth),
                'height' => max($height, $minHeight)
            ];
        }

        return [
            'width' => max($watermarkWidth, $minWidth),
            'height' => max($watermarkHeight, $minHeight)
        ];
    }

    public function calculatePosition(int $imageWidth, int $imageHeight, int $watermarkWidth, int $watermarkHeight): array
    {
        $margin = $this->margin;

        $x = match ($this->position) {
            'top-left' => $margin,
            'top-right' => $imageWidth - $watermarkWidth - $margin,
            'bottom-left' => $margin,
            'bottom-right' => $imageWidth - $watermarkWidth - $margin,
            'center' => (int)(($imageWidth - $watermarkWidth) / 2),
            default => $imageWidth - $watermarkWidth - $margin,
        };

        $y = match ($this->position) {
            'top-left', 'top-right' => $margin,
            'bottom-left', 'bottom-right' => $imageHeight - $watermarkHeight - $margin,
            'center' => (int)(($imageHeight - $watermarkHeight) / 2),
            default => $imageHeight - $watermarkHeight - $margin,
        };

        return [$x, $y];
    }

    public function apply(GdImage $image): bool
    {
        if (!$this->enabled) {
            return true;
        }

        if (!file_exists($this->image)) {
            return false;
        }

        // Lấy kích thước ảnh gốc
        $imageWidth = imagesx($image);
        $imageHeight = imagesy($image);

        // Tính toán kích thước watermark
        $size = $this->calculateSize($imageWidth, $imageHeight);
        $newWidth = $size['width'];
        $newHeight = $size['height'];

        // Tạo ảnh watermark
        $watermarkType = exif_imagetype($this->image);
        $watermarkImage = match ($watermarkType) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($this->image),
            IMAGETYPE_PNG  => imagecreatefrompng($this->image),
            IMAGETYPE_GIF  => imagecreatefromgif($this->image),
            default => false,
        };

        if (!$watermarkImage) {
            return false;
        }

        // Lấy kích thước watermark gốc
        $watermarkWidth = imagesx($watermarkImage);
        $watermarkHeight = imagesy($watermarkImage);

        // Tính toán tỉ lệ để giữ nguyên tỉ lệ gốc
        $ratio = $watermarkWidth / $watermarkHeight;
        if ($newWidth / $newHeight > $ratio) {
            $newWidth = (int)($newHeight * $ratio);
        } else {
            $newHeight = (int)($newWidth / $ratio);
        }

        // Tạo ảnh watermark mới với kích thước đã tính
        $newWatermark = imagecreatetruecolor($newWidth, $newHeight);

        // Giữ alpha channel cho PNG
        if ($watermarkType === IMAGETYPE_PNG) {
            imagealphablending($newWatermark, false);
            imagesavealpha($newWatermark, true);
            $transparent = imagecolorallocatealpha($newWatermark, 255, 255, 255, 127);
            imagefilledrectangle($newWatermark, 0, 0, $newWidth, $newHeight, $transparent);
        }

        // Resize watermark
        imagecopyresampled(
            $newWatermark, $watermarkImage,
            0, 0, 0, 0,
            $newWidth, $newHeight,
            $watermarkWidth, $watermarkHeight
        );

        // Tính toán vị trí đặt watermark
        [$x, $y] = $this->calculatePosition($imageWidth, $imageHeight, $newWidth, $newHeight);

        // Tạo ảnh tạm để xử lý alpha channel
        $tempImage = imagecreatetruecolor($newWidth, $newHeight);
        imagealphablending($tempImage, false);
        imagesavealpha($tempImage, true);
        $transparent = imagecolorallocatealpha($tempImage, 255, 255, 255, 127);
        imagefilledrectangle($tempImage, 0, 0, $newWidth, $newHeight, $transparent);

        // Copy watermark vào ảnh tạm với độ trong suốt
        imagecopy($tempImage, $newWatermark, 0, 0, 0, 0, $newWidth, $newHeight);

        // Bật alpha blending cho ảnh gốc
        imagealphablending($image, true);
        imagesavealpha($image, true);

        // Xử lý từng pixel để áp dụng opacity
        for ($i = 0; $i < $newWidth; $i++) {
            for ($j = 0; $j < $newHeight; $j++) {
                $color = imagecolorat($tempImage, $i, $j);
                $alpha = ($color >> 24) & 0xFF;
                
                if ($alpha < 127) { // Chỉ xử lý các pixel không hoàn toàn trong suốt
                    $r = ($color >> 16) & 0xFF;
                    $g = ($color >> 8) & 0xFF;
                    $b = $color & 0xFF;
                    
                    // Áp dụng opacity
                    $newAlpha = (int)((127 - $alpha) * ($this->opacity / 100));
                    $newColor = imagecolorallocatealpha($image, $r, $g, $b, 127 - $newAlpha);
                    
                    imagesetpixel($image, $x + $i, $y + $j, $newColor);
                }
            }
        }

        // Giải phóng bộ nhớ
        imagedestroy($watermarkImage);
        imagedestroy($newWatermark);
        imagedestroy($tempImage);

        return true;
    }

    /**
     * Kiểm tra khả năng sử dụng watermark.
     */
    public function isAvailable(): bool
    {
        return extension_loaded('gd');
    }

    /**
     * Lấy danh sách định dạng ảnh được hỗ trợ.
     */
    public function getSupportedFormats(): array
    {
        return ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico', 'heic', 'heif'];
    }
}
