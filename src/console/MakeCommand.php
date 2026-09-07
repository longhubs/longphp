<?php
namespace Long\Console;

class MakeCommand extends Command
{
    public function run()
    {
        $type = $this->getArg(1, '');
        $name = $this->getArg(2, '');

        if (!$name) {
            $this->error("请指定名称");
            echo "示例: php long make:controller UserController\n";
            return;
        }

        switch ($type) {
            case 'make:controller':
                \Long\Console\MakeController::run($this->argv);
                break;
            case 'make:model':
                \Long\Console\MakeModel::run($this->argv);
                break;
            case 'make:middleware':
                \Long\Console\MakeMiddleware::run($this->argv);
                break;
            case 'make:validate':
                \Long\Console\MakeValidate::run($this->argv);
                break;
            default:
                $this->error("未知生成器: {$type}");
        }
    }
}