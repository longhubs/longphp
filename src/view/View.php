<?php
// src/View/View.php

namespace Long\View;

class View
{
    public static function render($view, $data = [], $engine = 'blade')
    {
        if ($engine === 'blade') {
            return Blade::render($view, $data);
        }
        return self::renderPhp($view, $data);
    }

    protected static function renderPhp($view, $data = [])
    {
        extract($data);
        $viewFile = ROOT_PATH . '/app/views/' . str_replace('.', '/', $view) . '.php';
        if (!file_exists($viewFile)) {
            throw new \Exception("视图文件不存在: {$viewFile}");
        }
        ob_start();
        include $viewFile;
        return ob_get_clean();
    }

    public static function display($view, $data = [], $engine = 'blade')
    {
        echo self::render($view, $data, $engine);
    }
}