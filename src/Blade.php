<?php
// src/Blade.php
// LongPHP Framework - Blade 模板引擎封装
// 龙行天下 🐉

namespace Long;

use eftec\bladeone\BladeOne;

class Blade
{
    protected static $instance;
    protected static $viewPath;
    protected static $cachePath;

    public static function init()
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        self::$viewPath = ROOT_PATH . '/app/views';
        self::$cachePath = ROOT_PATH . '/storage/cache/views';

        if (!is_dir(self::$viewPath)) {
            mkdir(self::$viewPath, 0755, true);
        }
        if (!is_dir(self::$cachePath)) {
            mkdir(self::$cachePath, 0755, true);
        }

        self::$instance = new BladeOne(
            self::$viewPath,
            self::$cachePath,
            BladeOne::MODE_DEBUG
        );

        // ✅ 设置文件扩展名
        self::$instance->setFileExtension('.blade.php');

        self::registerDirectives();

        return self::$instance;
    }

    protected static function registerDirectives()
    {
        $blade = self::$instance;

        $blade->directive('datetime', function($expression) {
            return "<?php echo date('Y-m-d H:i:s', {$expression}); ?>";
        });

        $blade->directive('config', function($expression) {
            return "<?php echo config({$expression}); ?>";
        });

        $blade->directive('auth', function() {
            return "<?php if (function_exists('jwt_user') && jwt_user()): ?>";
        });

        $blade->directive('endauth', function() {
            return "<?php endif; ?>";
        });

        $blade->directive('guest', function() {
            return "<?php if (!function_exists('jwt_user') || !jwt_user()): ?>";
        });

        $blade->directive('endguest', function() {
            return "<?php endif; ?>";
        });

        $blade->directive('url', function($expression) {
            return "<?php echo U({$expression}); ?>";
        });
    }

    public static function render($view, $data = [])
    {
        $blade = self::init();
        
        // ✅ 将 user.detail → user/detail.blade.php
        $view = str_replace('.', '/', $view) . '.blade.php';
        
        try {
            return $blade->run($view, $data);
        } catch (\Exception $e) {
            die("Blade 渲染错误: " . $e->getMessage() . "\n文件: " . $e->getFile() . " (第 " . $e->getLine() . " 行)");
        }
    }

    public static function display($view, $data = [])
    {
        echo self::render($view, $data);
    }
}