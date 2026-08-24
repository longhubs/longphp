<?php
// src/console/MakeMiddleware.php

namespace Long\Console;

class MakeMiddleware
{
    public static function run($args)
    {
        $name = $args[2] ?? '';
        
        if (empty($name)) {
            echo "❌ 请指定中间件名称\n";
            echo "用法: php long make:middleware Auth\n";
            return;
        }

        $className = Generator::studly($name);
        $rootPath = Generator::getRootPath();
        
        $path = $rootPath . '/app/middleware/' . $className . '.php';
        $namespace = Generator::getNamespace($path, $rootPath . '/app/middleware/', 'App');

        $content = self::getTemplate($className, $namespace);
        Generator::generate($path, $content);
    }

    protected static function getTemplate($className, $namespace)
    {
        return <<<PHP
<?php
// app/middleware/{$className}.php

namespace {$namespace};

use Long\Middleware;
use Long\Request;

class {$className} extends Middleware
{
    public function handle(\$request, \$next)
    {
        return \$next(\$request);
    }
}
PHP;
    }
}