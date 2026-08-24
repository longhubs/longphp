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
}