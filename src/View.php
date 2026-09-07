<?php
// src/View.php
// LongPHP Framework - 视图管理
// 龙行天下 🐉

namespace Long;

use Long\App;  // ⬅️ 添加引用

class View
{
    protected static $vars = [];
    
    /**
     * @var App
     */
    protected static $app;  // ⬅️ 添加 App 实例

    /**
     * 初始化 App 实例（延迟加载）
     */
    protected static function getApp()
    {
        if (self::$app === null) {
            self::$app = App::getInstance();
        }
        return self::$app;
    }

    public static function assign($name, $value = null)
    {
        if (is_array($name)) {
            foreach ($name as $key => $val) {
                self::$vars[$key] = $val;
            }
        } else {
            self::$vars[$name] = $value;
        }
    }

    public static function getAssign($name = null)
    {
        if ($name === null) {
            return self::$vars;
        }
        return self::$vars[$name] ?? null;
    }

    public static function clearAssign()
    {
        self::$vars = [];
    }

    public static function render($view, $data = [], $engine = 'auto')
    {
        $allData = array_merge(self::$vars, $data);
        $viewPath = str_replace('.', '/', $view);

        // ✅ 使用框架的 getAppPath() 方法获取视图目录
        $app = self::getApp();
        $viewDir = $app->getAppPath('views');
        $bladeFile = $viewDir . '/' . $viewPath . '.blade.php';
        
        if ($engine === 'auto' && file_exists($bladeFile)) {
            $engine = 'blade';
        } elseif ($engine === 'auto') {
            $engine = 'php';
        }

        if ($engine === 'blade') {
            // 直接调用 Blade::render()
            return Blade::render($view, $allData);
        }

        return self::renderPhp($view, $allData);
    }

    protected static function renderPhp($view, $data = [])
    {
        extract($data);
        
        // ✅ 使用框架的 getAppPath() 方法
        $app = self::getApp();
        $viewFile = $app->getAppPath('views/' . str_replace('.', '/', $view) . '.php');
        
        if (!file_exists($viewFile)) {
            throw new \Exception("视图文件不存在: {$viewFile}");
        }
        ob_start();
        include $viewFile;
        return ob_get_clean();
    }

    public static function display($view, $data = [], $engine = 'auto')
    {
        echo self::render($view, $data, $engine);
    }
}