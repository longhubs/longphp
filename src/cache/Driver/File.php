<?php
// src/Cache/Driver/File.php

namespace Long\Cache\Driver;

use Long\Cache\DriverInterface;

class File implements DriverInterface
{
    protected $cachePath;
    protected $prefix;

    public function __construct($config = [])
    {
        $this->prefix = $config['prefix'] ?? 'cache_';
        $this->cachePath = $config['path'] ?? ROOT_PATH . '/storage/cache/data/';
        
        if (!is_dir($this->cachePath)) {
            mkdir($this->cachePath, 0755, true);
        }
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
        if (isset($data['expire']) && $data['expire'] < time()) {
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
        foreach ($files as $file) {
            unlink($file);
        }
        return true;
    }
}