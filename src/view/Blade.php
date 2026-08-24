<?php
// src/view/Blade.php

namespace Long\view;

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

        self::registerDirectives();

        return self::$instance;
    }

    protected static function registerDirectives()
    {
        $blade = self::$instance;

        $blade->directive('datetime', function($expression) {
            return "<?php echo date('Y-m-d H:i:s', {$expression}); ?>";
        });

        $blade->directive('auth', function() {
            return "<?php if (function_exists('jwt_user') && jwt_user()): ?>";
        });

        $blade->directive('endauth', function() {
            return "<?php endif; ?>";
        });

        $blade->directive('config', function($expression) {
            return "<?php echo config({$expression}); ?>";
        });
    }

    public static function render($view, $data = [])
    {
        $blade = self::init();
        
        // ✅ 将 user.detail → user/detail.blade.php
        $viewFile = str_replace('.', '/', $view) . '.blade.php';
        
        return $blade->run($viewFile, $data);
    }

    public static function display($view, $data = [])
    {
        echo self::render($view, $data);
    }
}