<?php
namespace Long\Console;

class HelpCommand extends Command
{
    public function run()
    {
        echo <<<HELP
╔══════════════════════════════════════════════════════════════════════╗
║  🐉 LongPHP Framework - 命令行工具（龙行天下）                     ║
╠══════════════════════════════════════════════════════════════════════╣
║  开发服务器:                                                       ║
║    php long server         启动开发服务器（默认 8081 端口）        ║
║    php long server 8082    指定端口启动                            ║
╠══════════════════════════════════════════════════════════════════════╣
║  定时任务:                                                         ║
║    php long run        执行所有到期任务                            ║
║    php long list       列出所有已注册的任务                        ║
║    php long work       常驻运行模式（开发调试）                    ║
║    php long start      后台启动调度器（生产环境）                  ║
║    php long stop       停止后台调度器                              ║
║    php long status     查看调度器状态                              ║
║    php long install    安装系统 Cron                               ║
╠══════════════════════════════════════════════════════════════════════╣
║  WebSocket 服务:                                                   ║
║    php long ws start [端口]     前台运行（调试）                   ║
║    php long ws start [端口] -d  后台运行（生产）                   ║
║    php long ws stop             停止服务                           ║
║    php long ws restart [端口]   重启服务                           ║
║    php long ws status           查看状态                           ║
╠══════════════════════════════════════════════════════════════════════╣
║  代码生成器:                                                       ║
║    php long make:controller UserController  生成控制器             ║
║    php long make:model User                生成模型                ║
║    php long make:middleware Auth           生成中间件              ║
║    php long make:validate UserValidate     生成验证器              ║
╠══════════════════════════════════════════════════════════════════════╣
║  使用示例:                                                         ║
║    1. 启动开发服务器:  php long server                             ║
║    2. 启动 WebSocket:   php long ws start 8081 -d                  ║
║    3. 查看所有任务:    php long list                               ║
║    4. 执行一次:        php long run                                ║
║    5. 后台运行:        php long start                              ║
║    6. 生成控制器:      php long make:controller UserController     ║
╚══════════════════════════════════════════════════════════════════════╝
HELP;
    }
}