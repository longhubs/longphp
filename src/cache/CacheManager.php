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
        $this->config = $config;
        $this->driver = $this->createDriver();
    }

    public static function getInstance($config = [])
    {
        if (self::$instance === null) {
            self::$instance = new self($config);
        }
        return self::$instance;
    }

    protected function createDriver()
    {
        $driver = $this->config['driver'] ?? 'file';
        
        switch ($driver) {
            case 'redis':
                if (class_exists('\Redis')) {
                    return new Driver\Redis($this->config[$driver] ?? []);
                }
                // 如果 Redis 不可用，降级到文件
                return new Driver\File($this->config['file'] ?? []);
            case 'file':
            default:
                return new Driver\File($this->config['file'] ?? []);
        }
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
     * @param string $key 缓存键
     * @param callable $callback 回调函数
     * @param int $ttl 过期时间（秒）
     * @return mixed
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
     * 增加缓存值
     */
    public function increment($key, $step = 1)
    {
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
        $value = $this->get($key);
        $value = ($value ?: 0) - $step;
        $this->set($key, $value);
        return $value;
    }
}