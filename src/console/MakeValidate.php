<?php
// src/console/MakeValidate.php

namespace Long\Console;

class MakeValidate
{
    public static function run($args)
    {
        $name = $args[2] ?? '';
        
        if (empty($name)) {
            echo "❌ 请指定验证器名称\n";
            echo "用法: php long make:validate UserValidate\n";
            return;
        }

        $className = Generator::studly($name);
        $rootPath = Generator::getRootPath();
        
        $path = $rootPath . '/app/validate/' . $className . '.php';
        $namespace = Generator::getNamespace($path, $rootPath . '/app/validate/', 'App');

        $content = self::getTemplate($className, $namespace);
        Generator::generate($path, $content);
    }

    protected static function getTemplate($className, $namespace)
    {
        return <<<PHP
<?php
// app/validate/{$className}.php

namespace {$namespace};

use Long\Validate;

class {$className} extends Validate
{
    protected \$rules = [];
    protected \$messages = [];
}
PHP;
    }
}