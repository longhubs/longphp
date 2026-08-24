<?php
// src/console/MakeModel.php

namespace Long\Console;

class MakeModel
{
    public static function run($args)
    {
        $name = $args[2] ?? '';
        
        if (empty($name)) {
            echo "❌ 请指定模型名称\n";
            echo "用法: php long make:model User\n";
            return;
        }

        $className = Generator::studly($name);
        $rootPath = Generator::getRootPath();
        
        $path = $rootPath . '/app/model/' . $className . '.php';
        $namespace = Generator::getNamespace($path, $rootPath . '/app/model/', 'App');

        $content = self::getTemplate($className, $namespace);
        Generator::generate($path, $content);
    }

    protected static function getTemplate($className, $namespace)
    {
        $table = strtolower(preg_replace('/(?<=[a-z])([A-Z])/', '_$1', $className));
        
        return <<<PHP
<?php
// app/model/{$className}.php

namespace {$namespace};

use Long\Model;

class {$className} extends Model
{
    protected \$table = '{$table}';
    protected \$pk = 'id';
    protected \$autoTimestamp = true;
    protected \$createTime = 'create_time';
    protected \$updateTime = 'update_time';
}
PHP;
    }
}