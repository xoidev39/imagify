<?php

namespace Xoixoi\IMagify\Watermark;

use GdImage;

/**
 * Interface WatermarkInterface
 *
 * Giao diện chuẩn cho các lớp xử lý watermark.
 */
interface WatermarkInterface
{
    /**
     * Kiểm tra watermark có được bật không.
     */
    public function isEnabled(): bool;

    /**
     * Lấy đường dẫn ảnh watermark.
     */
    public function getImage(): string;

    /**
     * Lấy chiều rộng watermark.
     */
    public function getWidth(): string|int;

    /**
     * Lấy chiều cao watermark.
     */
    public function getHeight(): string|int;

    /**
     * Lấy chiều rộng tối đa watermark.
     */
    public function getMaxWidth(): string|int;

    /**
     * Lấy chiều cao tối đa watermark.
     */
    public function getMaxHeight(): string|int;

    /**
     * Lấy chiều rộng tối thiểu watermark.
     */
    public function getMinWidth(): string|int;

    /**
     * Lấy chiều cao tối thiểu watermark.
     */
    public function getMinHeight(): string|int;

    /**
     * Lấy vị trí watermark.
     */
    public function getPosition(): string;

    /**
     * Lấy độ trong suốt watermark.
     */
    public function getOpacity(): int;

    /**
     * Lấy khoảng cách watermark.
     */
    public function getMargin(): int;

    /**
     * Chuyển đổi giá trị kích thước thành pixel.
     */
    public function convertSizeToPixels(string|int $value, int $base): int;

    /**
     * Tính toán kích thước mới cho watermark.
     */
    public function calculateSize(int $imageWidth, int $imageHeight): array;

    /**
     * Tính toán vị trí đặt watermark.
     */
    public function calculatePosition(int $imageWidth, int $imageHeight, int $watermarkWidth, int $watermarkHeight): array;

    /**
     * Thêm watermark vào ảnh.
     */
    public function apply(GdImage $image): bool;

    /**
     * Kiểm tra khả năng sử dụng (ví dụ: có GD không).
     *
     * @return bool
     */
    public function isAvailable(): bool;

    /**
     * Lấy danh sách định dạng ảnh hỗ trợ.
     *
     * @return string[]
     */
    public function getSupportedFormats(): array;
}
