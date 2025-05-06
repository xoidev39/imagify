<?php

namespace Xoixoi\IMagify;

use Xoixoi\IMagify\Driver\BinaryDriver;
use Xoixoi\IMagify\Driver\GDDriver;
use Xoixoi\IMagify\Driver\DriverInterface;
use Xoixoi\IMagify\Exception\ImageProcessorException;
use Xoixoi\IMagify\Options\ImageProcessorOptions;

/**
 * Class ImageProcessor
 *
 * Bộ xử lý ảnh trung tâm. Tự động phát hiện driver thích hợp (ưu tiên Binary).
 * Cho phép resize, watermark, optimize, convert ảnh.
 */
class ImageProcessor
{
    /**
     * @var DriverInterface
     */
    private DriverInterface $driver;

    /**
     * @var ImageProcessorOptions
     */
    private ImageProcessorOptions $options;

    /**
     * ImageProcessor constructor.
     *
     * @param array $options Cấu hình xử lý ảnh
     * @throws ImageProcessorException
     */
    public function __construct(array $options = [])
    {
        $this->options = new ImageProcessorOptions($options);
        $this->detectDriver();
    }

    /**
     * Tự động phát hiện driver xử lý ảnh phù hợp.
     *
     * Ưu tiên BinaryDriver → fallback về GDDriver nếu cần.
     *
     * @throws ImageProcessorException
     */
    private function detectDriver(): void
    {
        $binaryDriver = new BinaryDriver();
        if ($binaryDriver->isAvailable()) {
            $this->driver = $binaryDriver;
            return;
        }

        $gdDriver = new GDDriver();
        if ($gdDriver->isAvailable()) {
            $this->driver = $gdDriver;
            return;
        }

        throw ImageProcessorException::driverNotAvailable('Không tìm thấy driver phù hợp');
    }

    /**
     * Xử lý một file ảnh với các tùy chọn hiện tại.
     *
     * @param string $sourcePath      Đường dẫn ảnh gốc
     * @param string $destinationPath Đường dẫn ảnh sau xử lý
     * @return bool
     * @throws ImageProcessorException
     */
    public function process(string $sourcePath, string $destinationPath): bool
    {
        if (!file_exists($sourcePath)) {
            throw ImageProcessorException::fileNotFound($sourcePath);
        }

        $format = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
        if (!in_array($format, $this->driver->getSupportedFormats())) {
            throw ImageProcessorException::unsupportedFormat($format);
        }

        // Xử lý resize trước
        $sizes = $this->options->getSizes();
        if (!empty($sizes)) {
            foreach ($sizes as $size) {
                $resizedPath = $this->getResizedPath($destinationPath, $size['name']);
                if (!$this->resizeImage($sourcePath, $resizedPath, $size['width'], $size['height'])) {
                    throw ImageProcessorException::processingFailed("Không thể resize ảnh thành kích thước {$size['name']}");
                }
                // Xử lý các bước khác cho ảnh đã resize
                $this->driver->process($resizedPath, $resizedPath, $this->options->toArray());
            }
        }

        // Xử lý ảnh gốc
        return $this->driver->process($sourcePath, $destinationPath, $this->options->toArray());
    }

    /**
     * Resize và crop ảnh theo kích thước mới.
     *
     * @param string $sourcePath Đường dẫn file nguồn
     * @param string $destinationPath Đường dẫn file đích
     * @param int $width Chiều rộng mới
     * @param int $height Chiều cao mới
     * @return bool True nếu resize thành công
     */
    private function resizeImage(string $sourcePath, string $destinationPath, int $width, int $height): bool
    {
        [$origWidth, $origHeight, $type] = getimagesize($sourcePath);
        if (!$origWidth || !$origHeight) {
            return false;
        }

        // Tính toán kích thước mới giữ tỷ lệ
        $ratio = max($width / $origWidth, $height / $origHeight);
        $newWidth = (int)($origWidth * $ratio);
        $newHeight = (int)($origHeight * $ratio);

        // Tạo ảnh nguồn
        $srcImage = match ($type) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($sourcePath),
            IMAGETYPE_PNG  => imagecreatefrompng($sourcePath),
            IMAGETYPE_GIF  => imagecreatefromgif($sourcePath),
            default => false,
        };

        if (!$srcImage) {
            return false;
        }

        // Tạo ảnh tạm để scale
        $tempImage = imagecreatetruecolor($newWidth, $newHeight);

        // Giữ alpha channel cho PNG
        if ($type === IMAGETYPE_PNG) {
            imagealphablending($tempImage, false);
            imagesavealpha($tempImage, true);
            $transparent = imagecolorallocatealpha($tempImage, 255, 255, 255, 127);
            imagefilledrectangle($tempImage, 0, 0, $newWidth, $newHeight, $transparent);
        }

        // Scale ảnh
        imagecopyresampled(
            $tempImage, $srcImage,
            0, 0, 0, 0,
            $newWidth, $newHeight,
            $origWidth, $origHeight
        );

        // Tạo ảnh đích với kích thước cuối cùng
        $dstImage = imagecreatetruecolor($width, $height);

        // Giữ alpha channel cho PNG
        if ($type === IMAGETYPE_PNG) {
            imagealphablending($dstImage, false);
            imagesavealpha($dstImage, true);
            $transparent = imagecolorallocatealpha($dstImage, 255, 255, 255, 127);
            imagefilledrectangle($dstImage, 0, 0, $width, $height, $transparent);
        }

        // Tính toán vị trí crop
        $srcX = (int)(($newWidth - $width) / 2);
        $srcY = (int)(($newHeight - $height) / 2);

        // Crop ảnh
        imagecopy(
            $dstImage, $tempImage,
            0, 0, $srcX, $srcY,
            $width, $height
        );

        // Lưu ảnh
        $saved = match ($type) {
            IMAGETYPE_JPEG => imagejpeg($dstImage, $destinationPath, 90),
            IMAGETYPE_PNG  => imagepng($dstImage, $destinationPath, 9),
            IMAGETYPE_GIF  => imagegif($dstImage, $destinationPath),
            default => false,
        };

        // Giải phóng bộ nhớ
        imagedestroy($srcImage);
        imagedestroy($tempImage);
        imagedestroy($dstImage);

        return $saved;
    }

    /**
     * Tạo đường dẫn cho ảnh đã resize.
     *
     * @param string $path Đường dẫn gốc
     * @param string $sizeName Tên kích thước
     * @return string Đường dẫn mới
     */
    private function getResizedPath(string $path, string $sizeName): string
    {
        $info = pathinfo($path);
        return $info['dirname'] . '/' . $info['filename'] . '_' . $sizeName . '.' . $info['extension'];
    }

    /**
     * Trả về tên class của driver đang được sử dụng.
     *
     * @return string
     */
    public function getDriver(): string
    {
        return get_class($this->driver);
    }

    /**
     * Lấy ra cấu hình xử lý ảnh hiện tại.
     *
     * @return ImageProcessorOptions
     */
    public function getOptions(): ImageProcessorOptions
    {
        return $this->options;
    }
}
