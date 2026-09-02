<?php
// src/Cache/DriverInterface.php

namespace Long\Cache;

interface DriverInterface
{
    public function get($key);
    public function set($key, $value, $ttl = null);
    public function delete($key);
    public function has($key);
    public function clear();

    // 可选方法（各驱动自己实现）
    // public function isConnected();
    // public function getDriverName();
    // public function increment($key, $step = 1);
    // public function decrement($key, $step = 1);
    // public function expire($key, $ttl);
    // public function ttl($key);
}