<?php
// src/Console/ServerCommand.php

namespace Long\Console;

use Long\App;  // ⬅️ 添加引用

class ServerCommand extends Command
{
    protected $app;  // ⬅️ 添加 App 实例

    public function __construct($argv = [])
    {
        parent::__construct($argv);
        
        // ⬅️ 获取 App 实例
        $this->app = App::getInstance();
    }

    public function run()
    {
        $port = $this->getArg(2, 8081);
        $host = '127.0.0.1';
        
        // ✅ 使用框架路径方法
        $basePath = $this->app->getBasePath();
        $publicPath = $this->app->getPublicPath();

        echo "\n";
        echo "🐉 LongPHP 开发服务器已启动\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "  地址: http://{$host}:{$port}\n";
        echo "  文档根目录: {$publicPath}\n";
        echo "  按 Ctrl+C 停止服务器\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

        // ✅ 使用框架路径构建命令
        $cmd = sprintf(
            'php -S %s:%d -t %s -r "if (preg_match(\'/\\.(?:css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot|map)$/\', $_SERVER[\'REQUEST_URI\'])) { return false; } require __DIR__ . \'/index.php\';"',
            $host,
            $port,
            $publicPath
        );
        system($cmd);
    }
}