<?php

namespace Xoixoi\IMagify\Driver;

use Xoixoi\IMagify\Exception\ImageProcessorException;
use Exception;

/**
 * Class BinaryDriver
 *
 * Driver sử dụng các công cụ xử lý ảnh bên ngoài qua exec().
 * Tự động chọn và tối ưu hóa các binary phù hợp với hệ điều hành.
 */
class BinaryDriver implements DriverInterface
{
    /** @var array<string,string> */
    private array $binaries = [];

    /**
     * @param string|null $binDir Thư mục chứa các binary. Mặc định dùng thư mục bin bên cạnh.
     * @param array<string,mixed> $options Cấu hình driver: ['quality'=>int, 'optimize'=>bool, 'webp'=>['enabled'=>bool]].
     * @throws ImageProcessorException
     */
    public function __construct(?string $binDir = null, private array $options = [])
    {
        $dir = $binDir ?: dirname(__DIR__) . '/bin';
        $this->options += [
            'quality'  => 80,
            'optimize' => true,
            'webp'     => ['enabled' => false]
        ];
        $this->initializeBinaries($dir);
    }

    /**
     * Nạp và kiểm tra các binary cần thiết theo OS.
     *
     * @throws ImageProcessorException
     */
    private function initializeBinaries(string $dir): void
    {
        $os     = strtolower(PHP_OS);
        $suffix = match (true) {
            str_starts_with($os, 'win')    => '.exe',
            str_starts_with($os, 'darwin') => '-mac',
            str_starts_with($os, 'linux')  => '-linux',
            default                        => throw ImageProcessorException::driverNotAvailable("Unsupported OS: $os")
        };

        $tools = ['jpegtran', 'optipng', 'pngquant', 'gifsicle', 'cwebp'];
        foreach ($tools as $tool) {
            $binary = "$dir/{$tool}$suffix";
            if (!is_file($binary)) {
                throw ImageProcessorException::driverNotAvailable("Missing binary: $binary");
            }
            if (!is_executable($binary)) {
                @chmod($binary, 0755);
            }
            $this->binaries[$tool] = $binary;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isAvailable(): bool
    {
        return !empty($this->binaries);
    }

    /**
     * {@inheritdoc}
     */
    public function getSupportedFormats(): array
    {
        return ['jpg', 'jpeg', 'png', 'gif'];
    }

    /**
     * {@inheritdoc}
     *
     * @throws ImageProcessorException
     */
    public function process(string $sourcePath, string $destinationPath, array $opts = []): bool
    {
        if (!file_exists($sourcePath)) {
            throw ImageProcessorException::fileNotFound($sourcePath);
        }

        $opts += $this->options;
        $format   = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
        $tempPath = $sourcePath;

        // Thêm watermark nếu có
        if (!empty($opts['watermark']['enabled'])) {
            try {
                $gdDriver = new GDDriver();
                $tempPath = $sourcePath . '.watermark.' . $format;
                
                // Sử dụng GDDriver để thêm watermark
                if (!$gdDriver->process($sourcePath, $tempPath, $opts)) {
                    throw ImageProcessorException::processingFailed('Không thể thêm watermark');
                }
            } catch (Exception $e) {
                if (file_exists($tempPath) && $tempPath !== $sourcePath) {
                    @unlink($tempPath);
                }
                throw $e;
            }
        }

        // Tối ưu trước khi convert
        if ($opts['optimize']) {
            $this->optimizeImage($tempPath, $format);
        }

        // Chuyển WebP nếu yêu cầu
        if (!empty($opts['webp']['enabled'])) {
            $webpPath = $this->convertToWebP($tempPath, (int)$opts['quality']);
            if ($webpPath) {
                copy($webpPath, $destinationPath);
                @unlink($webpPath);
                if ($tempPath !== $sourcePath) {
                    @unlink($tempPath);
                }
                return true;
            }
        }

        // Sao chép file gốc
        $result = copy($tempPath, $destinationPath);
        if ($tempPath !== $sourcePath) {
            @unlink($tempPath);
        }

        return $result;
    }

    /**
     * Tối ưu ảnh theo loại đuôi sử dụng binary tương ứng.
     */
    private function optimizeImage(string $path, string $format): void
    {
        $toolMap = [
            'jpg'  => 'jpegtran',
            'jpeg' => 'jpegtran',
            'png'  => 'optipng',
            'gif'  => 'gifsicle',
        ];
        if (!isset($toolMap[$format])) {
            return;
        }

        $tool = $toolMap[$format];
        $cmd  = match ($tool) {
            'jpegtran' => sprintf(
                '%s -copy all -optimize -progressive -outfile %s %s',
                escapeshellarg($this->binaries[$tool]),
                escapeshellarg($path . '.tmp'),
                escapeshellarg($path)
            ),
            'optipng'  => sprintf(
                '%s -o2 -strip all -out %s %s',
                escapeshellarg($this->binaries[$tool]),
                escapeshellarg($path . '.tmp'),
                escapeshellarg($path)
            ),
            'gifsicle' => sprintf(
                '%s -O2 --colors 256 %s -o %s',
                escapeshellarg($this->binaries[$tool]),
                escapeshellarg($path),
                escapeshellarg($path . '.tmp')
            ),
            default    => ''
        };

        if ($cmd === '') {
            return;
        }
        @exec($cmd, $_, $ret);
        $tmp = $path . '.tmp';
        if ($ret === 0 && file_exists($tmp)) {
            @unlink($path);
            rename($tmp, $path);
        } elseif (file_exists($tmp)) {
            @unlink($tmp);
        }
    }

    /**
     * Chuyển đổi sang WebP.
     */
    private function convertToWebP(string $source, int $quality): ?string
    {
        $dst = $source . '.webp';
        $opts = [
            '-q ' . max(0, min(100, $quality)),
            '-m 4', // Mức nén vừa phải
            '-f 2', // Bộ lọc vừa phải
            '-sharpness 2', // Độ sắc nét vừa phải
            '-mt', // Sử dụng nhiều thread
            '-af', // Tự động điều chỉnh bộ lọc
            '-alpha_q 100', // Chất lượng alpha channel
            '-alpha_filter best', // Bộ lọc alpha tốt nhất
            '-exact', // Giữ nguyên màu sắc
            '-pass 4', // Số lần pass tối ưu
            '-pre 2', // Mức độ dự đoán
            '-sns 50', // Độ mạnh của bộ lọc không gian
            '-strong', // Sử dụng bộ lọc mạnh
            '-quiet' // Không hiển thị thông tin
        ];
        $cmd = sprintf(
            '%s %s %s -o %s',
            escapeshellarg($this->binaries['cwebp']),
            implode(' ', $opts),
            escapeshellarg($source),
            escapeshellarg($dst)
        );
        @exec($cmd, $_, $ret);
        return $ret === 0 && file_exists($dst) ? $dst : null;
    }
}
