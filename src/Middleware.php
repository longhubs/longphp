<?php
// long/Middleware.php
// LongPHP Framework - 中间件基类

namespace Long;

abstract class Middleware
{
    abstract public function handle($request, $next);
}