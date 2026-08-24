<?php
// long/View.php
// LongPHP Framework - 视图渲染

namespace Long;

class View
{
    private static $config = [];

    public static function setConfig($config): void
    {
        self::$config = $config;
    }

    public static function render(string $view, array $data = [], ?string $layout = null): string
    {
        // 提取数据为变量
        extract($data);
        
        // 视图文件路径（根目录下的 views 文件夹）
        $viewPath = __DIR__ . '/../views/' . str_replace('.', '/', $view) . '.php';
        
        if (!file_exists($viewPath)) {
            throw new \Exception("视图文件不存在: {$viewPath}");
        }
        
        // 开启输出缓冲
        ob_start();
        include $viewPath;
        $content = ob_get_clean();
        
        // 如果指定了布局文件
        if ($layout) {
            $layoutPath = __DIR__ . '/../views/' . str_replace('.', '/', $layout) . '.php';
            if (file_exists($layoutPath)) {
                ob_start();
                include $layoutPath;
                return ob_get_clean();
            }
        }
        
        return $content;
    }
}