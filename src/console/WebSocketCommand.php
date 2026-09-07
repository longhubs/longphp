<?php
namespace Long\Console;

use Long\App;
use Long\WebSocketManager;

class WebSocketCommand extends Command
{
    protected $app;

    public function __construct($argv = [], App $app = null)
    {
        parent::__construct($argv, $app);
        $this->app = $app ?: App::getInstance();
    }

    public function run()
    {
        $action = $this->getArg(2, 'start');
        $port = $this->getArg(3);
        $port = is_numeric($port) ? (int)$port : null;

        $daemon = $this->hasArg('-d');

        if ($action === 'start-d' || $action === 'startd') {
            $action = 'start';
            $daemon = true;
        }

        // ⭐ 传入 App 实例
        $manager = new WebSocketManager($this->app);

        switch ($action) {
            case 'start':
                echo $manager->start($port, $daemon);
                break;
            case 'stop':
                echo $manager->stop($port);
                break;
            case 'status':
                echo $manager->status();
                break;
            case 'restart':
                echo $manager->restart($port);
                break;
            default:
                $this->showHelp();
        }
    }

    protected function showHelp()
    {
        echo <<<HELP
🐉 LongPHP WebSocket 命令
──────────────────────────────────────────
  php long ws start -d              自动启动所有服务
  php long ws start 8081 -d         启动指定端口
  php long ws start 8081            前台调试模式
  php long ws stop                  停止所有服务
  php long ws stop 8081             停止指定端口
  php long ws status                查看所有状态
  php long ws restart 8081          重启指定端口

新增业务后执行:
  php long ws start -d
HELP;
    }
}