<?php
// long/File.php
// LongPHP Framework - 文件操作与上传类
// 支持：文件上传、图片处理、缩略图、iOS方向修正、文件操作

namespace Long;

class File
{
    // ─────────────────────────────────────────────────────────────
    // 上传相关属性
    // ─────────────────────────────────────────────────────────────

    /**
     * 上传文件信息
     * @var array
     */
    private $file = [];

    /**
     * 允许的文件类型
     * @var array
     */
    private $allowTypes = [];

    /**
     * 允许的最大文件大小（字节）
     * @var int
     */
    private $maxSize = 0;

    /**
     * 错误信息
     * @var string
     */
    private $error = '';

    // ─────────────────────────────────────────────────────────────
    // 构造函数
    // ─────────────────────────────────────────────────────────────

    public function __construct($file = null)
    {
        if ($file) {
            $this->file = $file;
        }
    }

    // ─────────────────────────────────────────────────────────────
    // 上传文件获取
    // ─────────────────────────────────────────────────────────────

    /**
     * 从 $_FILES 获取上传文件
     * @param string $field 表单字段名
     * @return static|null
     */
    public static function upload($field)
    {
        if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        return new static($_FILES[$field]);
    }

    /**
     * 获取所有上传文件
     * @return array
     */
    public static function all()
    {
        $files = [];
        foreach ($_FILES as $field => $file) {
            // 多文件上传 (如 name="images[]")
            if (is_array($file['name'])) {
                $count = count($file['name']);
                for ($i = 0; $i < $count; $i++) {
                    if ($file['error'][$i] === UPLOAD_ERR_NO_FILE) {
                        continue;
                    }
                    $files[] = new static([
                        'name'     => $file['name'][$i],
                        'type'     => $file['type'][$i],
                        'tmp_name' => $file['tmp_name'][$i],
                        'error'    => $file['error'][$i],
                        'size'     => $file['size'][$i],
                    ]);
                }
            } else {
                // 单文件上传
                if ($file['error'] !== UPLOAD_ERR_NO_FILE) {
                    $files[] = new static($file);
                }
            }
        }
        return $files;
    }

    // ─────────────────────────────────────────────────────────────
    // 上传验证
    // ─────────────────────────────────────────────────────────────

    /**
     * 设置允许的文件类型
     * @param array $types 文件扩展名数组
     * @return $this
     */
    public function allowTypes($types)
    {
        $this->allowTypes = array_map('strtolower', $types);
        return $this;
    }

    /**
     * 设置最大文件大小
     * @param int $size 字节数
     * @return $this
     */
    public function maxSize($size)
    {
        $this->maxSize = $size;
        return $this;
    }

    /**
     * 验证文件
     * @return bool
     */
    public function validate()
    {
        if ($this->file['error'] !== UPLOAD_ERR_OK) {
            $this->error = $this->getUploadError($this->file['error']);
            return false;
        }

        if ($this->maxSize > 0 && $this->file['size'] > $this->maxSize) {
            $this->error = '文件大小超过限制（最大 ' . static::formatSize($this->maxSize) . '）';
            return false;
        }

        if (!empty($this->allowTypes)) {
            $ext = strtolower(pathinfo($this->file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $this->allowTypes)) {
                $this->error = '不允许的文件类型: ' . $ext;
                return false;
            }
        }

        // ✅ 检查文件内容是否为真实的图片
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'])) {
            if (!getimagesize($this->file['tmp_name'])) {
                $this->error = '无效的图片文件（可能包含恶意代码）';
                return false;
            }
        }

        // ✅ 检查文件 MIME 类型
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $this->file['tmp_name']);
        finfo_close($finfo);
        
        $allowedMimes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf',
            'zip' => 'application/zip',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];
        
        if (isset($allowedMimes[$ext]) && $mimeType !== $allowedMimes[$ext]) {
            $this->error = '文件 MIME 类型不匹配: ' . $mimeType;
            return false;
        }

        return true;
    }

    // ─────────────────────────────────────────────────────────────
    // 文件保存
    // ─────────────────────────────────────────────────────────────

    /**
     * 保存上传文件
     * @param string $path 保存目录
     * @param string|null $name 文件名（不含扩展名）
     * @return string|false 返回保存的文件名
     */
    public function save($path, $name = null)
    {
        if (!$this->validate()) {
            return false;
        }

        $this->ensureDirectory($path);

        $ext = pathinfo($this->file['name'], PATHINFO_EXTENSION);
        $name = $name ?: date('YmdHis') . '_' . uniqid();
        $filename = $name . '.' . $ext;

        $savePath = rtrim($path, '/') . '/' . $filename;
        if (move_uploaded_file($this->file['tmp_name'], $savePath)) {
            return $filename;
        }

        $this->error = '文件保存失败';
        return false;
    }

    /**
     * 保存图片（自动生成缩略图，等比例缩放+居中裁剪）
     * @param string $path 保存目录
     * @param array $thumbSizes 缩略图尺寸 ['thumb' => ['width' => 200, 'height' => 200]]
     * @param string|null $name 文件名
     * @return array|false
     */
    public function saveImage($path, $thumbSizes = [], $name = null)
    {
        if (!$this->validate()) {
            return false;
        }

        $imageInfo = getimagesize($this->file['tmp_name']);
        if (!$imageInfo) {
            $this->error = '无效的图片文件';
            return false;
        }

        list($width, $height, $type) = $imageInfo;
        $this->ensureDirectory($path);

        $ext = strtolower(pathinfo($this->file['name'], PATHINFO_EXTENSION));
        $name = $name ?: date('YmdHis') . '_' . uniqid();
        $filename = $name . '.' . $ext;

        $result = [
            'name'   => $filename,
            'path'   => $path . '/' . $filename,
            'width'  => $width,
            'height' => $height,
            'size'   => $this->file['size'],
            'thumbs' => []
        ];

        // 保存原图（修复方向后）
        $savePath = rtrim($path, '/') . '/' . $filename;
        if (!$this->saveFixedImage($savePath, $ext)) {
            $this->error = '图片保存失败';
            return false;
        }

        // 生成缩略图
        if (!empty($thumbSizes)) {
            $result['thumbs'] = $this->createThumbnails($savePath, $path, $name, $ext, $thumbSizes);
        }

        return $result;
    }

    // ─────────────────────────────────────────────────────────────
    // 图片方向修复（iOS 倒立问题）
    // ─────────────────────────────────────────────────────────────

    /**
     * 修复图片方向（根据 EXIF 信息旋转）
     * @param resource $image 图片资源
     * @param string $filePath 图片文件路径
     * @return resource
     */
    private function fixOrientation($image, $filePath)
    {
        if (!function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($filePath);
        if (!$exif || !isset($exif['Orientation'])) {
            return $image;
        }

        $orientation = $exif['Orientation'];

        switch ($orientation) {
            case 3:  $image = imagerotate($image, 180, 0); break;
            case 6:  $image = imagerotate($image, -90, 0); break;
            case 8:  $image = imagerotate($image, 90, 0); break;
            case 2:  imageflip($image, IMG_FLIP_HORIZONTAL); break;
            case 4:  imageflip($image, IMG_FLIP_VERTICAL); break;
            case 5:  imageflip($image, IMG_FLIP_HORIZONTAL); $image = imagerotate($image, 90, 0); break;
            case 7:  imageflip($image, IMG_FLIP_HORIZONTAL); $image = imagerotate($image, -90, 0); break;
            default: break;
        }

        return $image;
    }

    /**
     * 保存修复方向后的图片
     */
    private function saveFixedImage($savePath, $ext)
    {
        $image = $this->createImageResource($this->file['tmp_name'], $ext);
        if (!$image) {
            return false;
        }

        $this->saveImageResource($image, $savePath, $ext);
        imagedestroy($image);
        return true;
    }

    // ─────────────────────────────────────────────────────────────
    // 缩略图生成（等比例缩放 + 居中裁剪）
    // ─────────────────────────────────────────────────────────────

    /**
     * 创建缩略图（cover 模式）
     */
    private function createThumbnails($sourcePath, $savePath, $name, $ext, $sizes)
    {
        $thumbs = [];
        $source = $this->createImageResource($sourcePath, $ext);
        if (!$source) {
            return $thumbs;
        }

        list($srcWidth, $srcHeight) = getimagesize($sourcePath);

        foreach ($sizes as $key => $size) {
            $targetWidth  = $size['width'] ?? 0;
            $targetHeight = $size['height'] ?? 0;

            if ($targetWidth <= 0 && $targetHeight <= 0) {
                continue;
            }

            // 等比例缩放（cover 模式：以小的为最大）
            $scale = max($targetWidth / $srcWidth, $targetHeight / $srcHeight);

            $newWidth  = (int)($srcWidth * $scale);
            $newHeight = (int)($srcHeight * $scale);

            // 创建目标画布
            $thumb = imagecreatetruecolor($targetWidth, $targetHeight);

            // 透明背景（PNG/GIF）
            if (in_array($ext, ['png', 'gif'])) {
                imagealphablending($thumb, false);
                imagesavealpha($thumb, true);
                $transparent = imagecolorallocatealpha($thumb, 255, 255, 255, 127);
                imagefilledrectangle($thumb, 0, 0, $targetWidth, $targetHeight, $transparent);
            } else {
                $white = imagecolorallocate($thumb, 255, 255, 255);
                imagefilledrectangle($thumb, 0, 0, $targetWidth, $targetHeight, $white);
            }

            // 等比例缩放
            $tempImage = imagecreatetruecolor($newWidth, $newHeight);
            if (in_array($ext, ['png', 'gif'])) {
                imagealphablending($tempImage, false);
                imagesavealpha($tempImage, true);
            }
            imagecopyresampled($tempImage, $source, 0, 0, 0, 0, $newWidth, $newHeight, $srcWidth, $srcHeight);

            // 居中裁剪
            $cropX = (int)(($newWidth - $targetWidth) / 2);
            $cropY = (int)(($newHeight - $targetHeight) / 2);

            imagecopyresampled(
                $thumb, $tempImage,
                0, 0,
                $cropX, $cropY,
                $targetWidth, $targetHeight,
                $targetWidth, $targetHeight
            );

            imagedestroy($tempImage);

            // 保存缩略图
            $thumbName = $name . '_' . $key . '.' . $ext;
            $thumbPath = rtrim($savePath, '/') . '/' . $thumbName;
            $this->saveImageResource($thumb, $thumbPath, $ext);
            imagedestroy($thumb);

            $thumbs[$key] = [
                'name'   => $thumbName,
                'path'   => $thumbPath,
                'width'  => $targetWidth,
                'height' => $targetHeight
            ];
        }

        imagedestroy($source);
        return $thumbs;
    }

    // ─────────────────────────────────────────────────────────────
    // 图片处理辅助方法
    // ─────────────────────────────────────────────────────────────

    /**
     * 创建图片资源（自动修复方向）
     */
    private function createImageResource($path, $ext)
    {
        $image = false;

        switch ($ext) {
            case 'jpg':
            case 'jpeg':
                $image = imagecreatefromjpeg($path);
                break;
            case 'png':
                $image = imagecreatefrompng($path);
                break;
            case 'gif':
                $image = imagecreatefromgif($path);
                break;
            case 'webp':
                $image = imagecreatefromwebp($path);
                break;
            default:
                return false;
        }

        // iOS 方向修正
        if ($image && in_array($ext, ['jpg', 'jpeg'])) {
            $image = $this->fixOrientation($image, $path);
        }

        return $image;
    }

    /**
     * 保存图片资源
     */
    private function saveImageResource($resource, $path, $ext)
    {
        switch ($ext) {
            case 'jpg':
            case 'jpeg':
                imagejpeg($resource, $path, 85);
                break;
            case 'png':
                imagepng($resource, $path, 9);
                break;
            case 'gif':
                imagegif($resource, $path);
                break;
            case 'webp':
                imagewebp($resource, $path, 85);
                break;
        }
    }

    // ─────────────────────────────────────────────────────────────
    // 文件信息
    // ─────────────────────────────────────────────────────────────

    /**
     * 获取文件扩展名
     */
    public function getExtension()
    {
        return strtolower(pathinfo($this->file['name'], PATHINFO_EXTENSION));
    }

    /**
     * 获取文件大小
     */
    public function getSize()
    {
        return $this->file['size'] ?? 0;
    }

    /**
     * 获取原始文件名
     */
    public function getOriginalName()
    {
        return $this->file['name'] ?? '';
    }

    /**
     * 获取错误信息
     */
    public function getError()
    {
        return $this->error;
    }

    // ─────────────────────────────────────────────────────────────
    // 静态文件操作
    // ─────────────────────────────────────────────────────────────

    /**
     * 写入文件
     */
    public static function put($path, $content, $append = false)
    {
        self::ensureDirectory(dirname($path));
        $flags = $append ? FILE_APPEND : 0;
        return file_put_contents($path, $content, $flags);
    }

    /**
     * 读取文件
     */
    public static function get($path)
    {
        if (!file_exists($path)) {
            return false;
        }
        return file_get_contents($path);
    }

    /**
     * 追加内容到文件
     */
    public static function append($path, $content)
    {
        return self::put($path, $content, true);
    }

    /**
     * 删除文件（支持批量）
     */
    public static function delete($path)
    {
        // ✅ 安全检查：防止目录遍历
        $realPath = realpath($path);
        $allowedDir = realpath(ROOT_PATH . '/public/uploads/');
        
        if ($realPath === false || strpos($realPath, $allowedDir) !== 0) {
            throw new \Exception('非法文件操作');
        }

        if (is_array($path)) {
            $success = true;
            foreach ($path as $p) {
                if (!self::delete($p)) {
                    $success = false;
                }
            }
            return $success;
        }

        if (!file_exists($path)) {
            return true;
        }

        if (is_dir($path)) {
            return self::rmdir($path);
        }

        return unlink($path);
    }

    /**
     * 移动文件
     */
    public static function move($from, $to)
    {
        if (!file_exists($from)) {
            return false;
        }
        self::ensureDirectory(dirname($to));
        return rename($from, $to);
    }

    /**
     * 复制文件
     */
    public static function copy($from, $to)
    {
        if (!file_exists($from)) {
            return false;
        }
        self::ensureDirectory(dirname($to));
        return copy($from, $to);
    }

    /**
     * 检查文件是否存在
     */
    public static function exists($path)
    {
        return file_exists($path);
    }

    /**
     * 获取文件大小（字节）
     */
    public static function size($path)
    {
        if (!file_exists($path)) {
            return false;
        }
        return filesize($path);
    }

    /**
     * 获取文件扩展名
     */
    public static function extension($path)
    {
        return strtolower(pathinfo($path, PATHINFO_EXTENSION));
    }

    /**
     * 获取文件 MIME 类型
     */
    public static function mime($path)
    {
        if (!file_exists($path)) {
            return false;
        }
        return mime_content_type($path);
    }

    /**
     * 创建目录
     */
    public static function mkdir($path, $mode = 0755)
    {
        if (is_dir($path)) {
            return true;
        }
        return mkdir($path, $mode, true);
    }

    /**
     * 删除目录（递归）
     */
    public static function rmdir($path, $recursive = true)
    {
        if (!is_dir($path)) {
            return true;
        }

        if ($recursive) {
            $files = array_diff(scandir($path), ['.', '..']);
            foreach ($files as $file) {
                $filePath = $path . '/' . $file;
                if (is_dir($filePath)) {
                    self::rmdir($filePath, true);
                } else {
                    unlink($filePath);
                }
            }
        }

        return rmdir($path);
    }

    /**
     * 获取目录文件列表
     */
    public static function files($path, $recursive = false)
    {
        if (!is_dir($path)) {
            return [];
        }

        $result = [];
        $items = array_diff(scandir($path), ['.', '..']);

        foreach ($items as $item) {
            $itemPath = $path . '/' . $item;
            if (is_file($itemPath)) {
                $result[] = $itemPath;
            }
            if ($recursive && is_dir($itemPath)) {
                $result = array_merge($result, self::files($itemPath, true));
            }
        }

        return $result;
    }

    /**
     * 获取目录所有内容（文件和子目录）
     */
    public static function dirAll($path, $recursive = false)
    {
        if (!is_dir($path)) {
            return [];
        }

        $result = ['files' => [], 'dirs' => []];
        $items = array_diff(scandir($path), ['.', '..']);

        foreach ($items as $item) {
            $itemPath = $path . '/' . $item;
            if (is_file($itemPath)) {
                $result['files'][] = $itemPath;
            }
            if (is_dir($itemPath)) {
                $result['dirs'][] = $itemPath;
                if ($recursive) {
                    $sub = self::dirAll($itemPath, true);
                    $result['files'] = array_merge($result['files'], $sub['files']);
                    $result['dirs'] = array_merge($result['dirs'], $sub['dirs']);
                }
            }
        }

        return $result;
    }

    /**
     * 格式化文件大小
     */
    public static function formatSize($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < 4) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, $precision) . ' ' . $units[$i];
    }

    // ─────────────────────────────────────────────────────────────
    // 私有辅助方法
    // ─────────────────────────────────────────────────────────────

    /**
     * 确保目录存在
     */
    private static function ensureDirectory($path)
    {
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }

    /**
     * 获取上传错误信息
     */
    private function getUploadError($code)
    {
        $errors = [
            UPLOAD_ERR_INI_SIZE   => '文件超过服务器限制',
            UPLOAD_ERR_FORM_SIZE  => '文件超过表单限制',
            UPLOAD_ERR_PARTIAL    => '文件只有部分被上传',
            UPLOAD_ERR_NO_FILE    => '没有文件被上传',
            UPLOAD_ERR_NO_TMP_DIR => '找不到临时文件夹',
            UPLOAD_ERR_CANT_WRITE => '文件写入失败',
            UPLOAD_ERR_EXTENSION  => '文件扩展名被阻止',
        ];
        return $errors[$code] ?? '未知上传错误';
    }
}
