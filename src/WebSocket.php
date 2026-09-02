<?php
// src/WebSocket.php
// LongPHP WebSocket 服务 - 生产级版本
// 龙行天下 🐉

namespace Long;

class WebSocket
{
    // ============================================================
    // 配置
    // ============================================================

    protected $host;
    protected $port;
    protected $server;
    protected $clients = [];
    protected $handshakes = [];
    protected $lastPing = [];
    protected $running = true;

    // 生产环境配置
    protected $config = [
        'max_connections' => 1024,           // 最大连接数
        'heartbeat_interval' => 30,           // 心跳间隔（秒）
        'timeout' => 60,                      // 超时时间（秒）
        'max_memory' => 128 * 1024 * 1024,    // 最大内存（128MB）
        'reuse_port' => true,                 // 端口复用
        'log_file' => null,                   // 日志文件
    ];

    // ============================================================
    // 统计信息
    // ============================================================

    protected $stats = [
        'total_connections' => 0,
        'current_connections' => 0,
        'total_messages' => 0,
        'start_time' => 0,
        'last_gc' => 0,
    ];

    public function __construct($config = [])
    {
        // 合并配置
        $this->config = array_merge($this->config, $config);

        if (empty($config)) {
            $appConfig = require ROOT_PATH . '/config/app.php';
            $wsConfig = $appConfig['websocket'] ?? [];
            $this->config = array_merge($this->config, $wsConfig);
        }

        $this->host = $this->config['host'] ?? '0.0.0.0';
        $this->port = $this->config['port'] ?? 8080;
        $this->stats['start_time'] = time();
    }

    // ============================================================
    // 启动服务
    // ============================================================

    public function start()
    {
        $this->log("🐉 LongPHP WebSocket 服务启动");
        $this->log("端口: {$this->port}");
        $this->log("最大连接数: {$this->config['max_connections']}");
        $this->log("──────────────────────────────────────────");

        // 创建 Socket 服务器
        $flags = $this->config['reuse_port'] ? STREAM_SERVER_BIND | STREAM_SERVER_LISTEN : STREAM_SERVER_BIND | STREAM_SERVER_LISTEN;
        $this->server = stream_socket_server(
            "tcp://{$this->host}:{$this->port}",
            $errno,
            $errstr,
            $flags
        );

        if (!$this->server) {
            $this->log("❌ 启动失败: {$errstr}", 'error');
            return;
        }

        // 设置非阻塞
        stream_set_blocking($this->server, false);

        $this->log("✅ 服务启动成功");
        $this->log("ws://localhost:{$this->port}");
        $this->log("按 Ctrl+C 停止");
        $this->log("");

        // 注册关闭信号
        register_shutdown_function([$this, 'shutdown']);

        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGINT, [$this, 'stop']);
            pcntl_signal(SIGTERM, [$this, 'stop']);
        }

        // 进入主循环
        $this->eventLoop();
    }

    // ============================================================
    // 主事件循环
    // ============================================================

    protected function eventLoop()
    {
        $lastHeartbeat = time();
        $lastStats = time();
        $loopCount = 0;

        while ($this->running) {
            $loopCount++;

            // 构建读取列表
            $read = array_values($this->clients);
            $read[] = $this->server;
            $write = null;
            $except = null;

            // IO 复用
            if (stream_select($read, $write, $except, 0, 100000) > 0) {
                foreach ($read as $stream) {
                    // 新连接
                    if ($stream === $this->server) {
                        $this->acceptConnection();
                        continue;
                    }

                    // 处理数据
                    $this->handleClientData($stream);
                }
            }

            // 心跳检测
            $now = time();
            if ($now - $lastHeartbeat >= $this->config['heartbeat_interval']) {
                $this->heartbeat();
                $lastHeartbeat = $now;
            }

            // 统计日志（每分钟）
            if ($now - $lastStats >= 60) {
                $this->logStats();
                $lastStats = $now;
            }

            // 内存监控
            if ($loopCount % 1000 == 0) {
                $this->checkMemory();
            }

            // 垃圾回收
            if ($now - $this->stats['last_gc'] > 300) { // 5 分钟
                gc_collect_cycles();
                $this->stats['last_gc'] = $now;
            }

            // 信号处理
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }

            usleep(10000);
        }
    }

    // ============================================================
    // 连接管理
    // ============================================================

    protected function acceptConnection()
    {
        // 连接数限制
        if (count($this->clients) >= $this->config['max_connections']) {
            $this->log("⚠️ 达到最大连接数，拒绝新连接", 'warning');
            $client = stream_socket_accept($this->server, 0);
            if ($client) {
                fwrite($client, "HTTP/1.1 503 Service Unavailable\r\n\r\n");
                fclose($client);
            }
            return;
        }

        $client = @stream_socket_accept($this->server, 0);
        if (!$client) return;

        try {
            stream_set_blocking($client, false);
            $fd = (int)$client;

            $this->clients[$fd] = $client;
            $this->handshakes[$fd] = false;
            $this->lastPing[$fd] = time();

            $this->stats['total_connections']++;
            $this->stats['current_connections'] = count($this->clients);

            $this->log("✅ 新连接: {$fd} (当前: {$this->stats['current_connections']})");
            $this->onConnect($fd);

        } catch (\Exception $e) {
            $this->log("❌ 接受连接失败: " . $e->getMessage(), 'error');
            @fclose($client);
        }
    }

    protected function handleClientData($stream)
    {
        $fd = null;
        foreach ($this->clients as $f => $c) {
            if ($c === $stream) {
                $fd = $f;
                break;
            }
        }
        if ($fd === null) return;

        try {
            $data = @fread($stream, 8192);

            // 连接关闭
            if ($data === false || $data === '') {
                $this->closeClient($fd);
                return;
            }

            // 更新心跳
            $this->lastPing[$fd] = time();

            // 握手
            if (!$this->handshakes[$fd]) {
                $this->doHandshake($stream, $fd, $data);
                return;
            }

            // 解码消息
            $msg = $this->decode($data);

            if ($msg !== false && $msg !== '') {
                $this->stats['total_messages']++;
                $this->onMessage($fd, $msg, $stream);
            }

        } catch (\Exception $e) {
            $this->log("❌ 处理客户端数据异常: {$fd} - " . $e->getMessage(), 'error');
            $this->closeClient($fd);
        }
    }

    protected function doHandshake($stream, $fd, $data)
    {
        if (preg_match("/Sec-WebSocket-Key: (.*)\r\n/", $data, $m)) {
            $accept = base64_encode(
                sha1(trim($m[1]) . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true)
            );

            $response = "HTTP/1.1 101 Switching Protocols\r\n";
            $response .= "Upgrade: websocket\r\n";
            $response .= "Connection: Upgrade\r\n";
            $response .= "Sec-WebSocket-Accept: {$accept}\r\n\r\n";

            fwrite($stream, $response);
            $this->handshakes[$fd] = true;
            $this->log("🤝 握手成功: {$fd}");

            $this->onHandshake($fd);
        }
    }

    protected function closeClient($fd)
    {
        if (isset($this->clients[$fd])) {
            @fclose($this->clients[$fd]);
            unset($this->clients[$fd]);
        }
        unset($this->handshakes[$fd]);
        unset($this->lastPing[$fd]);

        $this->stats['current_connections'] = count($this->clients);

        $this->log("❌ 断开: {$fd} (当前: {$this->stats['current_connections']})");
        $this->onClose($fd);
    }

    // ============================================================
    // 心跳检测
    // ============================================================

    protected function heartbeat()
    {
        $now = time();

        foreach ($this->clients as $fd => $stream) {
            // 检查上次活动时间
            $lastActive = $this->lastPing[$fd] ?? $now;

            // 超时断开
            if ($now - $lastActive > $this->config['timeout']) {
                $this->log("⏰ 心跳超时: {$fd}");
                $this->closeClient($fd);
                continue;
            }

            // 发送 Ping 帧
            try {
                $ping = chr(0x89) . chr(0x00); // WebSocket Ping 帧
                fwrite($stream, $ping);
            } catch (\Exception $e) {
                $this->closeClient($fd);
            }
        }
    }

    // ============================================================
    // WebSocket 协议编解码
    // ============================================================

    protected function decode($data)
    {
        try {
            $len = ord($data[1]) & 127;

            if ($len <= 125) {
                $mask = substr($data, 2, 4);
                $payload = substr($data, 6);
            } elseif ($len == 126) {
                $mask = substr($data, 4, 4);
                $payload = substr($data, 8);
            } else {
                $mask = substr($data, 10, 4);
                $payload = substr($data, 14);
            }

            $msg = '';
            for ($i = 0; $i < strlen($payload); $i++) {
                $msg .= $payload[$i] ^ $mask[$i % 4];
            }
            return $msg;

        } catch (\Exception $e) {
            return false;
        }
    }

    protected function encode($msg, $opcode = 0x1)
    {
        $len = strlen($msg);
        $frame = chr(0x80 | $opcode);

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

    // ============================================================
    // 发送和广播
    // ============================================================

    public function send($stream, $data)
    {
        try {
            if (is_array($data) || is_object($data)) {
                $data = json_encode($data, JSON_UNESCAPED_UNICODE);
            }
            $frame = $this->encode($data);
            return @fwrite($stream, $frame);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function broadcast($data, $except = null)
    {
        $success = 0;
        foreach ($this->clients as $fd => $stream) {
            if ($except !== null && $fd == $except) continue;
            if ($this->send($stream, $data)) {
                $success++;
            }
        }
        return $success;
    }

    // ============================================================
    // 内存和资源管理
    // ============================================================

    protected function checkMemory()
    {
        $memory = memory_get_usage(true);
        $peak = memory_get_peak_usage(true);

        if ($memory > $this->config['max_memory']) {
            $this->log("⚠️ 内存使用过高: " . $this->formatBytes($memory), 'warning');

            // 强制垃圾回收
            gc_collect_cycles();

            // 如果还是过高，记录警告
            if (memory_get_usage(true) > $this->config['max_memory']) {
                $this->log("❌ 内存持续过高，建议重启服务", 'error');
            }
        }

        // 峰值记录
        if ($peak > $this->config['max_memory'] * 1.5) {
            $this->log("⚠️ 内存峰值过高: " . $this->formatBytes($peak), 'warning');
        }
    }

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

    // ============================================================
    // 日志系统
    // ============================================================

    protected function log($msg, $level = 'info')
    {
        $time = date('Y-m-d H:i:s');
        $logMsg = "[{$time}] [{$level}] {$msg}";

        // 控制台输出
        echo $logMsg . "\n";

        // 写入日志文件
        if ($this->config['log_file']) {
            file_put_contents($this->config['log_file'], $logMsg . "\n", FILE_APPEND);
        }
    }

    protected function logStats()
    {
        $uptime = time() - $this->stats['start_time'];
        $this->log("📊 统计: 连接={$this->stats['current_connections']}, " .
                   "总数={$this->stats['total_connections']}, " .
                   "消息={$this->stats['total_messages']}, " .
                   "运行=" . $this->formatUptime($uptime) .
                   ", 内存=" . $this->formatBytes(memory_get_usage(true)));
    }

    protected function formatUptime($seconds)
    {
        $d = floor($seconds / 86400);
        $h = floor(($seconds % 86400) / 3600);
        $m = floor(($seconds % 3600) / 60);
        $s = $seconds % 60;

        $parts = [];
        if ($d) $parts[] = $d . '天';
        if ($h) $parts[] = $h . '时';
        if ($m) $parts[] = $m . '分';
        $parts[] = $s . '秒';

        return implode('', $parts);
    }

    // ============================================================
    // 停止和清理
    // ============================================================

    public function stop()
    {
        $this->running = false;
        $this->log("🛑 正在关闭服务...");
    }

    public function shutdown()
    {
        $this->log("🧹 清理资源...");

        foreach ($this->clients as $stream) {
            @fclose($stream);
        }
        @fclose($this->server);

        $this->clients = [];
        $this->handshakes = [];

        $this->log("✅ 服务已关闭");
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

    protected function onMessage($fd, $msg, $stream)
    {
        // 收到消息 - 默认广播
        $this->broadcast([
            'type' => 'chat',
            'from' => $fd,
            'msg' => $msg,
            'time' => date('H:i:s')
        ]);
    }

    protected function onClose($fd)
    {
        // 连接关闭
    }

    // ============================================================
    // 辅助方法（外部调用）
    // ============================================================

    public function getStats()
    {
        return [
            'current_connections' => $this->stats['current_connections'],
            'total_connections' => $this->stats['total_connections'],
            'total_messages' => $this->stats['total_messages'],
            'uptime' => time() - $this->stats['start_time'],
            'memory' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'max_connections' => $this->config['max_connections'],
        ];
    }
}