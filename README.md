# 📸 iMagify - Thư viện xử lý ảnh PHP mạnh mẽ

**iMagify** là một thư viện PHP giúp xử lý ảnh hiệu quả cho các ứng dụng web hoặc hệ thống nội bộ. Thư viện hỗ trợ các chức năng như thêm watermark, resize ảnh, tối ưu hóa dung lượng và chuyển đổi định dạng sang WebP. Được thiết kế linh hoạt với khả năng sử dụng các công cụ nhị phân (qua `exec()`) hoặc fallback về GD nếu cần.

---

## 🚀 Tính năng nổi bật

- ✅ **Thêm watermark**: hỗ trợ nhiều vị trí, độ mờ, tỉ lệ, khoảng cách.
- 🖼️ **Resize ảnh**: cho nhiều kích thước tùy biến.
- 📦 **Tối ưu hóa ảnh**: giảm dung lượng ảnh bằng các công cụ như `jpegtran`, `optipng`, `pngquant`, `gifsicle`.
- 🌐 **Chuyển đổi sang WebP**: sử dụng `cwebp` hoặc GD WebP.
- 🔄 **Xử lý hàng loạt**: với `BatchProcessor`.
- 🧠 **Tự động fallback**: sử dụng thư viện GD khi môi trường không hỗ trợ `exec()`.
- 🔧 **Tùy biến linh hoạt**: qua các tùy chọn cấu hình đa dạng.

---

## 📦 Cài đặt

```bash
composer require xoixoi/imagify
```

---

## 🔧 Yêu cầu hệ thống

- PHP >= 7.4
- GD Library (nếu không sử dụng exec)
- Hệ điều hành hỗ trợ thực thi `exec()` (Linux, macOS, Windows)

---

## 🛠️ Hướng dẫn sử dụng

### ✅ Xử lý một ảnh

```php
use Xoixoi\IMagify\ImageProcessor;

$processor = new ImageProcessor([
    'enable_watermark' => true,
    'watermark_image' => '/path/to/watermark.png',
    'watermark_position' => 'bottom-right',
    'watermark_opacity' => 80,
    'enable_webp' => true,
    'webp_quality' => 80
]);

$result = $processor->process(
    '/path/to/source.jpg',
    '/path/to/destination.jpg',
    [
        ['name' => 'thumbnail', 'width' => 150, 'height' => 150],
        ['name' => 'medium', 'width' => 300, 'height' => 300]
    ]
);
```

### 🔁 Xử lý nhiều ảnh

```php
use Xoixoi\IMagify\BatchProcessor;

$batch = new BatchProcessor($processor);

// Xử lý danh sách ảnh
$results = $batch->process([
    '/path/to/image1.jpg',
    '/path/to/image2.png'
], '/path/to/output');

// Hoặc toàn bộ thư mục
$results = $batch->processDirectory(
    '/path/to/source/dir',
    '/path/to/output/dir',
    [['name' => 'thumbnail', 'width' => 150, 'height' => 150]]
);
```

---

## ⚙️ Danh sách tùy chọn `ImageProcessor`

| Tùy chọn              | Mô tả                                                                 | Mặc định       |
|-----------------------|----------------------------------------------------------------------|----------------|
| enable_watermark      | Bật/tắt tính năng watermark                                          | `false`        |
| enable_optimize       | Bật/tắt tối ưu hóa dung lượng ảnh                                    | `true`         |
| enable_webp           | Bật/tắt chuyển đổi sang WebP                                         | `true`         |
| watermark_image       | Đường dẫn đến ảnh watermark                                          | `''`           |
| watermark_position    | Vị trí watermark: `top-left`, `top-right`, `bottom-left`,...         | `bottom-right` |
| watermark_opacity     | Độ trong suốt của watermark (0-100)                                  | `80`           |
| watermark_scale       | Tỷ lệ kích thước watermark (%)                                       | `20`           |
| watermark_margin      | Khoảng cách watermark so với viền                                    | `10`           |
| watermark_margin_unit | Đơn vị khoảng cách: `px`, `%`                                        | `px`           |
| webp_quality          | Chất lượng ảnh WebP (0-100)                                          | `80`           |
| exclude_keywords      | Các từ khóa loại trừ (dùng khi batch xử lý)                          | `''`           |
| sizes_to_convert      | Danh sách kích thước cần xử lý                                       | `['FULL']`     |
| sizes_to_watermark    | Danh sách kích thước cần watermark                                   | `['FULL']`     |

---

## 🗂 Cấu trúc thư mục dự án

```
iMagify/
├── composer.json                  # Cấu hình autoload & dependency Composer
├── README.md                      # Tài liệu sử dụng
├── src/
│   ├── ImageProcessor.php         # Xử lý chính: resize, watermark, convert, optimize
│   ├── ImageProcessorOptions.php  # Quản lý các tùy chọn cấu hình xử lý ảnh
│   ├── bin/                       # Thư mục chứa binary tương ứng theo hệ điều hành
│   │   └── cwebp-*, jpegtran-*, gifsicle-*, optipng-*, pngquant-*  (Windows, Linux, macOS)
│   ├── Driver/
│   │   ├── BinaryDriver.php       # Gọi binary thực thi bằng `exec()`
│   │   ├── DriverInterface.php    # Interface chuẩn cho các driver ảnh
│   │   └── GDDriver.php           # Driver fallback dùng thư viện GD
│   ├── Exception/
│   │   └── ImageProcessorException.php  # Xử lý lỗi tùy biến
│   ├── lib/
│   │   ├── iMagify.php            # Wrapper thư viện chính
│   │   ├── wwc-image-converter.php  # Chuyển đổi định dạng nội bộ
│   │   └── wwc-watermark.php      # Thêm watermark vào ảnh
│   ├── Watermark/
│   │   ├── Watermark.php          # Lớp xử lý watermark
│   │   └── WatermarkInterface.php # Interface cho watermark engine
```

---

## 📄 Giấy phép

**MIT License** — Tự do sử dụng, sửa đổi, phân phối.

---

## 🤝 Đóng góp

Thư viện hiện đang phát triển nội bộ, nếu bạn quan tâm đến việc đóng góp hoặc sử dụng phiên bản public, vui lòng theo dõi bản phát hành tiếp theo hoặc liên hệ trực tiếp.
