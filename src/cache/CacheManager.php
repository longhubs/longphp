<?php
// src/Cache/CacheManager.php

namespace Long\Cache;

use Long\Cache\Driver\File;

class CacheManager
{
    protected static $instance;
    protected $driver;
    protected $config;

    public function __construct($config = [])
    {
        // 加载配置
        $this->config = $config ?: $this->loadConfig();
        $this->driver = $this->createDriver();
    }

    /**
     * 加载配置文件
     */
    protected function loadConfig()
    {
        $configFile = ROOT_PATH . '/config/app.php';
        if (file_exists($configFile)) {
            $config = require $configFile;
            return $config['cache'] ?? ['driver' => 'file'];
        }
        return ['driver' => 'file'];
    }

    /**
     * 获取单例实例
     */
    public static function getInstance($config = [])
    {
        if (self::$instance === null) {
            self::$instance = new self($config);
        }
        return self::$instance;
    }

    /**
     * 创建驱动
     */
    protected function createDriver()
    {
        $driver = $this->config['driver'] ?? 'file';

        switch ($driver) {
            case 'redis':
                if (class_exists('\Redis')) {
                    try {
                        $redis = new Driver\Redis($this->config['redis'] ?? []);
                        if ($redis->isConnected()) {
                            return $redis;
                        }
                    } catch (\Exception $e) {
                        // 记录异常
                    }
                    // 连接失败，降级到文件缓存
                    if (function_exists('logs')) {
                        logs('Redis 连接失败，降级到文件缓存', 'warning');
                    }
                } else {
                    if (function_exists('logs')) {
                        logs('Redis 扩展未安装，使用文件缓存', 'warning');
                    }
                }
                return new Driver\File($this->config['file'] ?? []);
            case 'file':
            default:
                return new Driver\File($this->config['file'] ?? []);
        }
    }

    /**
     * 检查是否连接成功
     */
    public function isConnected()
    {
        if (method_exists($this->driver, 'isConnected')) {
            return $this->driver->isConnected();
        }
        return true; // 文件驱动始终可用
    }

    /**
     * 获取驱动名称
     */
    public function getDriverName()
    {
        if (method_exists($this->driver, 'getDriverName')) {
            return $this->driver->getDriverName();
        }
        return 'file';
    }

    /**
     * 获取原始驱动对象
     */
    public function getDriver()
    {
        return $this->driver;
    }

    public function get($key)
    {
        return $this->driver->get($key);
    }

    public function set($key, $value, $ttl = null)
    {
        return $this->driver->set($key, $value, $ttl);
    }

    public function delete($key)
    {
        return $this->driver->delete($key);
    }

    public function has($key)
    {
        return $this->driver->has($key);
    }

    public function clear()
    {
        return $this->driver->clear();
    }

    /**
     * 获取或设置缓存（类似 Laravel 的 remember）
     */
    public function remember($key, $callback, $ttl = null)
    {
        $value = $this->get($key);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);
        return $value;
    }

    /**
     * 增加缓存值（支持 Redis 原子操作）
     */
    public function increment($key, $step = 1)
    {
        if (method_exists($this->driver, 'increment')) {
            return $this->driver->increment($key, $step);
        }

        // 降级方案
        $value = $this->get($key);
        $value = ($value ?: 0) + $step;
        $this->set($key, $value);
        return $value;
    }

    /**
     * 减少缓存值
     */
    public function decrement($key, $step = 1)
    {
        if (method_exists($this->driver, 'decrement')) {
            return $this->driver->decrement($key, $step);
        }

        // 降级方案
        $value = $this->get($key);
        $value = ($value ?: 0) - $step;
        $this->set($key, $value);
        return $value;
    }
}