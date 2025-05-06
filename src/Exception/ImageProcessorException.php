<?php

namespace Xoixoi\IMagify\Exception;

/**
 * Class ImageProcessorException
 *
 * Xử lý ngoại lệ cho thư viện xử lý ảnh iMagify.
 */
class ImageProcessorException extends \Exception
{
    public const ERROR_FILE_NOT_FOUND         = 1;
    public const ERROR_UNSUPPORTED_FORMAT     = 2;
    public const ERROR_PROCESSING_FAILED      = 3;
    public const ERROR_WATERMARK_NOT_FOUND    = 4;
    public const ERROR_CONVERTER_NOT_AVAILABLE = 5;
    public const ERROR_DIRECTORY_NOT_FOUND    = 6;
    public const ERROR_PROCESSOR_NOT_FOUND    = 7;
    public const ERROR_COLLECTION_EMPTY       = 8;
    public const ERROR_INVALID_OPTIONS        = 9;
    public const ERROR_DRIVER_NOT_AVAILABLE   = 10;

    public static function fileNotFound(string $path): self
    {
        return new self("File không tồn tại: {$path}", self::ERROR_FILE_NOT_FOUND);
    }

    public static function unsupportedFormat(string $format): self
    {
        return new self("Định dạng không được hỗ trợ: {$format}", self::ERROR_UNSUPPORTED_FORMAT);
    }

    public static function processingFailed(string $message): self
    {
        return new self("Xử lý ảnh thất bại: {$message}", self::ERROR_PROCESSING_FAILED);
    }

    public static function watermarkNotFound(string $path): self
    {
        return new self("File watermark không tồn tại: {$path}", self::ERROR_WATERMARK_NOT_FOUND);
    }

    public static function converterNotAvailable(string $format): self
    {
        return new self("Không tìm thấy converter cho định dạng: {$format}", self::ERROR_CONVERTER_NOT_AVAILABLE);
    }

    public static function directoryNotFound(string $path): self
    {
        return new self("Thư mục không tồn tại: {$path}", self::ERROR_DIRECTORY_NOT_FOUND);
    }

    public static function processorNotFound(string $name): self
    {
        return new self("Không tìm thấy processor: {$name}", self::ERROR_PROCESSOR_NOT_FOUND);
    }

    public static function collectionEmpty(): self
    {
        return new self("Collection không có ảnh nào", self::ERROR_COLLECTION_EMPTY);
    }

    public static function invalidOptions(string $message): self
    {
        return new self("Tùy chọn không hợp lệ: {$message}", self::ERROR_INVALID_OPTIONS);
    }

    public static function driverNotAvailable(string $driver): self
    {
        return new self("Driver không khả dụng: {$driver}", self::ERROR_DRIVER_NOT_AVAILABLE);
    }
}
