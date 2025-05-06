<?php

namespace Xoixoi\IMagify\Driver;

/**
 * Interface DriverInterface
 *
 * Giao diện chuẩn cho các driver xử lý ảnh.
 */
interface DriverInterface
{
    /**
     * Xử lý ảnh nguồn và lưu ra đích.
     *
     * @param string $source_path       Đường dẫn ảnh nguồn
     * @param string $destination_path  Đường dẫn ảnh đầu ra
     * @param array $options            Tùy chọn xử lý
     * @return bool                     True nếu xử lý thành công
     */
    public function process(string $source_path, string $destination_path, array $options = []): bool;

    /**
     * Kiểm tra driver có sẵn trên hệ thống không.
     *
     * @return bool
     */
    public function isAvailable(): bool;

    /**
     * Lấy danh sách định dạng ảnh mà driver hỗ trợ.
     *
     * @return string[]
     */
    public function getSupportedFormats(): array;
}
