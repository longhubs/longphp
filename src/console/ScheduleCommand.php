<?php
// src/Console/ScheduleCommand.php

namespace Long\Console;

use Long\App;  // ⬅️ 添加引用

class ScheduleCommand extends Command
{
    protected $console;
    protected $scriptPath;
    protected $app;  // ⬅️ 添加 App 实例

    public function __construct($argv = [])
    {
        parent::__construct($argv);
        
        // ⬅️ 获取 App 实例
        $this->app = App::getInstance();
        
        // ✅ 使用框架的 getBasePath() 方法
        $this->scriptPath = $this->app->getBasePath('long');
        
        $consoleClass = '\\App\\console\\Console';
        if (class_exists($consoleClass)) {
            $this->console = new $consoleClass();
        }
    }

    public function run()
    {
        $command = $this->getArg(1, 'help');

        switch ($command) {
            case 'run':
                $this->runTasks();
                break;
            case 'list':
                $this->listTasks();
                break;
            case 'work':
                $this->workLoop();
                break;
            case 'start':
                $this->startDaemon();
                break;
            case 'stop':
                $this->stopDaemon();
                break;
            case 'status':
                $this->statusDaemon();
                break;
            case 'install':
                $this->showInstall();
                break;
            default:
                $this->showHelp();
        }
    }

    // ============================================================
    // 任务执行
    // ============================================================

    protected function runTasks()
    {
        if ($this->console) {
            $this->console->run();
            $this->success("定时任务执行完成");
        } else {
            $this->error("未找到 Console 类");
        }
    }

    protected function listTasks()
    {
        if (!$this->console) {
            $this->error("未找到 Console 类");
            return;
        }

        $tasks = $this->console->getTasks();
        echo "\n🐉 定时任务列表\n";
        echo str_repeat('─', 80) . "\n";
        printf("%-5s %-25s %-20s %-10s\n", '#', '任务名称', 'Cron 表达式', '启用');
        echo str_repeat('─', 80) . "\n";
        foreach ($tasks as $i => $task) {
            printf(
                "%-5d %-25s %-20s %-10s\n",
                $i + 1,
                $task->getName() ?: '未命名',
                $task->getExpression(),
                $task->isEnabled() ? '✅' : '❌'
            );
        }
        echo str_repeat('─', 80) . "\n";
        echo "总计: " . count($tasks) . " 个任务\n\n";
    }

    // ============================================================
    // 常驻运行
    // ============================================================

    protected function workLoop()
    {
        // ⭐ 写入 PID 文件
        $pidDir = $this->app->getRuntimePath();
        if (!is_dir($pidDir)) {
            mkdir($pidDir, 0755, true);
        }
        $pidFile = $pidDir . '/scheduler.pid';
        file_put_contents($pidFile, getmypid());
        
        // ⭐ 调试日志
        file_put_contents($pidDir . '/work_debug.log', "[" . date('Y-m-d H:i:s') . "] work 进程启动 (PID: " . getmypid() . ")\n", FILE_APPEND);

        $this->log("🚀 调度器已启动 (PID: " . getmypid() . ")，按 Ctrl+C 停止");
        
        while (true) {
            if ($this->console) {
                $this->console->run();
            }
            sleep(5);
        }
    }

    // ============================================================
    // 后台启动
    // ============================================================

    protected function startDaemon()
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            $this->startWindowsDaemon();
        } else {
            $this->startLinuxDaemon();
        }
    }

    protected function startWindowsDaemon()
    {
        // ⭐ 使用 App 管理路径
        $pidFile = $this->app->getRuntimePath('scheduler.pid');

        if (file_exists($pidFile)) {
            $pid = file_get_contents($pidFile);
            exec('tasklist /FI "PID eq ' . $pid . '" /FO CSV /NH 2>nul', $output);
            if (strpos($output[0] ?? '', 'php.exe') !== false) {
                $this->warning("调度器已在运行 (PID: $pid)");
                return;
            }
            @unlink($pidFile);
        }

        // 启动后台进程
        $cmd = 'start /B php ' . $this->scriptPath . ' work > nul 2>&1';
        pclose(popen($cmd, 'r'));

        $this->log("🔍 等待进程启动...");
        $wait = 0;
        while (!file_exists($pidFile) && $wait < 10) {
            sleep(1);
            $wait++;
            $this->log("🔍 等待中... ({$wait}/10)");
        }

        if (file_exists($pidFile)) {
            $pid = file_get_contents($pidFile);
            $this->success("调度器已在后台启动 (PID: $pid)");
        } else {
            $this->error("调度器启动失败");
        }
    }

    protected function startLinuxDaemon()
    {
        // ✅ 使用框架的 getRuntimePath() 方法
        $logDir = $this->app->getRuntimePath('logs');
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        $logFile = $logDir . '/scheduler.log';
        $cmd = 'nohup php ' . $this->scriptPath . ' work >> ' . $logFile . ' 2>&1 &';
        exec($cmd);
        $this->success("调度器已在后台启动 (Linux/Mac)");
        $this->log("日志文件: " . $logFile);
    }

    // ============================================================
    // 停止
    // ============================================================

    protected function stopDaemon()
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            $this->stopWindowsDaemon();
        } else {
            exec('pkill -f "php ' . $this->scriptPath . ' work"');
            $this->success("调度器已停止");
        }
    }

    protected function stopWindowsDaemon()
    {
        $pidFile = $this->app->getRuntimePath('scheduler.pid');

        if (file_exists($pidFile)) {
            $pid = file_get_contents($pidFile);
            exec('taskkill /F /PID ' . $pid . ' 2>nul');
            @unlink($pidFile);
            $this->success("调度器已停止 (PID: $pid)");
        } else {
            $this->warning("未找到运行中的调度器进程");
        }
    }

    // ============================================================
    // 状态
    // ============================================================

    protected function statusDaemon()
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            $this->statusWindowsDaemon();
        } else {
            exec('ps aux | grep "php ' . $this->scriptPath . ' work" | grep -v grep', $output);
            if (!empty($output)) {
                $this->log("✅ 调度器正在运行");
            } else {
                $this->log("❌ 调度器未运行");
            }
        }
    }

    protected function statusWindowsDaemon()
    {
        $pidFile = $this->app->getRuntimePath('scheduler.pid');

        if (file_exists($pidFile)) {
            $pid = file_get_contents($pidFile);
            exec('tasklist /FI "PID eq ' . $pid . '" /FO CSV /NH 2>nul', $output);
            if (strpos($output[0] ?? '', 'php.exe') !== false) {
                $this->log("✅ 调度器正在运行 (PID: $pid)");
                return;
            }
            @unlink($pidFile);
        }
        $this->log("❌ 调度器未运行");
    }

    // ============================================================
    // 安装
    // ============================================================

    protected function showInstall()
    {
        // ✅ 使用框架路径
        $rootPath = $this->app->getBasePath();
        $cronLogPath = $this->app->getRuntimePath('logs/cron.log');
        
        echo <<<CRON
📦 添加系统定时器（生产环境推荐）

1. 执行:
   crontab -e

2. 添加这一行（每分钟执行一次）:
   * * * * * cd {$rootPath} && php {$rootPath}/long run >> {$cronLogPath} 2>&1

3. 保存退出即可

查看日志:
   tail -f {$cronLogPath}

CRON;
    }

    // ============================================================
    // 帮助
    // ============================================================

    protected function showHelp()
    {
        echo <<<HELP
🐉 定时任务命令
──────────────────────────────────────────
  php long run        执行所有到期任务
  php long list       列出所有已注册的任务
  php long work       常驻运行模式（开发调试）
  php long start      后台启动调度器（生产环境）
  php long stop       停止后台调度器
  php long status     查看调度器状态
  php long install    安装系统 Cron
HELP;
    }
}