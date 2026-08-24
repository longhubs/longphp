<?php
// src/Cache/Driver/Redis.php

namespace Long\Cache\Driver;

use Long\Cache\DriverInterface;

class Redis implements DriverInterface
{
    protected $redis;
    protected $prefix;

    public function __construct($config = [])
    {
        $this->prefix = $config['prefix'] ?? 'cache_';
        
        $this->redis = new \Redis();
        $this->redis->connect(
            $config['host'] ?? '127.0.0.1',
            $config['port'] ?? 6379
        );
        if (!empty($config['password'])) {
            $this->redis->auth($config['password']);
        }
        if (!empty($config['database'])) {
            $this->redis->select($config['database']);
        }
    }

    public function get($key)
    {
        $value = $this->redis->get($this->prefix . $key);
        return $value !== false ? unserialize($value) : null;
    }

    public function set($key, $value, $ttl = null)
    {
        $key = $this->prefix . $key;
        $value = serialize($value);
        
        if ($ttl) {
            return $this->redis->setex($key, $ttl, $value);
        }
        return $this->redis->set($key, $value);
    }

    public function delete($key)
    {
        return $this->redis->del($this->prefix . $key) > 0;
    }

    public function has($key)
    {
        return $this->redis->exists($this->prefix . $key) > 0;
    }

    public function clear()
    {
        $keys = $this->redis->keys($this->prefix . '*');
        foreach ($keys as $key) {
            $this->redis->del($key);
        }
        return true;
    }
}