<?php
namespace Xoixoi\IMagify\Internal;
/**
 * iMagify Library
 *
 * Supports multi-tasking image processing:
 *  - Add watermark (with optional position and padding)
 *  - Crop by specific size or ratio (center crop)
 *  - Resize image (respect aspect ratio)
 *  - Convert format and change quality
 *  - Save image from base64 string to file
 *
 * Security checks:
 *  - Check input file via getimagesize to ensure valid image
 *  - Only allow safe image formats: jpg/jpeg, png, gif, webp
 *  - Process base64 string by removing unnecessary headers
 *
 * Requirements: PHP with GD Library (with webp support if available)
 */
class iMagify
{
    private $image;   // Resource ảnh GD
    private $width;
    private $height;
    private $type;    // jpg, png, gif, webp

    /* ------------------------------
       Static instance creation methods
       ------------------------------ */

    // Load ảnh từ file (với kiểm tra MIME hợp lệ)
    public static function load($filename)
    {
        $instance = new self();
        $instance->loadImage($filename);
        return $instance;
    }

    // Load ảnh từ chuỗi Base64
    public static function loadFromBase64($base64String)
    {
        $instance = new self();
        // Loại bỏ header nếu có (vd: data:image/png;base64,)
        $base64String = preg_replace('#^data:image/\w+;base64,#i', '', $base64String);
        $data = base64_decode($base64String);
        if ($data === false) {
            throw new \Exception("Base64 decode không thành công.");
        }
        $instance->image = imagecreatefromstring($data);
        if (!$instance->image) {
            throw new \Exception("Không thể tạo ảnh từ chuỗi Base64.");
        }
        $instance->width = imagesx($instance->image);
        $instance->height = imagesy($instance->image);
        // Nếu không thể xác định loại, đặt mặc định là jpg
        $instance->type = 'jpg';
        return $instance;
    }

    /* ------------------------------
       Internal image loading from file
       ------------------------------ */
    private function loadImage($filename)
    {
        if (!file_exists($filename)) {
            throw new \Exception("File không tồn tại: $filename");
        }
        // Sử dụng getimagesize để kiểm tra an toàn
        $info = getimagesize($filename);
        if (!$info) {
            throw new \Exception("File không phải ảnh hợp lệ: $filename");
        }
        $mime = $info['mime'];
        // Chỉ cho phép các mime an toàn
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($mime, $allowed)) {
            throw new \Exception("Định dạng ảnh không được phép: $mime");
        }
        switch ($mime) {
            case 'image/jpeg':
                $this->image = imagecreatefromjpeg($filename);
                $this->type = 'jpg';
                break;
            case 'image/png':
                $this->image = imagecreatefrompng($filename);
                $this->type = 'png';
                break;
            case 'image/gif':
                $this->image = imagecreatefromgif($filename);
                $this->type = 'gif';
                break;
            case 'image/webp':
                if (function_exists('imagecreatefromwebp')) {
                    $this->image = imagecreatefromwebp($filename);
                    $this->type = 'webp';
                } else {
                    throw new \Exception("Hỗ trợ webp không khả dụng.");
                }
                break;
            default:
                throw new \Exception("Định dạng ảnh không được hỗ trợ: $mime");
        }
        if (!$this->image) {
            throw new \Exception("Không thể load ảnh từ file: $filename");
        }
        $this->width = imagesx($this->image);
        $this->height = imagesy($this->image);
    }

    /* ------------------------------
       Watermark processing
       ------------------------------ */
    /**
     * Add watermark to $this->image (GD resource) and return the object
     *
     * @param string $watermarkFile   Watermark file path
     * @param string $position        top-left | top-right | bottom-left | bottom-right | center
     * @param int    $padding         Edge distance (px)
     * @param int    $opacity         0-100 (100 = original display)
     * @return static
     * @throws \Exception
     */
    public function addWatermark(
        string $watermarkFile,
        string $position = 'bottom-right',
        int    $padding  = 10,
        int    $opacity  = 100
    ) {
        /* ---------- 1. Nạp watermark an toàn ---------- */
        $info = @getimagesize($watermarkFile);
        if (!$info) {
            throw new \Exception("Watermark không phải ảnh hợp lệ: $watermarkFile");
        }
        $mime     = $info['mime'];
        $allowed  = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($mime, $allowed, true)) {
            throw new \Exception("Định dạng watermark không hỗ trợ: $mime");
        }

        switch ($mime) {
            case 'image/jpeg':
                $wm = imagecreatefromjpeg($watermarkFile);
                break;
            case 'image/png':
                $wm = imagecreatefrompng($watermarkFile);
                break;
            case 'image/gif':
                $wm = imagecreatefromgif($watermarkFile);
                break;
            case 'image/webp':
                if (!function_exists('imagecreatefromwebp')) {
                    throw new \Exception('Máy chủ chưa biên dịch WebP cho GD');
                }
                $wm = imagecreatefromwebp($watermarkFile);
                break;
        }
        if (!$wm) {
            throw new \Exception("Không thể load watermark: $watermarkFile");
        }

        /* ---------- 2. Tính vị trí ---------- */
        $wmW = imagesx($wm);
        $wmH = imagesy($wm);

        switch ($position) {
            case 'top-left':
                $destX = $padding;
                $destY = $padding;
                break;
            case 'top-right':
                $destX = $this->width  - $wmW - $padding;
                $destY = $padding;
                break;
            case 'bottom-left':
                $destX = $padding;
                $destY = $this->height - $wmH - $padding;
                break;
            case 'center':
                $destX = (int) round(($this->width  - $wmW) / 2);
                $destY = (int) round(($this->height - $wmH) / 2);
                break;
            default: // bottom-right
                $destX = $this->width  - $wmW - $padding;
                $destY = $this->height - $wmH - $padding;
        }

        /* ---------- 3. Bật alpha cho ảnh gốc ---------- */
        imagealphablending($this->image, true);
        imagesavealpha($this->image, true);

        /* ---------- 4. Phối watermark ---------- */
        $hasAlpha   = in_array($mime, ['image/png', 'image/webp', 'image/gif'], true);
        $opacity    = max(0, min(100, $opacity));            // clamp 0-100

        if ($opacity >= 100) {
            // Giữ nguyên watermark
            imagecopy($this->image, $wm, $destX, $destY, 0, 0, $wmW, $wmH);
        } elseif ($hasAlpha) {
            /* ---- 4A. PNG/WebP/GIF: giảm trong suốt *đúng cách* ---- */
            // Tạo layer tạm trong suốt
            $tmp = imagecreatetruecolor($wmW, $wmH);
            imagealphablending($tmp, false);
            imagesavealpha($tmp, true);

            // Lấp đầy nền trong suốt
            $transparent = imagecolorallocatealpha($tmp, 0, 0, 0, 127);
            imagefill($tmp, 0, 0, $transparent);

            // Sao chép watermark gốc
            imagecopy($tmp, $wm, 0, 0, 0, 0, $wmW, $wmH);

            // Tính mức alpha mới (0 = trong suốt hoàn toàn, 127 = đục)
            $alpha = 127 - (int) round(127 * ($opacity / 100));

            // Giảm độ trong suốt đồng đều
            imagefilter($tmp, IMG_FILTER_COLORIZE, 0, 0, 0, $alpha);

            // Dán lên ảnh chính
            imagecopy($this->image, $tmp, $destX, $destY, 0, 0, $wmW, $wmH);
            imagedestroy($tmp);
        } else {
            /* ---- 4B. JPEG: không có alpha, dùng imagecopymerge ---- */
            imagecopymerge($this->image, $wm, $destX, $destY, 0, 0, $wmW, $wmH, $opacity);
        }

        /* ---------- 5. Dọn dẹp ---------- */
        imagedestroy($wm);
        return $this;
    }


    /* ------------------------------
       Crop ảnh
       ------------------------------ */
    public function crop($x, $y, $cropWidth, $cropHeight)
    {
        // Đảm bảo các giá trị crop là số nguyên
        $x = (int) round($x);
        $y = (int) round($y);
        $cropWidth = (int) round($cropWidth);
        $cropHeight = (int) round($cropHeight);

        $newImg = imagecreatetruecolor($cropWidth, $cropHeight);
        if (in_array($this->type, ['png', 'gif'])) {
            imagecolortransparent($newImg, imagecolorallocatealpha($newImg, 0, 0, 0, 127));
            imagealphablending($newImg, false);
            imagesavealpha($newImg, true);
        }
        imagecopy($newImg, $this->image, 0, 0, $x, $y, $cropWidth, $cropHeight);
        imagedestroy($this->image);
        $this->image = $newImg;
        $this->width = $cropWidth;
        $this->height = $cropHeight;
        return $this;
    }

    // Crop ảnh theo tỷ lệ (cắt trung tâm)
    public function cropByRatio($ratioWidth, $ratioHeight)
    {
        $targetRatio = $ratioWidth / $ratioHeight;
        $currentRatio = $this->width / $this->height;
        if ($currentRatio > $targetRatio) {
            $newWidth = (int) round($this->height * $targetRatio);
            $x = (int) round(($this->width - $newWidth) / 2);
            return $this->crop($x, 0, $newWidth, $this->height);
        } else {
            $newHeight = (int) round($this->width / $targetRatio);
            $y = (int) round(($this->height - $newHeight) / 2);
            return $this->crop(0, $y, $this->width, $newHeight);
        }
    }

    /* =========================================================
    Resize ảnh
    ---------------------------------------------------------
    $dstW, $dstH        : kích thước đích
    $maintainAspect=true: giữ tỉ lệ (contain / cover)
    $cover=false        : false = contain (vừa khít hộp)
                            true  = cover  (phủ kín rồi crop)
    =======================================================*/
    public function resize(
        int  $dstW,
        int  $dstH,
        bool $maintainAspect = true,
        bool $cover          = false
    ) {
        /* 1. Không giữ tỉ lệ  → scale thẳng */
        if (!$maintainAspect) {
            return $this->_resampleTo($dstW, $dstH);
        }

        /* 2. Giữ tỉ lệ: tính factor scale */
        $ratioW = $dstW / $this->width;
        $ratioH = $dstH / $this->height;
        $scale  = $cover ? max($ratioW, $ratioH)    // cover  : phủ kín
            : min($ratioW, $ratioH);   // contain: vừa khít

        $scaledW = (int) round($this->width  * $scale);
        $scaledH = (int) round($this->height * $scale);

        /* Bước I – scale */
        $scaled = $this->_createBlank($scaledW, $scaledH);
        imagecopyresampled(
            $scaled,
            $this->image,
            0,
            0,
            0,
            0,
            $scaledW,
            $scaledH,
            $this->width,
            $this->height
        );

        /* Bước II – nếu contain: cập nhật & kết thúc */
        if (!$cover) {
            imagedestroy($this->image);
            $this->image  = $scaled;
            $this->width  = $scaledW;
            $this->height = $scaledH;
            return $this;
        }

        /* Bước III – cover: crop trung tâm về kích thước đích */
        $cropX = (int) floor(($scaledW - $dstW) / 2);
        $cropY = (int) floor(($scaledH - $dstH) / 2);

        $final = $this->_createBlank($dstW, $dstH);
        imagecopy(
            $final,
            $scaled,
            0,
            0,            // to (0,0)
            $cropX,
            $cropY,  // from
            $dstW,
            $dstH
        );

        // cập nhật state
        imagedestroy($this->image);
        imagedestroy($scaled);
        $this->image  = $final;
        $this->width  = $dstW;
        $this->height = $dstH;
        return $this;
    }

    /* =========================================================
    * Helper: tạo canvas trống, hỗ trợ alpha PNG/GIF
    * =======================================================*/
    private function _createBlank(int $w, int $h)
    {
        $img = imagecreatetruecolor($w, $h);
        if (in_array($this->type, ['png', 'gif'], true)) {
            imagealphablending($img, false);
            imagesavealpha($img, true);
            $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
            imagefill($img, 0, 0, $transparent);
        }
        return $img;
    }

    /* =========================================================
    * Helper: scale "raw" không giữ tỉ lệ
    * =======================================================*/
    private function _resampleTo(int $w, int $h)
    {
        $dst = $this->_createBlank($w, $h);
        imagecopyresampled(
            $dst,
            $this->image,
            0,
            0,
            0,
            0,
            $w,
            $h,
            $this->width,
            $this->height
        );
        imagedestroy($this->image);
        $this->image  = $dst;
        $this->width  = $w;
        $this->height = $h;
        return $this;
    }

    /* ------------------------------
       Convert image format
       ------------------------------ */
    public function convert($format, $quality = 90)
    {
        $format = strtolower($format);
        if (!in_array($format, ['jpg', 'png', 'gif', 'webp'])) {
            throw new \Exception("Định dạng không được hỗ trợ: $format");
        }
        $this->type = $format;
        return $this;
    }

    /* ------------------------------
       Save image to file
       ------------------------------ */
    public function save($destination, $quality = 90)
    {
        $saved = false;
        switch ($this->type) {
            case 'jpg':
            case 'jpeg':
                $saved = imagejpeg($this->image, $destination, $quality);
                break;
            case 'png':
                $pngQuality = round((100 - $quality) / 10);
                $saved = imagepng($this->image, $destination, $pngQuality);
                break;
            case 'gif':
                $saved = imagegif($this->image, $destination);
                break;
            case 'webp':
                if (function_exists('imagewebp')) {
                    $saved = imagewebp($this->image, $destination, $quality);
                } else {
                    throw new \Exception("Hỗ trợ webp không khả dụng.");
                }
                break;
            default:
                throw new \Exception("Định dạng lưu không được hỗ trợ: {$this->type}");
        }
        return $saved;
    }

    /* ------------------------------
       Output to Base64
       ------------------------------ */
    public function output($format = null, $quality = 90)
    {
        ob_start();
        $fmt = $format ? strtolower($format) : $this->type;
        switch ($fmt) {
            case 'jpg':
            case 'jpeg':
                imagejpeg($this->image, null, $quality);
                break;
            case 'png':
                $pngQuality = round((100 - $quality) / 10);
                imagepng($this->image, null, $pngQuality);
                break;
            case 'gif':
                imagegif($this->image);
                break;
            case 'webp':
                if (function_exists('imagewebp')) {
                    imagewebp($this->image, null, $quality);
                } else {
                    throw new \Exception("Hỗ trợ webp không khả dụng.");
                }
                break;
            default:
                throw new \Exception("Định dạng output không được hỗ trợ: $fmt");
        }
        $data = ob_get_contents();
        ob_end_clean();
        return 'data:image/' . $fmt . ';base64,' . base64_encode($data);
    }

    /* ------------------------------
       Memory cleanup
       ------------------------------ */
    public function destroy()
    {
        if ($this->image) {
            imagedestroy($this->image);
        }
    }


    // xoiupdate cho crawl image
    // Phương thức loadFromContent cần được bổ sung vào iMagify, ví dụ:
    public static function loadFromContent($content)
    {
        $instance = new self();
        $instance->image = imagecreatefromstring($content);
        if (!$instance->image) {
            throw new \Exception("Không thể tạo ảnh từ dữ liệu tải về");
        }
        $instance->width = imagesx($instance->image);
        $instance->height = imagesy($instance->image);
        $instance->type = 'jpg'; // mặc định, hoặc có thể kiểm tra MIME
        return $instance;
    }

    public function getWidth()
    {
        return $this->width;
    }

    public function getHeight()
    {
        return $this->height;
    }
}
