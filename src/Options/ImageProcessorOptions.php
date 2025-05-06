<?php

namespace Xoixoi\IMagify\Options;

/**
 * Class ImageProcessorOptions
 *
 * Lưu trữ các tùy chọn xử lý ảnh:
 * - Chất lượng ảnh
 * - Tối ưu hóa
 * - Chuyển WebP
 * - Watermark
 * - Kích thước resize
 */
class ImageProcessorOptions
{
    private int $quality = 80;
    private bool $optimize = true;
    private array $webp = ['enabled' => false];
    private array $watermark = [
        'enabled' => false,
        'image' => '',
        'width' => "100px", // Kích thước cố định
        'height' => "100px", // Kích thước cố định
        'max_width' => "20%", // Kích thước tối đa
        'max_height' => "20%", // Kích thước tối đa
        'position' => 'bottom-right',
        'opacity' => 60,
        'margin' => 10
    ];
    private array $sizes = [];

    /**
     * @param array<string,mixed> $options Tùy chọn xử lý ảnh
     */
    public function __construct(array $options = [])
    {
        $this->quality = $options['quality'] ?? $this->quality;
        $this->optimize = $options['optimize'] ?? $this->optimize;
        $this->webp = $options['webp'] ?? $this->webp;
        $this->watermark = array_merge($this->watermark, $options['watermark'] ?? []);
        $this->sizes = $options['sizes'] ?? $this->sizes;
    }

    /**
     * Lấy chất lượng ảnh.
     */
    public function getQuality(): int
    {
        return $this->quality;
    }

    /**
     * Lấy tùy chọn tối ưu.
     */
    public function getOptimize(): bool
    {
        return $this->optimize;
    }

    /**
     * Lấy tùy chọn WebP.
     *
     * @return array<string,mixed>
     */
    public function getWebp(): array
    {
        return $this->webp;
    }

    /**
     * Lấy tùy chọn watermark.
     *
     * @return array<string,mixed>
     */
    public function getWatermark(): array
    {
        return $this->watermark;
    }

    /**
     * Lấy đường dẫn ảnh watermark.
     */
    public function getWatermarkImage(): string
    {
        return $this->watermark['image'] ?? '';
    }

    /**
     * Lấy chiều rộng cố định của watermark.
     */
    public function getWatermarkWidth(): int
    {
        return $this->watermark['width'] ?? 0;
    }

    /**
     * Lấy chiều cao cố định của watermark.
     */
    public function getWatermarkHeight(): int
    {
        return $this->watermark['height'] ?? 0;
    }

    /**
     * Lấy chiều rộng tối đa của watermark.
     */
    public function getWatermarkMaxWidth(): int
    {
        return $this->watermark['max_width'] ?? 0;
    }

    /**
     * Lấy chiều cao tối đa của watermark.
     */
    public function getWatermarkMaxHeight(): int
    {
        return $this->watermark['max_height'] ?? 0;
    }

    /**
     * Lấy vị trí watermark.
     */
    public function getWatermarkPosition(): string
    {
        return $this->watermark['position'] ?? 'bottom-right';
    }

    /**
     * Lấy độ trong suốt watermark.
     */
    public function getWatermarkOpacity(): int
    {
        return $this->watermark['opacity'] ?? 60;
    }

    /**
     * Lấy khoảng cách watermark.
     */
    public function getWatermarkMargin(): int
    {
        return $this->watermark['margin'] ?? 10;
    }

    /**
     * Lấy danh sách kích thước resize.
     *
     * @return array<int,array{name:string,width:int,height:int}>
     */
    public function getSizes(): array
    {
        return $this->sizes;
    }

    /**
     * Chuyển đổi thành mảng.
     *
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'quality' => $this->quality,
            'optimize' => $this->optimize,
            'webp' => $this->webp,
            'watermark' => $this->watermark,
        ];
    }
} 