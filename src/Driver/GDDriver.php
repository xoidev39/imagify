<?php

namespace Xoixoi\IMagify\Driver;

use GdImage;
use Xoixoi\IMagify\Exception\ImageProcessorException;
use Xoixoi\IMagify\Watermark\Watermark;

/**
 * Class GDDriver
 *
 * Driver sử dụng thư viện GD của PHP để xử lý ảnh.
 * Dùng làm fallback nếu không thể sử dụng các công cụ binary.
 */
class GDDriver implements DriverInterface
{
    /**
     * @var Watermark|null
     */
    private ?Watermark $watermark = null;

    /**
     * GDDriver constructor.
     *
     * @throws ImageProcessorException
     */
    public function __construct()
    {
        if (!$this->isAvailable()) {
            throw ImageProcessorException::driverNotAvailable('GD Library không được cài đặt');
        }
    }

    /**
     * Xử lý ảnh từ đường dẫn gốc và lưu kết quả.
     *
     * @param string $sourcePath
     * @param string $destinationPath
     * @param array $options
     * @return bool
     * @throws ImageProcessorException
     */
    public function process(string $sourcePath, string $destinationPath, array $options = []): bool
    {
        if (!file_exists($sourcePath)) {
            throw ImageProcessorException::fileNotFound($sourcePath);
        }

        $sourceInfo = getimagesize($sourcePath);
        if ($sourceInfo === false) {
            throw ImageProcessorException::processingFailed('Không thể đọc thông tin ảnh nguồn');
        }

        $sourceImage = $this->createImageFromFile($sourcePath, $sourceInfo[2]);
        if ($sourceImage === false) {
            throw ImageProcessorException::processingFailed('Không thể tạo ảnh nguồn');
        }

        // Xử lý watermark
        if ($options['watermark']['enabled'] ?? false) {
            $watermark = new \Xoixoi\IMagify\Watermark\Watermark($options['watermark']);
            if (!$watermark->apply($sourceImage)) {
                throw ImageProcessorException::processingFailed('Không thể thêm watermark');
            }
        }

        $result = $this->saveImage($sourceImage, $destinationPath, $sourceInfo[2], $options);

        imagedestroy($sourceImage);

        return $result;
    }

    /**
     * Kiểm tra xem GD extension có khả dụng không.
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        return extension_loaded('gd');
    }

    /**
     * Định dạng ảnh được hỗ trợ.
     *
     * @return string[]
     */
    public function getSupportedFormats(): array
    {
        return ['jpg', 'jpeg', 'png', 'gif'];
    }

    /**
     * Tạo ảnh từ file.
     * 
     * @param string $path
     * @param int $type
     * @return GdImage|false
     */
    private function createImageFromFile(string $path, int $type): GdImage|false
    {
        return match ($type) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($path),
            IMAGETYPE_PNG => imagecreatefrompng($path),
            IMAGETYPE_WEBP => imagecreatefromwebp($path),
            default => false,
        };
    }

    /**
     * Lưu ảnh ra file.
     * 
     * @param GdImage $image
     * @param string $path
     * @param int $type
     * @param array $options
     * @return bool
     */
    private function saveImage(GdImage $image, string $path, int $type, array $options = []): bool
    {
        $quality = $options['quality'] ?? 80;

        return match ($type) {
            IMAGETYPE_JPEG => imagejpeg($image, $path, $quality),
            IMAGETYPE_PNG => imagepng($image, $path, (int)(($quality - 100) / 11.111111)),
            IMAGETYPE_WEBP => imagewebp($image, $path, $quality),
            default => false,
        };
    }

    /**
     * Tính toán vị trí watermark.
     * 
     * @param int $sourceWidth
     * @param int $sourceHeight
     * @param int $watermarkWidth
     * @param int $watermarkHeight
     * @param string $position
     * @param int $margin
     * @return array{0: int, 1: int}
     */
    private function calculateWatermarkPosition(
        int $sourceWidth,
        int $sourceHeight,
        int $watermarkWidth,
        int $watermarkHeight,
        string $position,
        int $margin
    ): array {
        return match ($position) {
            'top-left' => [$margin, $margin],
            'top-right' => [$sourceWidth - $watermarkWidth - $margin, $margin],
            'bottom-left' => [$margin, $sourceHeight - $watermarkHeight - $margin],
            'center' => [
                (int)(($sourceWidth - $watermarkWidth) / 2),
                (int)(($sourceHeight - $watermarkHeight) / 2)
            ],
            default => [
                $sourceWidth - $watermarkWidth - $margin,
                $sourceHeight - $watermarkHeight - $margin
            ],
        };
    }

    /**
     * Chuyển đổi giá trị kích thước thành pixel.
     * 
     * @param string|int $value Giá trị kích thước (px hoặc %)
     * @param int $base Kích thước cơ sở để tính phần trăm
     * @return int Kích thước tính bằng pixel
     */
    private function convertSizeToPixels(string|int $value, int $base): int
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

    /**
     * Thêm watermark vào ảnh.
     *
     * @param GdImage $image Ảnh gốc
     * @param array $options Tùy chọn watermark
     * @return bool True nếu thêm thành công
     */
    private function addWatermark(GdImage $image, array $options): bool
    {
        if (empty($options['watermark']['enabled'])) {
            return true;
        }

        $watermarkPath = $options['watermark']['image'];
        if (!file_exists($watermarkPath)) {
            return false;
        }

        // Lấy kích thước ảnh gốc
        $imageWidth = imagesx($image);
        $imageHeight = imagesy($image);

        // Tạo ảnh watermark
        $watermarkType = exif_imagetype($watermarkPath);
        $watermarkImage = match ($watermarkType) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($watermarkPath),
            IMAGETYPE_PNG  => imagecreatefrompng($watermarkPath),
            IMAGETYPE_GIF  => imagecreatefromgif($watermarkPath),
            default => false,
        };

        if (!$watermarkImage) {
            return false;
        }

        // Lấy kích thước watermark gốc
        $watermarkWidth = imagesx($watermarkImage);
        $watermarkHeight = imagesy($watermarkImage);

        // Tính toán kích thước mới cho watermark
        $newWidth = $watermarkWidth;
        $newHeight = $watermarkHeight;

        // Nếu có kích thước cố định
        if (!empty($options['watermark']['width']) && !empty($options['watermark']['height'])) {
            $newWidth = $this->convertSizeToPixels($options['watermark']['width'], $imageWidth);
            $newHeight = $this->convertSizeToPixels($options['watermark']['height'], $imageHeight);
        }
        // Nếu có kích thước tối đa
        else {
            $maxWidth = $this->convertSizeToPixels($options['watermark']['max_width'] ?? 0, $imageWidth);
            $maxHeight = $this->convertSizeToPixels($options['watermark']['max_height'] ?? 0, $imageHeight);

            if ($maxWidth > 0 || $maxHeight > 0) {
                $ratio = $watermarkWidth / $watermarkHeight;

                if ($maxWidth > 0 && $maxHeight > 0) {
                    // Lấy tỷ lệ nhỏ hơn để đảm bảo không vượt quá cả hai
                    $newRatio = min($maxWidth / $watermarkWidth, $maxHeight / $watermarkHeight);
                } elseif ($maxWidth > 0) {
                    $newRatio = $maxWidth / $watermarkWidth;
                } else {
                    $newRatio = $maxHeight / $watermarkHeight;
                }

                $newWidth = (int)($watermarkWidth * $newRatio);
                $newHeight = (int)($watermarkHeight * $newRatio);
            }
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
        $margin = $options['watermark']['margin'] ?? 10;
        $position = $options['watermark']['position'] ?? 'bottom-right';

        $x = match ($position) {
            'top-left' => $margin,
            'top-right' => $imageWidth - $newWidth - $margin,
            'bottom-left' => $margin,
            'bottom-right' => $imageWidth - $newWidth - $margin,
            'center' => (int)(($imageWidth - $newWidth) / 2),
            default => $imageWidth - $newWidth - $margin,
        };

        $y = match ($position) {
            'top-left', 'top-right' => $margin,
            'bottom-left', 'bottom-right' => $imageHeight - $newHeight - $margin,
            'center' => (int)(($imageHeight - $newHeight) / 2),
            default => $imageHeight - $newHeight - $margin,
        };

        // Thêm watermark với độ trong suốt
        $opacity = $options['watermark']['opacity'] ?? 60;

        // Tạo ảnh tạm để xử lý alpha channel
        $tempImage = imagecreatetruecolor($newWidth, $newHeight);
        imagealphablending($tempImage, false);
        imagesavealpha($tempImage, true);
        $transparent = imagecolorallocatealpha($tempImage, 255, 255, 255, 127);
        imagefilledrectangle($tempImage, 0, 0, $newWidth, $newHeight, $transparent);

        // Copy watermark vào ảnh tạm với độ trong suốt
        imagecopy($tempImage, $newWatermark, 0, 0, 0, 0, $newWidth, $newHeight);

        // Copy ảnh tạm vào ảnh gốc
        imagecopymerge($image, $tempImage, $x, $y, 0, 0, $newWidth, $newHeight, $opacity);

        // Giải phóng bộ nhớ
        imagedestroy($watermarkImage);
        imagedestroy($newWatermark);
        imagedestroy($tempImage);

        return true;
    }
}
