<?php
// src/WebSocketWorker.php
// LongPHP WebSocket 多进程服务 - Master-Worker 模式
// 龙行天下 🐉

namespace Long;

use Long\App;

class WebSocketWorker
{
    // ============================================================
    // 配置
    // ============================================================

    protected $config = [
        'host' => '0.0.0.0',
        'port' => 8080,
        'workers' => 4,
        'max_connections' => 100000,
        'heartbeat' => 30,
        'timeout' => 60,
        'max_memory' => 128 * 1024 * 1024,
        'daemonize' => false,
        'pid_file' => '/tmp/websocket.pid',
        'log_file' => '/tmp/websocket.log',
    ];

    protected $workers = [];
    protected $running = true;
    protected $masterPid;
    protected $server;  // ⭐ Master 的 socket

    /**
     * @var App
     */
    protected $app;

    /**
     * @var string
     */
    protected $basePath;

    public function __construct($config = [])
    {
        // 获取 App 实例
        $this->app = App::getInstance();
        $this->basePath = $this->app->getBasePath();

        $this->config = array_merge($this->config, $config);

        // 使用框架的 getConfigPath() 方法
        $configFile = $this->app->getConfigPath('app.php');
        if (file_exists($configFile)) {
            $appConfig = require $configFile;
            $wsConfig = $appConfig['websocket'] ?? [];
            foreach ($wsConfig as $key => $value) {
                if (!isset($config[$key])) {
                    $this->config[$key] = $value;
                }
            }
        }

        // 确保目录存在
        $pidDir = dirname($this->config['pid_file']);
        if (!is_dir($pidDir)) {
            @mkdir($pidDir, 0777, true);
        }
        $logDir = dirname($this->config['log_file']);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }
    }

    /**
     * 检测是否支持多进程
     */
    protected function isMultiProcessSupported()
    {
        return function_exists('pcntl_fork') 
            && function_exists('pcntl_signal') 
            && function_exists('posix_getpid');
    }

    // ============================================================
    // 入口方法
    // ============================================================

    /**
     * 前台启动（调试模式）
     */
    public function start()
    {
        $this->log("🔍 [DEBUG] start() 被调用");
        $this->log("🔍 [DEBUG] pid_file: " . $this->config['pid_file']);
        $this->log("🔍 [DEBUG] log_file: " . $this->config['log_file']);
        
        if ($this->config['pid_file']) {
            $pidDir = dirname($this->config['pid_file']);
            if (!is_dir($pidDir)) {
                @mkdir($pidDir, 0777, true);
            }
            file_put_contents($this->config['pid_file'], getmypid());
        }

        $this->masterPid = getmypid();

        if ($this->isMultiProcessSupported() && $this->config['workers'] > 1) {
            $this->log("🐉 LongPHP WebSocket Master-Worker 模式");
            $this->multiProcessStart();
        } else {
            $this->log("⚠️ 单进程模式", 'warning');
            $this->singleProcessStart();
        }
    }

    /**
     * 后台启动（守护进程模式）
     */
    public function startDaemon()
    {
        $this->log("🔍 [DEBUG] startDaemon() 被调用");
        $this->log("🔍 [DEBUG] 操作系统: " . (DIRECTORY_SEPARATOR === '\\' ? 'Windows' : 'Linux/Unix'));
        $this->log("🔍 [DEBUG] 端口: " . $this->config['port']);
        $this->log("🔍 [DEBUG] pid_file: " . $this->config['pid_file']);
        $this->log("🔍 [DEBUG] log_file: " . $this->config['log_file']);
        
        if (DIRECTORY_SEPARATOR === '\\') {
            $this->startWindowsDaemon();
        } else {
            $this->startLinuxDaemon();
        }
    }

    public function stopDaemon()
    {
        $pidFile = $this->config['pid_file'];
        if (!file_exists($pidFile)) {
            $this->log("❌ 服务未运行", 'error');
            return;
        }

        $pid = file_get_contents($pidFile);
        $pid = trim($pid);

        if (DIRECTORY_SEPARATOR === '\\') {
            $check = shell_exec("tasklist /FI \"PID eq {$pid}\" 2>nul");
            if (strpos($check, $pid) !== false) {
                shell_exec("taskkill /F /PID {$pid} 2>nul");
                $this->log("✅ 服务已停止（PID: {$pid}）");
            }
        } else {
            if (function_exists('posix_kill') && posix_kill($pid, 0)) {
                posix_kill($pid, SIGTERM);
                $wait = 0;
                while (function_exists('posix_kill') && posix_kill($pid, 0) && $wait < 5) {
                    sleep(1);
                    $wait++;
                }
                if (function_exists('posix_kill') && posix_kill($pid, 0)) {
                    posix_kill($pid, SIGKILL);
                }
                $this->log("✅ 服务已停止（PID: {$pid}）");
            } else {
                $this->log("❌ 服务未运行（PID: {$pid} 不存在）", 'warning');
            }
        }
        @unlink($pidFile);
    }

    public function statusDaemon()
    {
        $pidFile = $this->config['pid_file'];
        if (!file_exists($pidFile)) {
            $this->log("❌ 服务未运行");
            return;
        }

        $pid = file_get_contents($pidFile);
        $pid = trim($pid);

        if (DIRECTORY_SEPARATOR === '\\') {
            $check = shell_exec("tasklist /FI \"PID eq {$pid}\" 2>nul");
            if (strpos($check, $pid) !== false) {
                $this->log("✅ 服务运行中（PID: {$pid}）");
                $this->log("   端口: {$this->config['port']}");
                $this->log("   日志: {$this->config['log_file']}");
            } else {
                $this->log("❌ 服务未运行");
                @unlink($pidFile);
            }
        } else {
            if (function_exists('posix_kill') && posix_kill($pid, 0)) {
                $this->log("✅ 服务运行中（PID: {$pid}）");
                $this->log("   端口: {$this->config['port']}");
                $this->log("   日志: {$this->config['log_file']}");
            } else {
                $this->log("❌ 服务未运行");
                @unlink($pidFile);
            }
        }
    }

    public function restartDaemon()
    {
        $this->log("🔄 正在重启 WebSocket 服务...");
        $this->stopDaemon();
        sleep(2);
        $this->startDaemon();
    }

    // ============================================================
    // Linux 守护进程
    // ============================================================

    protected function startLinuxDaemon()
    {
        // 使用框架路径
        $script = $this->basePath . '/long';
        $logFile = $this->config['log_file'];
        $pidFile = $this->config['pid_file'];

        $this->log("🔍 [DEBUG] basePath: " . $this->basePath);
        $this->log("🔍 [DEBUG] script路径: " . $script);
        $this->log("🔍 [DEBUG] pidFile: " . $pidFile);
        $this->log("🔍 [DEBUG] logFile: " . $logFile);

        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        $pidDir = dirname($pidFile);
        if (!is_dir($pidDir)) {
            @mkdir($pidDir, 0755, true);
        }

        if (file_exists($pidFile)) {
            $pid = file_get_contents($pidFile);
            if (function_exists('posix_kill') && posix_kill($pid, 0)) {
                $this->log("❌ 服务已在运行（PID: {$pid}）", 'error');
                return;
            }
            @unlink($pidFile);
        }

        if (!file_exists($script)) {
            $this->log("❌ 脚本文件不存在: " . $script, 'error');
            return;
        }

        // ⭐ 直接后台启动
        $cmd = "nohup php {$script} ws start {$this->config['port']} --pid {$pidFile} >> {$logFile} 2>&1 &";
        $this->log("🔍 [DEBUG] 后台命令: " . $cmd);
        exec($cmd);

        $wait = 0;
        while (!file_exists($pidFile) && $wait < 10) {
            sleep(1);
            $wait++;
            $this->log("🔍 [DEBUG] 等待 PID 文件... ({$wait}s)");
        }

        if (file_exists($pidFile)) {
            $pid = file_get_contents($pidFile);
            $this->log("✅ 守护进程已启动（PID: {$pid}）");
            $this->log("📝 日志: {$logFile}");
            sleep(1);
            if (function_exists('posix_kill') && posix_kill($pid, 0)) {
                $this->log("✅ 进程验证通过");
            }
        } else {
            $this->log("❌ PID 文件未生成", 'error');
            if (file_exists($logFile)) {
                $this->log("📌 日志内容:\n" . file_get_contents($logFile), 'error');
            }
        }
    }

    // ============================================================
    // Windows 守护进程
    // ============================================================

    protected function startWindowsDaemon()
    {
        // 使用框架路径
        $script = $this->basePath . '\\long';
        $logFile = str_replace('/', '\\', $this->config['log_file']);
        $pidFile = str_replace('/', '\\', $this->config['pid_file']);

        $this->log("🔍 [DEBUG] basePath: " . $this->basePath);
        $this->log("🔍 [DEBUG] script路径: " . $script);
        $this->log("🔍 [DEBUG] pidFile: " . $pidFile);
        $this->log("🔍 [DEBUG] logFile: " . $logFile);

        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }
        $pidDir = dirname($pidFile);
        if (!is_dir($pidDir)) {
            @mkdir($pidDir, 0777, true);
        }

        if (file_exists($pidFile)) {
            $pid = file_get_contents($pidFile);
            $check = shell_exec("tasklist /FI \"PID eq {$pid}\" 2>nul");
            if (strpos($check, $pid) !== false) {
                $this->log("❌ 服务已在运行（PID: {$pid}）", 'error');
                return;
            }
            @unlink($pidFile);
        }

        if (!file_exists($script)) {
            $this->log("❌ 脚本文件不存在: " . $script, 'error');
            return;
        }

        $cmd = 'start /B php "' . $script . '" ws start ' . $this->config['port'] . ' --pid "' . $pidFile . '" > "' . $logFile . '" 2>&1';
        $this->log("🔍 [DEBUG] 后台命令: " . $cmd);
        pclose(popen($cmd, 'r'));

        $wait = 0;
        while (!file_exists($pidFile) && $wait < 10) {
            sleep(1);
            $wait++;
            $this->log("🔍 [DEBUG] 等待 PID 文件... ({$wait}s)");
        }

        if (file_exists($pidFile)) {
            $pid = file_get_contents($pidFile);
            $this->log("✅ 守护进程已启动（PID: {$pid}）");
            $this->log("📝 日志: {$logFile}");
        } else {
            $this->log("❌ PID 文件未生成", 'error');
            if (file_exists($logFile)) {
                $this->log("📌 日志内容:\n" . file_get_contents($logFile), 'error');
            }
        }
    }

    // ============================================================
    // Master-Worker 多进程模式（Linux）
    // ============================================================

    protected function multiProcessStart()
    {
        $this->log("端口: {$this->config['port']}");
        $this->log("进程数: {$this->config['workers']}");
        $this->log("──────────────────────────────────────────");

        // ⭐ 1. Master 创建 socket，绑定端口
        $this->server = $this->createServer();
        if (!$this->server) {
            return;
        }

        // 安装信号
        pcntl_signal(SIGINT, [$this, 'shutdown']);
        pcntl_signal(SIGTERM, [$this, 'shutdown']);

        // ⭐ 2. 启动 Worker 子进程
        for ($i = 0; $i < $this->config['workers']; $i++) {
            $this->startWorker($i);
        }

        // ⭐ 3. Master 监控子进程
        $this->monitor();
    }

    /**
     * Master 创建 socket
     */
    protected function createServer()
    {
        $server = @stream_socket_server(
            "tcp://{$this->config['host']}:{$this->config['port']}",
            $errno,
            $errstr,
            STREAM_SERVER_BIND | STREAM_SERVER_LISTEN
        );

        if (!$server) {
            $this->log("❌ 启动失败: [{$errno}] {$errstr}", 'error');
            $this->log("📌 可能原因：端口 {$this->config['port']} 已被占用", 'error');
            return null;
        }

        stream_set_blocking($server, false);
        return $server;
    }

    protected function startWorker($id)
    {
        $pid = pcntl_fork();

        if ($pid == -1) {
            $this->log("❌ 创建进程失败", 'error');
            return;
        }

        if ($pid == 0) {
            // ⭐ 子进程：使用 Master 的 socket
            $this->workerLoop($id);
            exit(0);
        }

        // Master 记录子进程
        $this->workers[$pid] = [
            'id' => $id,
            'pid' => $pid,
            'start_time' => time(),
        ];

        $this->log("✅ 工作进程启动: PID={$pid}, ID={$id}");
    }

    protected function workerLoop($id)
    {
        if (function_exists('cli_set_process_title')) {
            cli_set_process_title("longphp-ws-{$id}");
        }
        
        // ⭐ 子进程使用 Master 的 socket 处理连接
        $this->runWorker();
        exit(0);
    }

    /**
     * Worker 处理连接（不绑定端口，使用 Master 的 socket）
     */
    protected function runWorker()
    {
        $clients = [];
        $handshakes = [];
        $lastPing = [];
        $lastHeartbeat = time();
        $lastGc = time();

        // ⭐ 子进程使用 Master 的 socket
        $server = $this->server;

        while (true) {
            $read = array_values($clients);
            $read[] = $server;
            $write = null;
            $except = null;

            if (stream_select($read, $write, $except, 0, 100000) > 0) {
                foreach ($read as $stream) {
                    // 新连接
                    if ($stream === $server) {
                        if (count($clients) >= $this->config['max_connections']) {
                            $client = @stream_socket_accept($server, 0);
                            if ($client) {
                                fwrite($client, "HTTP/1.1 503 Service Unavailable\r\n\r\n");
                                fclose($client);
                            }
                            continue;
                        }

                        $client = @stream_socket_accept($server, 0);
                        if ($client) {
                            stream_set_blocking($client, false);
                            $fd = (int)$client;
                            $clients[$fd] = $client;
                            $handshakes[$fd] = false;
                            $lastPing[$fd] = time();
                            $this->log("✅ 新连接: {$fd}");
                            $this->onConnect($fd);
                        }
                        continue;
                    }

                    // 查找 fd
                    $fd = null;
                    foreach ($clients as $f => $c) {
                        if ($c === $stream) {
                            $fd = $f;
                            break;
                        }
                    }
                    if ($fd === null) continue;

                    $data = @fread($stream, 8192);

                    if ($data === false || $data === '') {
                        $this->closeClient($fd, $clients, $handshakes, $lastPing);
                        continue;
                    }

                    $lastPing[$fd] = time();

                    // 握手
                    if (!$handshakes[$fd]) {
                        if (preg_match("/Sec-WebSocket-Key: (.*)\r\n/", $data, $m)) {
                            $accept = base64_encode(sha1(trim($m[1]) . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
                            $response = "HTTP/1.1 101 Switching Protocols\r\n";
                            $response .= "Upgrade: websocket\r\n";
                            $response .= "Connection: Upgrade\r\n";
                            $response .= "Sec-WebSocket-Accept: {$accept}\r\n\r\n";
                            fwrite($stream, $response);
                            $handshakes[$fd] = true;
                            $this->log("🤝 握手: {$fd}");
                            $this->onHandshake($fd);
                        }
                        continue;
                    }

                    // 解码消息
                    $msg = $this->decode($data);
                    if ($msg !== '') {
                        $this->log("📩 [{$fd}]: {$msg}");
                        $this->onMessage($fd, $msg, $stream, $clients);
                    }
                }
            }

            // 心跳检测
            $now = time();
            if ($now - $lastHeartbeat >= $this->config['heartbeat']) {
                foreach ($clients as $fd => $stream) {
                    if ($now - $lastPing[$fd] > $this->config['timeout']) {
                        $this->closeClient($fd, $clients, $handshakes, $lastPing);
                    }
                }
                $lastHeartbeat = $now;
            }

            // 内存回收
            if ($now - $lastGc > 300) {
                gc_collect_cycles();
                $lastGc = $now;
            }

            // 内存监控
            if (memory_get_usage(true) > $this->config['max_memory']) {
                $this->log("⚠️ 内存超限: " . $this->formatBytes(memory_get_usage(true)), 'warning');
                gc_collect_cycles();
            }

            usleep(10000);
        }
    }

    protected function closeClient($fd, &$clients, &$handshakes, &$lastPing)
    {
        if (isset($clients[$fd])) {
            @fclose($clients[$fd]);
            unset($clients[$fd]);
        }
        unset($handshakes[$fd]);
        unset($lastPing[$fd]);
        $this->log("❌ 断开: {$fd}");
        $this->onClose($fd);
    }

    protected function monitor()
    {
        while (true) {
            $status = 0;
            $pid = pcntl_wait($status, WNOHANG);

            if ($pid > 0) {
                $this->log("🔄 进程退出: PID={$pid}，重启中...", 'warning');
                unset($this->workers[$pid]);
                sleep(1);
                $this->startWorker(0);
            }

            pcntl_signal_dispatch();
            sleep(1);
        }
    }

    public function shutdown()
    {
        $this->log("🛑 关闭服务...");
        foreach ($this->workers as $pid => $info) {
            posix_kill($pid, SIGTERM);
        }
        if ($this->server) {
            @fclose($this->server);
        }
        if ($this->config['pid_file'] && file_exists($this->config['pid_file'])) {
            @unlink($this->config['pid_file']);
        }
        exit(0);
    }

    // ============================================================
    // 单进程模式（Windows / 不支持多进程）
    // ============================================================

    protected function singleProcessStart()
    {
        $this->log("端口: {$this->config['port']}");
        $this->log("──────────────────────────────────────────");

        register_shutdown_function(function() {
            $this->log("服务已关闭");
            if ($this->config['pid_file'] && file_exists($this->config['pid_file'])) {
                @unlink($this->config['pid_file']);
            }
        });

        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGINT, function() { $this->running = false; });
            pcntl_signal(SIGTERM, function() { $this->running = false; });
        }

        // 单进程直接运行服务
        $server = @stream_socket_server(
            "tcp://{$this->config['host']}:{$this->config['port']}",
            $errno,
            $errstr,
            STREAM_SERVER_BIND | STREAM_SERVER_LISTEN
        );

        if (!$server) {
            $this->log("❌ 启动失败: [{$errno}] {$errstr}", 'error');
            return;
        }

        stream_set_blocking($server, false);
        $this->runSingleServer($server);
    }

    protected function runSingleServer($server)
    {
        $clients = [];
        $handshakes = [];
        $lastPing = [];
        $lastHeartbeat = time();
        $lastGc = time();

        $this->log("✅ 服务启动成功");
        $this->log("🌐 地址: ws://{$this->config['host']}:{$this->config['port']}");
        $this->log("按 Ctrl+C 停止\n");

        while ($this->running) {
            $read = array_values($clients);
            $read[] = $server;
            $write = null;
            $except = null;

            if (stream_select($read, $write, $except, 0, 100000) > 0) {
                foreach ($read as $stream) {
                    if ($stream === $server) {
                        if (count($clients) >= $this->config['max_connections']) {
                            $client = @stream_socket_accept($server, 0);
                            if ($client) {
                                fwrite($client, "HTTP/1.1 503 Service Unavailable\r\n\r\n");
                                fclose($client);
                            }
                            continue;
                        }

                        $client = @stream_socket_accept($server, 0);
                        if ($client) {
                            stream_set_blocking($client, false);
                            $fd = (int)$client;
                            $clients[$fd] = $client;
                            $handshakes[$fd] = false;
                            $lastPing[$fd] = time();
                            $this->onConnect($fd);
                        }
                        continue;
                    }

                    $fd = null;
                    foreach ($clients as $f => $c) {
                        if ($c === $stream) {
                            $fd = $f;
                            break;
                        }
                    }
                    if ($fd === null) continue;

                    $data = @fread($stream, 8192);
                    if ($data === false || $data === '') {
                        $this->closeClient($fd, $clients, $handshakes, $lastPing);
                        continue;
                    }

                    $lastPing[$fd] = time();

                    if (!$handshakes[$fd]) {
                        if (preg_match("/Sec-WebSocket-Key: (.*)\r\n/", $data, $m)) {
                            $accept = base64_encode(sha1(trim($m[1]) . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
                            $response = "HTTP/1.1 101 Switching Protocols\r\n";
                            $response .= "Upgrade: websocket\r\n";
                            $response .= "Connection: Upgrade\r\n";
                            $response .= "Sec-WebSocket-Accept: {$accept}\r\n\r\n";
                            fwrite($stream, $response);
                            $handshakes[$fd] = true;
                            $this->onHandshake($fd);
                        }
                        continue;
                    }

                    $msg = $this->decode($data);
                    if ($msg !== '') {
                        $this->onMessage($fd, $msg, $stream, $clients);
                    }
                }
            }

            $now = time();
            if ($now - $lastHeartbeat >= $this->config['heartbeat']) {
                foreach ($clients as $fd => $stream) {
                    if ($now - $lastPing[$fd] > $this->config['timeout']) {
                        $this->closeClient($fd, $clients, $handshakes, $lastPing);
                    }
                }
                $lastHeartbeat = $now;
            }

            if ($now - $lastGc > 300) {
                gc_collect_cycles();
                $lastGc = $now;
            }

            if (memory_get_usage(true) > $this->config['max_memory']) {
                gc_collect_cycles();
            }

            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }

            usleep(10000);
        }

        foreach ($clients as $stream) {
            @fclose($stream);
        }
        @fclose($server);
        $this->log("服务已关闭");
        
        if ($this->config['pid_file'] && file_exists($this->config['pid_file'])) {
            @unlink($this->config['pid_file']);
        }
    }

    // ============================================================
    // WebSocket 协议
    // ============================================================

    protected function decode($data)
    {
        $len = ord($data[1]) & 127;
        $mask = substr($data, 2, 4);
        $payload = substr($data, 6);
        $msg = '';
        for ($i = 0; $i < strlen($payload); $i++) {
            $msg .= $payload[$i] ^ $mask[$i % 4];
        }
        return $msg;
    }

    protected function encode($msg)
    {
        $len = strlen($msg);
        $frame = chr(0x81);

        if ($len <= 125) {
            $frame .= chr($len);
        } elseif ($len <= 65535) {
            $frame .= chr(126) . pack('n', $len);
        } else {
            $frame .= chr(127) . pack('J', $len);
        }

        $frame .= $msg;
        return $frame;
    }

    protected function send($stream, $data)
    {
        if (is_array($data) || is_object($data)) {
            $data = json_encode($data, JSON_UNESCAPED_UNICODE);
        }
        return @fwrite($stream, $this->encode($data));
    }

    protected function broadcast($data, $clients, $except = null)
    {
        foreach ($clients as $fd => $stream) {
            if ($except !== null && $fd == $except) continue;
            $this->send($stream, $data);
        }
    }

    // ============================================================
    // 工具方法
    // ============================================================

    protected function formatBytes($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < 3) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }

    protected function log($msg, $level = 'info')
    {
        $time = date('Y-m-d H:i:s');
        $logMsg = "[{$time}] [{$level}] {$msg}";
        echo $logMsg . "\n";

        if ($this->config['log_file']) {
            @file_put_contents($this->config['log_file'], $logMsg . "\n", FILE_APPEND);
        }
    }

    // ============================================================
    // 事件钩子（子类重写）
    // ============================================================

    protected function onConnect($fd)
    {
        // 连接建立
    }

    protected function onHandshake($fd)
    {
        // 握手完成
    }

    protected function onMessage($fd, $msg, $stream, $clients)
    {
        $this->broadcast($msg, $clients);
    }

    protected function onClose($fd)
    {
        // 连接关闭
    }
}