<?php
// long/Exception.php
// LongPHP Framework - 统一异常处理

namespace Long;
use Long\App;  // ⬅️ 添加引用

class Exception
{
    private static $debug = false;

     /**
     * @var App
     */
    private static $app;  // ⬅️ 添加 App 实例
     public static function init()
    {
        // ⬅️ 获取 App 实例
        self::$app = App::getInstance();
        
        // ✅ 从配置文件读取 debug 状态
        $configFile = self::$app->getConfigPath('app.php');
        if (file_exists($configFile)) {
            $config = require $configFile;
            self::$debug = $config['debug'] ?? false;
        }

        // 根据 debug 模式设置错误显示
        if (self::$debug) {
            error_reporting(E_ALL);
            ini_set('display_errors', 1);
        } else {
            error_reporting(0);
            ini_set('display_errors', 0);
        }

        // 设置异常处理器
        set_exception_handler([self::class, 'handle']);

        // 设置错误处理器（将错误转为异常）
        set_error_handler([self::class, 'handleError']);

        // 注册关闭函数（处理致命错误）
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    /**
     * 处理异常
     */
    public static function handle($e)
    {
        if (self::$debug) {
            // 调试模式：显示详细错误
            echo '<!DOCTYPE html>';
            echo '<html><head><meta charset="UTF-8"><title>WHL 异常</title>';
            echo '<style>
                body { font-family: Consolas, monospace; max-width: 1200px; margin: 50px auto; padding: 0 20px; background: #f8f9fa; }
                h1 { color: #e74c3c; border-bottom: 3px solid #e74c3c; padding-bottom: 10px; }
                .info { background: #fff; padding: 15px; border-radius: 5px; margin: 10px 0; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
                .label { font-weight: bold; color: #555; }
                .file { color: #2980b9; }
                .line { color: #e67e22; }
                .trace { background: #2d3436; color: #dfe6e9; padding: 15px; border-radius: 5px; overflow: auto; font-size: 13px; white-space: pre-wrap; }
            </style>';
            echo '</head><body>';
            
            echo '<h1>🚨 LongPHP 系统异常</h1>';
            echo '<div class="info"><span class="label">消息：</span> ' . $e->getMessage() . '</div>';
            echo '<div class="info"><span class="label">文件：</span> <span class="file">' . $e->getFile() . '</span> <span class="label">第</span> <span class="line">' . $e->getLine() . '</span> 行</div>';
            echo '<div class="info"><span class="label">堆栈追踪：</span></div>';
            echo '<div class="trace">' . $e->getTraceAsString() . '</div>';
            
            echo '</body></html>';
        } else {
            // 生产模式：记录日志，显示友好提示
            self::logError($e);
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'code' => 500,
                'msg' => '系统繁忙，请稍后再试'
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * 处理错误（转为异常）
     */
    public static function handleError($level, $message, $file, $line)
    {
        if (error_reporting() & $level) {
            throw new \ErrorException($message, 0, $level, $file, $line);
        }
    }

    /**
     * 处理致命错误（shutdown）
     */
    public static function handleShutdown()
    {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            self::handle(new \ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']));
        }
    }

    /**
     * 记录错误日志
     */
    private static function logError($e)
    {
        $logDir = __DIR__ . '/../runlogs/logs/';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
        
        $log = date('Y-m-d H:i:s') . " [ERROR] " . $e->getMessage() . "\n";
        $log .= "文件: " . $e->getFile() . " (第 " . $e->getLine() . " 行)\n";
        $log .= "堆栈: " . $e->getTraceAsString() . "\n";
        $log .= str_repeat('-', 80) . "\n";
        
        file_put_contents($logDir . 'error.log', $log, FILE_APPEND);
    }
}