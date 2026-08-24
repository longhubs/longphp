<?php
// src/console/Generator.php

namespace Long\Console;

class Generator
{
    /**
     * 获取项目根目录
     * @return string
     */
    public static function getRootPath()
    {
        // 从当前文件位置向上查找，直到找到 app 目录
        $path = __DIR__;
        $maxDepth = 15;
        
        while ($maxDepth-- > 0) {
            if (is_dir($path . '/app') && is_dir($path . '/config')) {
                return $path;
            }
            $parent = dirname($path);
            if ($parent === $path) {
                break;
            }
            $path = $parent;
        }
        
        // 如果找不到，从 vendor 位置推断
        $vendorPos = strpos(__DIR__, 'vendor' . DIRECTORY_SEPARATOR);
        if ($vendorPos !== false) {
            return substr(__DIR__, 0, $vendorPos);
        }
        
        return dirname(__DIR__, 3);
    }

    public static function generate($path, $content, $force = false)
    {
        if (file_exists($path) && !$force) {
            echo "❌ 文件已存在: {$path}\n";
            return false;
        }

        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($path, $content);
        echo "✅ 已生成: {$path}\n";
        return true;
    }

    public static function getNamespace($path, $baseDir, $namespace = 'App')
    {
        $relative = str_replace($baseDir, '', $path);
        $relative = trim($relative, '/');
        $parts = explode('/', $relative);
        array_pop($parts);
        $namespacePath = implode('\\', $parts);
        return $namespace . ($namespacePath ? '\\' . $namespacePath : '');
    }

    public static function getClassName($path)
    {
        return pathinfo($path, PATHINFO_FILENAME);
    }

    public static function studly($name)
    {
        return str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $name)));
    }
}