<?php

namespace Xoixoi\IMagify;

use Xoixoi\IMagify\Exception\ImageProcessorException;

/**
 * Class ImageProcessorOptions
 *
 * Quản lý và xác thực cấu hình xử lý ảnh.
 */
class ImageProcessorOptions
{
    public const WATERMARK_POSITIONS = [
        'top-left', 'top-right', 'bottom-left', 'bottom-right', 'center'
    ];

    /**
     * @var array Cấu hình xử lý ảnh
     */
    private array $options = [
        'quality' => 80,
        'png_quality' => 80,
        'optimize' => true,
        'webp' => [
            'enabled' => true,
            'quality' => 80
        ],
        'watermark' => [
            'enabled' => false,
            'image' => '',
            'position' => 'bottom-right',
            'opacity' => 50,
            'margin' => 10
        ]
    ];

    /**
     * ImageProcessorOptions constructor.
     *
     * @param array $options Mảng tùy chọn đầu vào
     * @throws ImageProcessorException
     */
    public function __construct(array $options = [])
    {
        $this->options = array_replace_recursive($this->options, $options);
        $this->validate();
    }

    /**
     * Xác thực toàn bộ cấu hình.
     *
     * @throws ImageProcessorException
     */
    public function validate(): void
    {
        $this->validateQuality('quality', 0, 100);
        $this->validateQuality('png_quality', 0, 100);
        $this->validateWebPOptions();
        $this->validateWatermarkOptions();
    }

    private function validateQuality(string $key, int $min, int $max): void
    {
        if (isset($this->options[$key]) && ($this->options[$key] < $min || $this->options[$key] > $max)) {
            throw ImageProcessorException::invalidOptions(ucfirst($key) . " phải từ $min đến $max");
        }
    }

    private function validateWebPOptions(): void
    {
        if (isset($this->options['webp']['quality']) &&
            ($this->options['webp']['quality'] < 0 || $this->options['webp']['quality'] > 100)) {
            throw ImageProcessorException::invalidOptions('WebP quality phải từ 0 đến 100');
        }
    }

    private function validateWatermarkOptions(): void
    {
        $wm = $this->options['watermark'];

        if ($wm['enabled']) {
            if (empty($wm['image'])) {
                throw ImageProcessorException::invalidOptions('Watermark image không được để trống');
            }

            if (!in_array($wm['position'], self::WATERMARK_POSITIONS, true)) {
                throw ImageProcessorException::invalidOptions('Watermark position không hợp lệ');
            }

            if ($wm['opacity'] < 0 || $wm['opacity'] > 100) {
                throw ImageProcessorException::invalidOptions('Watermark opacity phải từ 0 đến 100');
            }

            if ($wm['margin'] < 0) {
                throw ImageProcessorException::invalidOptions('Watermark margin phải >= 0');
            }
        }
    }

    /**
     * Trả về cấu hình dưới dạng mảng.
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->options;
    }

    // Một số getter tiêu biểu (có thể bổ sung nếu cần dùng riêng)

    public function getQuality(): int
    {
        return $this->options['quality'];
    }

    public function isWebPEnabled(): bool
    {
        return $this->options['webp']['enabled'];
    }

    public function getWebPQuality(): int
    {
        return $this->options['webp']['quality'];
    }

    public function isWatermarkEnabled(): bool
    {
        return $this->options['watermark']['enabled'];
    }

    public function getWatermarkImage(): string
    {
        return $this->options['watermark']['image'];
    }
}
