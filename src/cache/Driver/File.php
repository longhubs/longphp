<?php
// src/Cache/Driver/File.php

namespace Long\Cache\Driver;

use Long\Cache\DriverInterface;
use Long\App;  // ⬅️ 添加这个引用

class File implements DriverInterface
{
    protected $cachePath;
    protected $prefix;
    protected $connected = true;

    public function __construct($config = [])
    {
        $this->prefix = $config['prefix'] ?? 'cache_';
        
        // ✅ 替换方案：使用 App 实例获取路径
        $app = App::getInstance();
        
        // 方案一：使用框架的 getStoragePath() 方法（推荐）
        $this->cachePath = $config['path'] ?? $app->getStoragePath('cache/data/');
        
        // 方案二：使用 getBasePath() 手动拼接（备选）
        // $this->cachePath = $config['path'] ?? $app->getBasePath('storage/cache/data/');
        
        // 方案三：兼容旧代码，如果配置中有 path 就用配置的
        // $this->cachePath = $config['path'] ?? $app->getStoragePath() . '/cache/data/';
        
        // 确保目录存在
        if (!is_dir($this->cachePath)) {
            mkdir($this->cachePath, 0755, true);
        }
    }

    /**
     * 检查是否可用（文件驱动始终可用）
     */
    public function isConnected()
    {
        return $this->connected && is_dir($this->cachePath) && is_writable($this->cachePath);
    }

    /**
     * 获取驱动名称
     */
    public function getDriverName()
    {
        return 'file';
    }

    protected function getFilePath($key)
    {
        $filename = md5($this->prefix . $key);
        return $this->cachePath . '/' . $filename . '.cache';
    }

    public function get($key)
    {
        $file = $this->getFilePath($key);

        if (!file_exists($file)) {
            return null;
        }

        $data = unserialize(file_get_contents($file));

        // 检查是否过期
        if (isset($data['expire']) && $data['expire'] !== null && $data['expire'] < time()) {
            $this->delete($key);
            return null;
        }

        return $data['value'] ?? null;
    }

    public function set($key, $value, $ttl = null)
    {
        $file = $this->getFilePath($key);

        $data = [
            'value' => $value,
            'expire' => $ttl ? time() + $ttl : null,
            'time' => time()
        ];

        return file_put_contents($file, serialize($data)) !== false;
    }

    public function delete($key)
    {
        $file = $this->getFilePath($key);
        if (file_exists($file)) {
            return unlink($file);
        }
        return true;
    }

    public function has($key)
    {
        return $this->get($key) !== null;
    }

    public function clear()
    {
        $files = glob($this->cachePath . '*.cache');
        $success = true;
        foreach ($files as $file) {
            if (!unlink($file)) {
                $success = false;
            }
        }
        return $success;
    }

    /**
     * 自增
     */
    public function increment($key, $step = 1)
    {
        $value = $this->get($key);
        $value = ($value ?: 0) + $step;
        $this->set($key, $value);
        return $value;
    }

    /**
     * 自减
     */
    public function decrement($key, $step = 1)
    {
        $value = $this->get($key);
        $value = ($value ?: 0) - $step;
        $this->set($key, $value);
        return $value;
    }

    /**
     * 设置过期时间
     */
    public function expire($key, $ttl)
    {
        $value = $this->get($key);
        if ($value === null) {
            return false;
        }
        return $this->set($key, $value, $ttl);
    }

    /**
     * 获取剩余时间（秒）
     */
    public function ttl($key)
    {
        $file = $this->getFilePath($key);
        if (!file_exists($file)) {
            return -2;
        }

        $data = unserialize(file_get_contents($file));
        if (!isset($data['expire']) || $data['expire'] === null) {
            return -1; // 永不过期
        }

        $remaining = $data['expire'] - time();
        return $remaining > 0 ? $remaining : -2;
    }

    /**
     * 获取缓存统计
     */
    public function stats()
    {
        $files = glob($this->cachePath . '*.cache');
        $total = count($files);
        $size = 0;
        foreach ($files as $file) {
            $size += filesize($file);
        }
        return [
            'total' => $total,
            'size' => $size,
            'path' => $this->cachePath,
        ];
    }
}