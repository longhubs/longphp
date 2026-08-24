<?php
// src/console/MakeController.php

namespace Long\Console;

class MakeController
{
    public static function run($args)
    {
        $name = $args[2] ?? '';
        
        if (empty($name)) {
            echo "❌ 请指定控制器名称\n";
            echo "用法: php long make:controller UserController\n";
            return;
        }

        $className = Generator::studly($name);
        $rootPath = Generator::getRootPath();
        
        $path = $rootPath . '/app/controller/' . $className . '.php';
        $namespace = Generator::getNamespace($path, $rootPath . '/app/controller/', 'App');

        $content = self::getTemplate($className, $namespace);
        Generator::generate($path, $content);
    }

    protected static function getTemplate($className, $namespace)
    {
        return <<<PHP
<?php
// app/controller/{$className}.php

namespace {$namespace};

use Long\Controller;
use Long\Request;
use Long\Db;

class {$className} extends Controller
{
    public function index(Request \$request)
    {
        \$page = \$request->param('page', 1);
        \$limit = \$request->param('limit', 15);
        
        \$result = Db::table('')
            ->paginate(\$page, \$limit);
        
        return \$this->success(\$result, '获取成功');
    }

    public function show(Request \$request, \$id)
    {
        \$data = Db::table('')
            ->where('id', \$id)
            ->find();
        
        if (!\$data) {
            return \$this->error('数据不存在', 404);
        }
        
        return \$this->success(\$data, '获取成功');
    }

    public function store(Request \$request)
    {
        \$data = \$request->all();
        
        \$id = Db::table('')->insert(\$data);
        
        if (\$id) {
            return \$this->success(['id' => \$id], '创建成功');
        }
        
        return \$this->error('创建失败', 500);
    }

    public function update(Request \$request, \$id)
    {
        \$data = \$request->all();
        unset(\$data['id']);
        
        \$result = Db::table('')
            ->where('id', \$id)
            ->update(\$data);
        
        if (\$result) {
            return \$this->success([], '更新成功');
        }
        
        return \$this->error('更新失败', 500);
    }

    public function delete(Request \$request, \$id)
    {
        \$result = Db::table('')
            ->where('id', \$id)
            ->delete();
        
        if (\$result) {
            return \$this->success([], '删除成功');
        }
        
        return \$this->error('删除失败', 500);
    }
}
PHP;
    }
}