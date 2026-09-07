<?php
// src/WebSocketManager.php
// LongPHP WebSocket 服务管理器
// 负责自动发现、启动、停止、状态管理

namespace Long;

use Long\App;  // ⬅️ 添加引用

class WebSocketManager
{
    protected $handlers = [];
    
    /**
     * @var App
     */
    protected $app;  // ⬅️ 添加 App 实例
    
    /**
     * @var string
     */
    protected $basePath;  // ⬅️ 添加项目根目录

    public function __construct()
    {
        // ⬅️ 获取 App 实例
        $this->app = App::getInstance();
        $this->basePath = $this->app->getBasePath();
        $this->discoverHandlers();
    }

    /**
     * 自动发现 app/websocket/ 目录下的所有处理器
     * 命名空间与目录大小写完全一致：app\websocket
     */
    protected function discoverHandlers()
    {
        $this->handlers = [];

        // ✅ 使用框架的 getAppPath() 方法
        $webSocketDir = $this->app->getAppPath('websocket');
        
        echo "🔍 扫描目录: " . $webSocketDir . "\n";
        echo "🔍 目录是否存在: " . (is_dir($webSocketDir) ? '✅ 是' : '❌ 否') . "\n";
        
        if (!is_dir($webSocketDir)) {
            return;
        }

        $files = glob($webSocketDir . '/*.php');
        echo "🔍 找到文件: " . count($files) . " 个\n";
        
        foreach ($files as $file) {
            echo "🔍 处理文件: " . $file . "\n";
            
            $className = 'app\\websocket\\' . pathinfo($file, PATHINFO_FILENAME);
            echo "🔍 类名: " . $className . "\n";

            require_once $file;

            if (!class_exists($className)) {
                echo "🔍 ❌ 类不存在\n";
                continue;
            }
            echo "🔍 ✅ 类存在\n";

            if (!is_subclass_of($className, '\\Long\\WebSocketWorker')) {
                echo "🔍 ❌ 不是 WebSocketWorker 的子类\n";
                continue;
            }
            echo "🔍 ✅ 是 WebSocketWorker 的子类\n";

            if (!defined("{$className}::PORT")) {
                echo "🔍 ❌ 没有定义 PORT 常量\n";
                continue;
            }
            echo "🔍 ✅ 定义了 PORT 常量\n";
            
            $port = $className::PORT;
            echo "🔍 端口: " . $port . "\n";
            
            $this->handlers[$className] = $port;
            echo "✅ 发现处理器: {$className} -> 端口 {$port}\n";
        }
        
        echo "🔍 总共发现: " . count($this->handlers) . " 个处理器\n";
    }

    /**
     * 获取指定端口的处理器
     */
    public function getHandlerForPort($port)
    {
        foreach ($this->handlers as $handler => $p) {
            if ($p == $port) {
                return $handler;
            }
        }
        return null;
    }

    /**
     * 获取所有处理器
     */
    public function getAllHandlers()
    {
        return $this->handlers;
    }

    /**
     * 获取 WebSocket 全局配置
     */
    protected function getConfig()
    {
        // ✅ 使用框架的 getConfigPath() 方法
        $configFile = $this->app->getConfigPath('app.php');
        if (file_exists($configFile)) {
            $appConfig = require $configFile;
            return $appConfig['websocket'] ?? [];
        }
        return [];
    }

    /**
     * 获取 PID 文件路径
     */
    protected function getPidFile($port)
    {
        // ✅ 使用框架的 getRuntimePath() 方法
        $pidFile = $this->app->getRuntimePath('websocket_' . $port . '.pid');
        return $pidFile;
    }

    /**
     * 获取日志文件路径
     */
    protected function getLogFile($port)
    {
        // ✅ 使用框架的 getRuntimePath() 方法
        $logFile = $this->app->getRuntimePath('websocket_' . $port . '.log');
        return $logFile;
    }

    // ============================================================
    // 服务管理
    // ============================================================

    /**
     * 启动指定端口的服务
     */
    public function start($port = null, $daemon = true)
    {
        if ($port === null) {
            return $this->startAll($daemon);
        }

        $handlerClass = $this->getHandlerForPort($port);
        if (!$handlerClass) {
            return "❌ 未找到端口 {$port} 对应的处理器\n";
        }

        $pidFile = $this->getPidFile($port);
        
        // 检查是否已运行
        if ($this->isRunning($port)) {
            $pid = file_get_contents($pidFile);
            return "⏭️  端口 {$port} 已在运行 (PID: {$pid})\n";
        }

        $config = array_merge($this->getConfig(), [
            'port' => $port,
            'pid_file' => $pidFile,
            'log_file' => $this->getLogFile($port),
        ]);

        $server = new $handlerClass($config);
        
        if ($daemon) {
            ob_start();
            $server->startDaemon();
            $output = ob_get_clean();
            sleep(1);
            if ($this->isRunning($port)) {
                $pid = file_get_contents($pidFile);
                return "✅ 端口 {$port} 已启动 (PID: {$pid})\n";
            } else {
                return "❌ 端口 {$port} 启动失败\n" . $output;
            }
        } else {
            // 前台模式
            $server->start();
            return '';
        }
    }

    /**
     * 启动所有服务
     */
    public function startAll($daemon = true)
    {
        if (empty($this->handlers)) {
            return "❌ 未发现任何 WebSocket 处理器\n";
        }

        $result = "🐉 自动启动所有 WebSocket 服务...\n";
        $result .= str_repeat('─', 50) . "\n";

        $started = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($this->handlers as $handler => $port) {
            $pidFile = $this->getPidFile($port);
            $logFile = $this->getLogFile($port);
            
            // 调试：打印路径
            $result .= "🔍 端口 {$port} PID文件: {$pidFile}\n";
            
            if ($this->isRunning($port)) {
                $pid = file_get_contents($pidFile);
                $result .= "  ⏭️  端口 {$port} 已在运行 (PID: {$pid})\n";
                $skipped++;
                continue;
            }

            $result .= "  🚀 启动端口 {$port} ({$handler})...";
            
            $config = array_merge($this->getConfig(), [
                'port' => $port,
                'pid_file' => $pidFile,
                'log_file' => $logFile,
            ]);

            $server = new $handler($config);
            $server->startDaemon();
            
            // 等待 PID 文件生成
            $wait = 0;
            while (!file_exists($pidFile) && $wait < 10) {
                sleep(1);
                $wait++;
                $result .= ".";
            }
            
            if (file_exists($pidFile)) {
                $pid = file_get_contents($pidFile);
                $result .= " ✅ (PID: {$pid})\n";
                $started++;
            } else {
                $result .= " ❌ 启动失败\n";
                if (file_exists($logFile)) {
                    $result .= "     日志: " . file_get_contents($logFile) . "\n";
                }
                $failed++;
            }
        }

        $result .= str_repeat('─', 50) . "\n";
        $result .= "📊 启动完成: 成功 {$started} 个, 已运行 {$skipped} 个" . ($failed ? ", 失败 {$failed} 个" : "") . "\n";
        
        return $result;
    }

    /**
     * 停止指定端口的服务
     */
    public function stop($port = null)
    {
        if ($port === null) {
            return $this->stopAll();
        }

        $pidFile = $this->getPidFile($port);
        $result = "";
        
        // 方法1：从 PID 文件读取
        if (file_exists($pidFile)) {
            $pid = file_get_contents($pidFile);
            $pid = trim($pid);
            if ($this->killProcess($pid)) {
                @unlink($pidFile);
                $result .= "✅ 已停止端口 {$port} (PID: {$pid})\n";
            } else {
                $result .= "⚠️ 端口 {$port} 进程不存在 (PID: {$pid})\n";
                @unlink($pidFile);
            }
        }
        
        // 方法2：通过端口杀（兜底）
        if (DIRECTORY_SEPARATOR === '\\') {
            $cmd = "netstat -ano | findstr :{$port} | findstr LISTENING";
            exec($cmd, $output);
            foreach ($output as $line) {
                if (preg_match('/LISTENING\s+(\d+)/', $line, $matches)) {
                    $pid = $matches[1];
                    exec('taskkill /F /PID ' . $pid . ' 2>nul');
                    $result .= "✅ 强制停止端口 {$port} (PID: {$pid})\n";
                    break;
                }
            }
        } else {
            $cmd = "netstat -tlnp 2>/dev/null | grep ':{$port}' | grep LISTEN | awk '{print \$7}' | cut -d'/' -f1";
            exec($cmd, $pidOutput);
            if (!empty($pidOutput)) {
                $pid = trim($pidOutput[0]);
                if ($pid) {
                    exec('kill -9 ' . $pid . ' 2>/dev/null');
                    $result .= "✅ 强制停止端口 {$port} (PID: {$pid})\n";
                }
            }
        }
        
        // 清理 PID 文件
        if (file_exists($pidFile)) {
            @unlink($pidFile);
        }
        
        if (empty($result)) {
            $result = "⚠️ 端口 {$port} 未运行\n";
        }
        
        return $result;
    }

    /**
     * 停止所有服务
     */
    public function stopAll()
    {
        $result = "🛑 停止所有 WebSocket 服务...\n";
        $stopped = 0;
        
        // ============================================================
        // 方法1：从 PID 文件读取并杀
        // ============================================================
        // ✅ 使用框架的 getRuntimePath() 方法扫描 PID 文件
        $pidFiles = glob($this->app->getRuntimePath('websocket_*.pid'));
        foreach ($pidFiles as $file) {
            $pid = file_get_contents($file);
            $pid = trim($pid);
            $port = str_replace(['websocket_', '.pid'], '', basename($file));
            
            if ($pid && $this->killProcess($pid)) {
                $result .= "  ✅ 停止端口 {$port} (PID: {$pid})\n";
                $stopped++;
            } else {
                $result .= "  ⏭️ 端口 {$port} 进程不存在 (PID: {$pid})\n";
            }
            @unlink($file);
        }
        
        // ============================================================
        // 方法2：通过端口查找并杀（从 handlers 获取端口列表）
        // ============================================================
        $ports = array_values($this->handlers);  // ⭐ 动态获取所有端口
        
        if (DIRECTORY_SEPARATOR === '\\') {
            // Windows
            exec('netstat -ano', $output);
            foreach ($output as $line) {
                foreach ($ports as $port) {
                    if (strpos($line, ":$port") !== false && strpos($line, 'LISTENING') !== false) {
                        if (preg_match('/LISTENING\s+(\d+)/', $line, $matches)) {
                            $pid = $matches[1];
                            exec('taskkill /F /PID ' . $pid . ' 2>nul');
                            $result .= "  ✅ 强制停止端口 {$port} (PID: {$pid})\n";
                            $stopped++;
                            break;
                        }
                    }
                }
            }
        } else {
            // Linux ⭐ 动态 pkill
            exec('pkill -9 -f "php.*ws start" 2>/dev/null', $output, $returnCode);
            if ($returnCode === 0) {
                $result .= "  ✅ 已强制清理所有 WebSocket 进程\n";
            }
            
            // 通过端口杀（动态）
            foreach ($ports as $port) {
                $cmd = "netstat -tlnp 2>/dev/null | grep ':{$port}' | grep LISTEN | awk '{print \$7}' | cut -d'/' -f1";
                exec($cmd, $pidOutput);
                if (!empty($pidOutput)) {
                    $pid = trim($pidOutput[0]);
                    if ($pid) {
                        exec('kill -9 ' . $pid . ' 2>/dev/null');
                        $result .= "  ✅ 强制停止端口 {$port} (PID: {$pid})\n";
                        $stopped++;
                    }
                }
            }
        }
        
        // ============================================================
        // 方法3：清理所有 PID 文件
        // ============================================================
        $pidFiles = glob($this->app->getRuntimePath('websocket_*.pid'));
        foreach ($pidFiles as $file) {
            @unlink($file);
        }
        
        sleep(1);
        $result .= "📊 已停止 {$stopped} 个服务\n";
        return $result;
    }

    /**
     * 查看状态
     */
    public function status()
    {
        if (empty($this->handlers)) {
            return "❌ 未发现任何 WebSocket 处理器\n";
        }

        $result = "🐉 WebSocket 服务状态\n";
        $result .= str_repeat('─', 50) . "\n";

        $running = 0;
        foreach ($this->handlers as $handler => $port) {
            if ($this->isRunning($port)) {
                $pidFile = $this->getPidFile($port);
                $pid = file_get_contents($pidFile);
                $result .= "  ✅ 端口 {$port} 运行中 (PID: {$pid})  [{$handler}]\n";
                $running++;
            } else {
                $result .= "  ❌ 端口 {$port} 未运行  [{$handler}]\n";
            }
        }

        $result .= str_repeat('─', 50) . "\n";
        $result .= "📊 运行中: {$running} / " . count($this->handlers) . "\n";
        
        return $result;
    }

    /**
     * 重启服务
     */
    public function restart($port = null)
    {
        if ($port === null) {
            $this->stopAll();
            sleep(2);
            return $this->startAll();
        } else {
            $this->stop($port);
            sleep(2);
            return $this->start($port);
        }
    }

    // ============================================================
    // 辅助方法
    // ============================================================

    /**
     * 检查服务是否在运行
     */
    protected function isRunning($port)
    {
        $pidFile = $this->getPidFile($port);
        if (!file_exists($pidFile)) {
            return false;
        }
        $pid = file_get_contents($pidFile);
        return $this->processExists($pid);
    }

    /**
     * 检查进程是否存在
     */
    protected function processExists($pid)
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            $check = shell_exec("tasklist /FI \"PID eq {$pid}\" 2>nul");
            return strpos($check, $pid) !== false;
        } else {
            return function_exists('posix_kill') && posix_kill($pid, 0);
        }
    }

    /**
     * 杀掉进程
     */
    protected function killProcess($pid)
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            shell_exec("taskkill /F /PID {$pid} 2>nul");
            return true;
        } else {
            if (function_exists('posix_kill') && posix_kill($pid, 0)) {
                posix_kill($pid, SIGTERM);
                $wait = 0;
                while (posix_kill($pid, 0) && $wait < 5) {
                    sleep(1);
                    $wait++;
                }
                if (posix_kill($pid, 0)) {
                    posix_kill($pid, SIGKILL);
                }
                return true;
            }
            return false;
        }
    }
}