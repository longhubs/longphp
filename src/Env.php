<?php
// src/Env.php
// LongPHP Framework - 环境变量加载器
// 龙行天下 🐉

namespace Long;

class Env
{
    /**
     * 环境变量缓存
     * @var array
     */
    protected static $values = [];

    /**
     * 加载 .env 文件
     * @param string $path .env 文件路径
     */
    public static function load($path = null)
    {
        $app = App::getInstance();
        $path = $app->getEnvPath();

        if (!file_exists($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            
            // 跳过注释行
            if (strpos($line, '#') === 0 || strpos($line, '//') === 0) {
                continue;
            }
            
            // 解析 KEY=VALUE
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // 去除引号
                $value = trim($value, '"\'');
                
                // 替换 ${VAR} 格式的变量
                $value = preg_replace_callback('/\$\{([^}]+)\}/', function($matches) {
                    return self::get($matches[1], '');
                }, $value);
                
                self::$values[$key] = $value;
                
                // 设置到环境变量
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
            }
        }
    }

    /**
     * 获取环境变量
     * @param string $key 键名
     * @param mixed $default 默认值
     * @return mixed
     */
    public static function get($key, $default = null)
    {
        $value = self::$values[$key] ?? getenv($key) ?? $default;
        
        // 类型转换
        if (is_string($value)) {
            $lower = strtolower($value);
            if ($lower === 'true') {
                return true;
            }
            if ($lower === 'false') {
                return false;
            }
            if ($lower === 'null') {
                return null;
            }
            if (is_numeric($value) && strpos($value, '.') !== false) {
                return (float)$value;
            }
            if (is_numeric($value)) {
                return (int)$value;
            }
        }
        
        return $value;
    }

    /**
     * 设置环境变量
     * @param string $key
     * @param mixed $value
     */
    public static function set($key, $value)
    {
        self::$values[$key] = $value;
        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
    }

    /**
     * 检查是否存在
     * @param string $key
     * @return bool
     */
    public static function has($key)
    {
        return isset(self::$values[$key]) || getenv($key) !== false;
    }

    /**
     * 获取所有环境变量
     * @return array
     */
    public static function all()
    {
        return self::$values;
    }
}