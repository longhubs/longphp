<?php
// src/Cache/Driver/Redis.php

namespace Long\Cache\Driver;

use Long\Cache\DriverInterface;

class Redis implements DriverInterface
{
    protected $redis;
    protected $prefix;
    protected $connected = false;

    public function __construct($config = [])
    {
        $this->prefix = $config['prefix'] ?? 'cache_';

        try {
            $this->redis = new \Redis();

            $host = $config['host'] ?? '127.0.0.1';
            $port = $config['port'] ?? 6379;
            $timeout = $config['timeout'] ?? 0;

            // 尝试连接
            $result = $this->redis->connect($host, $port, $timeout);

            if ($result === false) {
                $this->connected = false;
                $this->redis = null;
                if (function_exists('logs')) {
                    logs('Redis 连接失败：无法连接到 ' . $host . ':' . $port, 'error');
                }
                return;
            }

            // 密码验证
            if (!empty($config['password'])) {
                $authResult = $this->redis->auth($config['password']);
                if ($authResult === false) {
                    $this->connected = false;
                    $this->redis = null;
                    if (function_exists('logs')) {
                        logs('Redis 认证失败：密码错误', 'error');
                    }
                    return;
                }
            }

            // 选择数据库
            if (isset($config['database'])) {
                $this->redis->select($config['database']);
            }

            $this->connected = true;

        } catch (\Exception $e) {
            $this->connected = false;
            $this->redis = null;
            if (function_exists('logs')) {
                logs('Redis 连接异常：' . $e->getMessage(), 'error');
            }
        }
    }

    /**
     * 检查是否已连接
     */
    public function isConnected()
    {
        if (!$this->connected || $this->redis === null) {
            return false;
        }

        try {
            // ✅ 扩展自带的方法，永远返回 true/false
            return $this->redis->isConnected();
        } catch (\Exception $e) {
            $this->connected = false;
            $this->redis = null;
            return false;
        }
    }

    /**
     * 获取 Redis 实例
     */
    public function getRedis()
    {
        return $this->isConnected() ? $this->redis : null;
    }

    /**
     * 获取驱动名称
     */
    public function getDriverName()
    {
        return $this->isConnected() ? 'redis' : 'file';
    }

    public function get($key)
    {
        if (!$this->isConnected()) {
            return null;
        }

        try {
            $value = $this->redis->get($this->prefix . $key);
            return $value !== false && $value !== null ? unserialize($value) : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function set($key, $value, $ttl = null)
    {
        if (!$this->isConnected()) {
            return false;
        }

        try {
            $key = $this->prefix . $key;
            $value = serialize($value);

            if ($ttl && $ttl > 0) {
                return $this->redis->setex($key, $ttl, $value);
            }
            return $this->redis->set($key, $value);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function delete($key)
    {
        if (!$this->isConnected()) {
            return false;
        }

        try {
            return $this->redis->del($this->prefix . $key) > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function has($key)
    {
        if (!$this->isConnected()) {
            return false;
        }

        try {
            return $this->redis->exists($this->prefix . $key) > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function clear()
    {
        if (!$this->isConnected()) {
            return false;
        }

        try {
            $keys = $this->redis->keys($this->prefix . '*');
            foreach ($keys as $key) {
                $this->redis->del($key);
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 自增
     */
    public function increment($key, $step = 1)
    {
        if (!$this->isConnected()) {
            return false;
        }

        try {
            return $this->redis->incrBy($this->prefix . $key, $step);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 自减
     */
    public function decrement($key, $step = 1)
    {
        if (!$this->isConnected()) {
            return false;
        }

        try {
            return $this->redis->decrBy($this->prefix . $key, $step);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 设置过期时间
     */
    public function expire($key, $ttl)
    {
        if (!$this->isConnected()) {
            return false;
        }

        try {
            return $this->redis->expire($this->prefix . $key, $ttl);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 获取剩余时间
     */
    public function ttl($key)
    {
        if (!$this->isConnected()) {
            return -2;
        }

        try {
            return $this->redis->ttl($this->prefix . $key);
        } catch (\Exception $e) {
            return -2;
        }
    }
}