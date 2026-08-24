<?php
// src/Helper.php
// LongPHP Framework - 系统公共函数（全局命名空间）
// 龙行天下 🐉

use Long\Db;
use Long\Csrf;
use Long\Cache\CacheManager;

// ═══════════════════════════════════════════════════════════════════════
// 1. 事件系统
// ═══════════════════════════════════════════════════════════════════════

if (!function_exists('event')) {
    /**
     * 触发事件
     * 
     * @param string $event 事件名称，如 'user.login'
     * @param array $payload 事件携带的数据
     * @return array 监听器返回的结果数组
     * @example event('user.login', ['user_id' => 1, 'username' => '张三']);
     */
    function event($event, $payload = [])
    {
        return \Long\EventManager::getInstance()->dispatch($event, $payload);
    }
}

if (!function_exists('listen')) {
    /**
     * 注册事件监听器
     * 
     * @param string $event 事件名称
     * @param callable|string $listener 监听器（闭包或 'ClassName@method'）
     * @param int $priority 优先级（数字越大越先执行）
     * @example listen('user.login', function($payload) { logs('用户登录'); });
     * @example listen('user.login', 'App\Listener\UserListener@onLogin');
     */
    function listen($event, $listener, $priority = 0)
    {
        \Long\EventManager::getInstance()->listen($event, $listener, $priority);
    }
}

// ═══════════════════════════════════════════════════════════════════════
// 2. 服务容器（IoC）
// ═══════════════════════════════════════════════════════════════════════

if (!function_exists('app')) {
    /**
     * 获取服务容器实例或解析类
     * 
     * @param string|null $abstract 类名或接口名
     * @param array $parameters 构造函数参数
     * @return \Long\Container|object
     * @example app()                          // 获取容器实例
     * @example app('UserService')             // 解析 UserService
     * @example app()->bind('Interface', 'Impl') // 绑定服务
     */
    function app($abstract = null, $parameters = [])
    {
        $container = \Long\Container::getInstance();
        
        if ($abstract === null) {
            return $container;
        }
        
        return $container->make($abstract, $parameters);
    }
}

// ═══════════════════════════════════════════════════════════════════════
// 3. 调试函数
// ═══════════════════════════════════════════════════════════════════════

if (!function_exists('dd')) {
    /**
     * 打印变量并终止执行（Dump and Die）
     * 
     * @param mixed ...$args 要打印的变量（支持多个）
     * @example dd($data, $request->all(), $user);
     */
    function dd(...$args)
    {
        echo '<pre style="background:#f4f4f4;padding:15px;border-radius:5px;font-size:14px;font-family:monospace;color:#333;border:1px solid #ddd;overflow:auto;">';
        foreach ($args as $arg) {
            var_dump($arg);
        }
        echo '</pre>';
        die();
    }
}

if (!function_exists('dump')) {
    /**
     * 打印变量不终止执行（只打印，不中断程序）
     * 
     * @param mixed ...$args 要打印的变量（支持多个）
     * @example dump($data, $user);
     */
    function dump(...$args)
    {
        echo '<pre style="background:#f4f4f4;padding:15px;border-radius:5px;font-size:14px;font-family:monospace;color:#333;border:1px solid #ddd;overflow:auto;">';
        foreach ($args as $arg) {
            var_dump($arg);
        }
        echo '</pre>';
    }
}

if (!function_exists('p')) {
    /**
     * 友好打印变量（更美观的 print_r）
     * 
     * @param mixed $var 要打印的变量
     * @example p($_POST);
     * @example p($user);
     */
    function p($var)
    {
        if (is_bool($var)) {
            var_dump($var);
        } else if (is_null($var)) {
            var_dump(NULL);
        } else {
            header("Content-type:text/html;charset=utf-8");
            echo "<pre style='position:relative;z-index:1000;padding:10px;border-radius:5px;background:#F5F5F5;border:1px solid #aaa;font-size:14px;line-height:18px;opacity:0.9;'>" . print_r($var, true) . "</pre>";
        }
    }
}

if (!function_exists('logs')) {
    /**
     * 写入日志到文件
     * 
     * @param string $message 日志内容
     * @param string $level 日志级别（info/error/sql/warning/debug）
     * @example logs('用户登录成功', 'info');
     * @example logs('数据库连接失败', 'error');
     */
    function logs($message, $level = 'info')
    {
        $logDir = ROOT_PATH . '/runlogs/logs/';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
        $log = date('Y-m-d H:i:s') . " [{$level}] " . $message . "\n";
        file_put_contents($logDir . 'app.log', $log, FILE_APPEND);
    }
}

if (!function_exists('trace')) {
    /**
     * trace 作为 logs 的别名（兼容老代码）
     * 
     * @param string $message 日志内容
     * @param string $level 日志级别
     */
    function trace($message, $level = 'info')
    {
        return logs($message, $level);
    }
}

// ═══════════════════════════════════════════════════════════════════════
// 4. JSON 响应
// ═══════════════════════════════════════════════════════════════════════

if (!function_exists('json')) {
    /**
     * 返回 JSON 响应（自定义 HTTP 状态码）
     * 
     * @param mixed $data 要返回的数据
     * @param int $code HTTP 状态码（默认 200）
     * @return string JSON 字符串
     * @example json(['name' => '张三'], 200);
     */
    function json($data, $code = 200)
    {
        http_response_code($code);
        header('Content-Type: application/json');
        return json_encode($data, JSON_UNESCAPED_UNICODE);
    }
}

if (!function_exists('success')) {
    /**
     * 返回成功 JSON 响应（统一格式）
     * 
     * @param mixed $data 返回的数据
     * @param string $msg 成功消息（默认 'success'）
     * @param int $code 业务状态码（默认 0 表示成功）
     * @return string JSON 字符串
     * @example success(['user' => $user], '获取成功');
     */
    function success($data = [], $msg = 'success', $code = 0)
    {
        header('Content-Type: application/json');
        return json_encode([
            'code' => $code,
            'msg' => $msg,
            'data' => $data
        ], JSON_UNESCAPED_UNICODE);
    }
}

if (!function_exists('error')) {
    /**
     * 返回错误 JSON 响应（统一格式）
     * 
     * @param string $msg 错误消息（默认 'error'）
     * @param int $code 业务状态码（默认 1 表示错误）
     * @param mixed $data 附加数据
     * @return string JSON 字符串
     * @example error('用户不存在', 404);
     */
    function error($msg = 'error', $code = 1, $data = [])
    {
        header('Content-Type: application/json');
        return json_encode([
            'code' => $code,
            'msg' => $msg,
            'data' => $data
        ], JSON_UNESCAPED_UNICODE);
    }
}

// ═══════════════════════════════════════════════════════════════════════
// 5. 数据库
// ═══════════════════════════════════════════════════════════════════════

if (!function_exists('db')) {
    /**
     * 数据库操作（查询构造器入口）
     * 
     * @param string|null $table 表名
     * @return \Long\Db 查询构造器实例
     * @example db('users')->where('id', 1)->find();
     * @example db()->table('users')->select();
     */
    function db($table = null)
    {
        if ($table) {
            return Db::table($table);
        }
        return new Db();
    }
}

// ═══════════════════════════════════════════════════════════════════════
// 6. 请求和输入
// ═══════════════════════════════════════════════════════════════════════

if (!function_exists('input')) {
    /**
     * 获取输入参数（自动从 GET/POST/JSON 中查找）
     * 
     * @param string|null $key 参数名
     * @param mixed $default 默认值
     * @return mixed
     * @example $id = input('id', 0);
     * @example $all = input();  // 获取所有参数
     */
    function input($key = null, $default = null)
    {
        static $request;
        if (!$request) {
            $request = new \Long\Request();
        }
        if ($key === null) {
            return $request->all();
        }
        return $request->param($key, $default);
    }
}

if (!function_exists('request')) {
    /**
     * 获取 Request 请求对象
     * 
     * @return \Long\Request
     * @example $request = request();
     * @example $id = request()->param('id');
     */
    function request()
    {
        return new \Long\Request();
    }
}

// ═══════════════════════════════════════════════════════════════════════
// 7. 配置和环境
// ═══════════════════════════════════════════════════════════════════════

if (!function_exists('env')) {
    /**
     * 获取环境变量
     * 
     * @param string $key 环境变量名
     * @param mixed $default 默认值
     * @return mixed
     * @example $debug = env('APP_DEBUG', false);
     */
    function env($key, $default = null)
    {
        $value = getenv($key);
        return $value === false ? $default : $value;
    }
}

if (!function_exists('config')) {
    /**
     * 获取配置（加载 config/ 目录下所有 .php 文件并合并）
     * 
     * @param string|null $key 配置键名（支持点号分隔）
     * @param mixed $default 默认值
     * @return mixed
     * @example config('jwt.key')   // 读取 config/jwt.php 中的 key
     * @example config('app.debug') // 读取 config/app.php 中的 debug
     * @example config()            // 获取所有配置
     */
    function config($key = null, $default = null)
    {
        static $configs = null;

        if ($configs === null) {
            $configs = [];
            $files = glob(ROOT_PATH . '/config/*.php');
            foreach ($files as $file) {
                $configs = array_merge($configs, require $file);
            }
        }

        if ($key === null) {
            return $configs;
        }

        $keys = explode('.', $key);
        $value = $configs;
        foreach ($keys as $segment) {
            if (!isset($value[$segment])) {
                return $default;
            }
            $value = $value[$segment];
        }
        return $value;
    }
}

// ═══════════════════════════════════════════════════════════════════════
// 8. 缓存
// ═══════════════════════════════════════════════════════════════════════

if (!function_exists('cache')) {
    /**
     * 缓存操作（支持文件/Redis 驱动）
     * 
     * @param string|null $key 缓存键
     * @param mixed $default 默认值或缓存值
     * @param int|null $ttl 缓存时间（秒）
     * @return mixed
     * @example cache('user_1', $user, 3600);  // 设置缓存
     * @example $user = cache('user_1');       // 获取缓存
     * @example cache()->delete('user_1');     // 删除缓存
     */
    function cache($key = null, $default = null, $ttl = null)
    {
        $cache = CacheManager::getInstance();
        
        if ($key === null) {
            return $cache;
        }
        
        if (func_num_args() === 1) {
            return $cache->get($key);
        }
        
        if ($ttl !== null) {
            $cache->set($key, $default, $ttl);
        } else {
            $cache->set($key, $default);
        }
        return $default;
    }
}

if (!function_exists('remember')) {
    /**
     * 缓存回调结果（缓存不存在则执行回调并缓存）
     * 
     * @param string $key 缓存键
     * @param callable $callback 回调函数
     * @param int|null $ttl 缓存时间（秒）
     * @return mixed
     * @example $users = remember('user_list', function() { return db('users')->select(); }, 600);
     */
    function remember($key, $callback, $ttl = null)
    {
        return CacheManager::getInstance()->remember($key, $callback, $ttl);
    }
}

if (!function_exists('cache_clear')) {
    /**
     * 清空所有缓存
     * 
     * @return bool
     * @example cache_clear();
     */
    function cache_clear()
    {
        return CacheManager::getInstance()->clear();
    }
}

// ═══════════════════════════════════════════════════════════════════════
// 9. 字符串
// ═══════════════════════════════════════════════════════════════════════

if (!function_exists('str_random')) {
    /**
     * 生成随机字符串（大小写字母 + 数字）
     * 
     * @param int $length 长度（默认 32）
     * @return string
     * @example $token = str_random(32);
     */
    function str_random($length = 32)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $max = strlen($characters) - 1;
        $string = '';
        for ($i = 0; $i < $length; $i++) {
            $string .= $characters[random_int(0, $max)];
        }
        return $string;
    }
}

if (!function_exists('str_slug')) {
    /**
     * 生成 URL 友好的 Slug（用于 SEO）
     * 
     * @param string $string 要转换的字符串
     * @param string $separator 分隔符（默认 -）
     * @return string
     * @example str_slug('Hello World!'); // hello-world
     * @example str_slug('你好 世界', '-'); // 你好-世界
     */
    function str_slug($string, $separator = '-')
    {
        $string = preg_replace('/[^a-zA-Z0-9\s\-]/', '', $string);
        $string = preg_replace('/\s+/', $separator, trim($string));
        return strtolower($string);
    }
}

if (!function_exists('str_limit')) {
    /**
     * 限制字符串长度（支持中文）
     * 
     * @param string $string 原字符串
     * @param int $length 最大长度
     * @param string $suffix 超出后的后缀（默认 ...）
     * @return string
     * @example str_limit('这是一段很长的文字', 10); // 这是一段很长的...
     */
    function str_limit($string, $length = 100, $suffix = '...')
    {
        if (mb_strlen($string) <= $length) {
            return $string;
        }
        return mb_substr($string, 0, $length) . $suffix;
    }
}

// ═══════════════════════════════════════════════════════════════════════
// 10. 数组
// ═══════════════════════════════════════════════════════════════════════

if (!function_exists('array_only')) {
    /**
     * 只保留数组中指定的键
     * 
     * @param array $array 原数组
     * @param array|string $keys 要保留的键名
     * @return array
     * @example $result = array_only($user, ['id', 'name', 'email']);
     */
    function array_only($array, $keys)
    {
        return array_intersect_key($array, array_flip((array) $keys));
    }
}

if (!function_exists('array_except')) {
    /**
     * 排除数组中指定的键
     * 
     * @param array $array 原数组
     * @param array|string $keys 要排除的键名
     * @return array
     * @example $result = array_except($user, ['password', 'token']);
     */
    function array_except($array, $keys)
    {
        return array_diff_key($array, array_flip((array) $keys));
    }
}

// ═══════════════════════════════════════════════════════════════════════
// 11. 时间
// ═══════════════════════════════════════════════════════════════════════

if (!function_exists('now')) {
    /**
     * 获取当前时间（格式化）
     * 
     * @param string $format 时间格式（默认 Y-m-d H:i:s）
     * @return string
     * @example now();                 // 2026-01-15 14:30:00
     * @example now('Y-m-d');         // 2026-01-15
     */
    function now($format = 'Y-m-d H:i:s')
    {
        return date($format);
    }
}

// ═══════════════════════════════════════════════════════════════════════
// 12. 重定向
// ═══════════════════════════════════════════════════════════════════════

if (!function_exists('redirect')) {
    /**
     * 页面重定向
     * 
     * @param string $url 跳转地址
     * @param int $status HTTP 状态码（默认 302）
     * @return void
     * @example redirect('/user/profile');
     * @example redirect('/login', 301);
     */
    function redirect($url, $status = 302)
    {
        header('Location: ' . $url, true, $status);
        exit;
    }
}

// ═══════════════════════════════════════════════════════════════════════
// 13. CSRF 防护
// ═══════════════════════════════════════════════════════════════════════

if (!function_exists('csrf_token')) {
    /**
     * 生成 CSRF Token（存入 Session）
     * 
     * @return string CSRF Token
     * @example $token = csrf_token();
     */
    function csrf_token()
    {
        return Csrf::token();
    }
}

if (!function_exists('csrf_field')) {
    /**
     * 生成 CSRF 隐藏域 HTML（用于表单）
     * 
     * @return string HTML 隐藏域
     * @example <form method="POST"><?= csrf_field() ?></form>
     */
    function csrf_field()
    {
        return Csrf::field();
    }
}

if (!function_exists('get_csrf_token')) {
    /**
     * 从当前请求中获取 CSRF Token
     * 
     * 支持从以下位置获取（按优先级）：
     * 1. Header: X-CSRF-TOKEN
     * 2. POST/GET 参数: _csrf
     * 
     * @return string|null 不存在返回 null
     * @example $token = get_csrf_token();
     */
    function get_csrf_token()
    {
        static $request;
        if (!$request) {
            $request = new \Long\Request();
        }
        
        $token = $request->header('X-CSRF-TOKEN', '');
        if (empty($token)) {
            $token = $request->param('_csrf', '');
        }
        return $token ?: null;
    }
}

// ═══════════════════════════════════════════════════════════════════════
// 14. JWT Token 获取
// ═══════════════════════════════════════════════════════════════════════

if (!function_exists('token')) {
    /**
     * 从当前请求中获取 JWT Token
     * 
     * 支持从以下位置获取（按优先级）：
     * 1. Header: Authorization: Bearer xxx
     * 2. Header: token: xxx
     * 3. GET 参数: ?token=xxx
     * 
     * @return string|null 不存在返回 null
     * @example $token = token();
     * @example $user = jwt_verify(token());
     */
    function token()
    {
        // 从 Authorization Header 获取
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $auth = $_SERVER['HTTP_AUTHORIZATION'];
            if (strpos($auth, 'Bearer ') === 0) {
                return substr($auth, 7);
            }
            return $auth;
        }
        
        // 从自定义 Header 获取
        if (isset($_SERVER['HTTP_TOKEN'])) {
            return $_SERVER['HTTP_TOKEN'];
        }
        
        // 从 GET 参数获取
        if (isset($_GET['token'])) {
            return $_GET['token'];
        }
        
        return null;
    }
}

// ═══════════════════════════════════════════════════════════════════════
// 15. ThinkPHP 3.2 风格字母方法
// ═══════════════════════════════════════════════════════════════════════

if (!function_exists('I')) {
    /**
     * 获取输入参数（ThinkPHP 3.2 风格）
     * 
     * @param string|null $name 参数名（如 'id' 或 'get.id' 或 'post.name'）
     * @param mixed $default 默认值
     * @param string $filter 过滤函数（如 'intval', 'trim'）
     * @return mixed
     * @example I('id')                // 自动查找
     * @example I('get.id', 0)        // 只从 GET 获取
     * @example I('post.name', '')    // 只从 POST 获取
     * @example I('json.user_id', 0)  // 从 JSON 获取
     */
    function I($name = null, $default = null, $filter = '')
    {
        static $request;
        if (!$request) {
            $request = new \Long\Request();
        }

        if ($name === null || $name === '') {
            return $request->all();
        }

        // 没有指定来源，自动查找
        if (strpos($name, '.') === false) {
            $value = $default;
            
            if (($val = $request->get($name)) !== null) {
                $value = $val;
            } elseif (($val = $request->post($name)) !== null) {
                $value = $val;
            } elseif (($val = $request->json($name)) !== null) {
                $value = $val;
            } else {
                $raw = $request->raw();
                if (!empty($raw)) {
                    parse_str($raw, $parsed);
                    if (isset($parsed[$name])) {
                        $value = $parsed[$name];
                    }
                }
            }
            
            if ($filter && function_exists($filter)) {
                $value = $filter($value);
            }
            return $value;
        }

        // 指定来源
        $parts = explode('.', $name);
        $method = strtolower($parts[0]);
        $key = $parts[1] ?? '';

        switch ($method) {
            case 'get':
                $value = $request->get($key, $default);
                break;
            case 'post':
                $value = $request->post($key, $default);
                break;
            case 'json':
                $value = $request->json($key, $default);
                break;
            case 'put':
                $value = $request->put($key, $default);
                break;
            case 'delete':
                $value = $request->delete($key, $default);
                break;
            default:
                $value = $request->param($key, $default);
                break;
        }

        if ($filter && function_exists($filter)) {
            $value = $filter($value);
        }

        return $value;
    }
}

if (!function_exists('C')) {
    /**
     * 获取配置（ThinkPHP 3.2 风格）
     * 
     * @param string|null $name 配置名（支持点号分隔）
     * @param mixed $default 默认值
     * @return mixed
     * @example C('jwt.key')     // 读取 config/jwt.php 中的 key
     * @example C('app.debug')   // 读取 config/app.php 中的 debug
     * @example C()              // 获取所有配置
     */
    function C($name = null, $default = null)
    {
        if ($name === null || $name === '') {
            return config();
        }
        return config($name, $default);
    }
}

if (!function_exists('U')) {
    /**
     * 生成 URL（ThinkPHP 3.2 风格）
     * 
     * @param string $url 路由地址
     * @param array $params URL 参数
     * @param bool $full 是否返回完整 URL（含域名）
     * @return string
     * @example U('user/index')              // /user
     * @example U('user/detail', ['id'=>1]) // /user/detail?id=1
     * @example U('index')                  // /
     * @example U('user/index', [], true)   // http://localhost/user
     */
    function U($url, $params = [], $full = false)
    {
        // 处理首页
        if ($url === 'index' || $url === 'index/index') {
            $url = '';
        }
        
        // 去掉 /index 结尾
        if (substr($url, -6) === '/index') {
            $url = substr($url, 0, -6);
        }
        
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        
        $path = '/' . ltrim($url, '/');
        if (!empty($params)) {
            $path .= '?' . http_build_query($params);
        }
        
        if ($full) {
            return $protocol . '://' . $host . $path;
        }
        return $path;
    }
}

if (!function_exists('D')) {
    /**
     * 实例化模型（业务层，ThinkPHP 3.2 风格）
     * 
     * @param string $name 模型名（如 'User'）
     * @return \Long\Model 模型实例
     * @example $user = D('User');
     * @example $user = D('User')->where('id', 1)->find();
     */
    function D($name)
    {
        $class = 'App\\Model\\' . $name;
        if (class_exists($class)) {
            return new $class();
        }
        return db(strtolower($name));
    }
}

if (!function_exists('M')) {
    /**
     * 实例化数据库表（基础层，ThinkPHP 3.2 风格）
     * 
     * @param string|null $table 表名
     * @return \Long\Db 查询构造器实例
     * @example $user = M('user');
     * @example $user = M('user')->where('id', 1)->find();
     */
    function M($table = null)
    {
        return db($table);
    }
}

if (!function_exists('A')) {
    /**
     * 实例化控制器（ThinkPHP 3.2 风格）
     * 
     * @param string $name 控制器名（如 'User'）
     * @return object|null 控制器实例，不存在返回 null
     * @example $user = A('User');
     * @example $result = A('User')->index();
     */
    function A($name)
    {
        $class = 'App\\Controller\\' . $name;
        if (class_exists($class)) {
            return new $class();
        }
        return null;
    }
}

if (!function_exists('S')) {
    /**
     * Session 操作（ThinkPHP 3.2 风格）
     * 
     * @param string $name 键名
     * @param mixed $value 值（不传则获取，传入则设置）
     * @return mixed
     * @example S('user_id', 123);   // 设置
     * @example $id = S('user_id');  // 获取
     */
    function S($name, $value = null)
    {
        if ($value === null) {
            return session($name);
        }
        session($name, $value);
        return $value;
    }
}

if (!function_exists('L')) {
    /**
     * 语言变量操作（ThinkPHP 3.2 风格）
     * 
     * @param string $name 键名
     * @param string|null $value 值（不传则获取，传入则设置）
     * @return mixed
     * @example L('welcome', '欢迎');  // 设置
     * @example echo L('welcome');     // 获取
     */
    function L($name, $value = null)
    {
        static $lang = [];
        
        if ($value === null) {
            return $lang[$name] ?? $name;
        }
        
        $lang[$name] = $value;
        return $value;
    }
}

if (!function_exists('E')) {
    /**
     * 抛出异常（ThinkPHP 3.2 风格）
     * 
     * @param string $msg 错误信息
     * @param int $code 错误码
     * @throws \Exception
     * @example E('用户不存在', 404);
     * @example E('参数错误', 3001);
     */
    function E($msg, $code = 0)
    {
        throw new \Exception($msg, $code);
    }
}

// ═══════════════════════════════════════════════════════════════════════
// 16. 常用工具方法
// ═══════════════════════════════════════════════════════════════════════

if (!function_exists('get_ip')) {
    /**
     * 获取客户端 IP 地址
     * 
     * 支持代理转发（X-Forwarded-For, Client-IP）
     * 
     * @return string IP 地址
     * @example $ip = get_ip(); // 192.168.1.1
     */
    function get_ip()
    {
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}

if (!function_exists('get_browser_info')) {
    /**
     * 获取用户浏览器和操作系统信息
     * 
     * @return array 包含 browser, platform, user_agent 的数组
     * @example $info = get_browser_info();
     * @example echo $info['browser']; // Chrome
     * @example echo $info['platform']; // Windows 10
     */
    function get_browser_info()
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $browser = '未知';
        $platform = '未知';
        
        // 操作系统检测
        if (strpos($userAgent, 'Windows NT 10.0') !== false) {
            $platform = 'Windows 10';
        } elseif (strpos($userAgent, 'Windows NT 6.1') !== false) {
            $platform = 'Windows 7';
        } elseif (strpos($userAgent, 'Mac OS X') !== false) {
            $platform = 'Mac OS X';
        } elseif (strpos($userAgent, 'Linux') !== false) {
            $platform = 'Linux';
        } elseif (strpos($userAgent, 'iPhone') !== false) {
            $platform = 'iPhone';
        } elseif (strpos($userAgent, 'Android') !== false) {
            $platform = 'Android';
        }
        
        // 浏览器检测
        if (strpos($userAgent, 'Edg') !== false) {
            $browser = 'Edge';
        } elseif (strpos($userAgent, 'Chrome') !== false && strpos($userAgent, 'Edg') === false) {
            $browser = 'Chrome';
        } elseif (strpos($userAgent, 'Firefox') !== false) {
            $browser = 'Firefox';
        } elseif (strpos($userAgent, 'Safari') !== false && strpos($userAgent, 'Chrome') === false) {
            $browser = 'Safari';
        } elseif (strpos($userAgent, 'MSIE') !== false || strpos($userAgent, 'Trident') !== false) {
            $browser = 'Internet Explorer';
        }
        
        return [
            'browser' => $browser,
            'platform' => $platform,
            'user_agent' => $userAgent
        ];
    }
}

if (!function_exists('is_mobile')) {
    /**
     * 判断是否为移动端访问
     * 
     * @return bool true 表示移动端，false 表示 PC
     * @example if (is_mobile()) { // 移动端 }
     */
    function is_mobile()
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $mobileAgents = ['iPhone', 'Android', 'iPad', 'iPod', 'Mobile'];
        foreach ($mobileAgents as $agent) {
            if (strpos($userAgent, $agent) !== false) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('is_https')) {
    /**
     * 判断当前请求是否使用 HTTPS
     * 
     * @return bool true 表示 HTTPS，false 表示 HTTP
     * @example if (is_https()) { // HTTPS 请求 }
     */
    function is_https()
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['SERVER_PORT'] ?? 80) == 443;
    }
}

if (!function_exists('current_url')) {
    /**
     * 获取当前页面的完整 URL
     * 
     * @return string 完整 URL
     * @example $url = current_url(); // https://example.com/user?id=1
     */
    function current_url()
    {
        $protocol = is_https() ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        return $protocol . '://' . $host . $uri;
    }
}

if (!function_exists('uuid')) {
    /**
     * 生成 UUID v4（通用唯一标识符）
     * 
     * @return string UUID 字符串
     * @example $id = uuid(); // 550e8400-e29b-41d4-a716-446655440000
     */
    function uuid()
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}

if (!function_exists('order_no')) {
    /**
     * 生成订单号
     * 
     * 格式：前缀 + 年月日时分秒 + 4位随机数
     * 
     * @param string $prefix 前缀（可选）
     * @return string 订单号
     * @example $order = order_no('ORD'); // ORD202601151430001234
     * @example $order = order_no();      // 202601151430001234
     */
    function order_no($prefix = '')
    {
        return $prefix . date('YmdHis') . rand(1000, 9999);
    }
}

if (!function_exists('xss_clean')) {
    /**
     * XSS 过滤（HTML 转义）
     * 
     * @param string $str 输入字符串
     * @return string 过滤后的字符串
     * @example $safe = xss_clean('<script>alert(1)</script>');
     */
    function xss_clean($str)
    {
        return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('rand_code')) {
    /**
     * 生成随机数字验证码
     * 
     * @param int $length 长度（默认 6 位）
     * @return string 验证码
     * @example $code = rand_code(4); // 4829
     * @example $code = rand_code(6); // 384729
     */
    function rand_code($length = 6)
    {
        $chars = '0123456789';
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $chars[random_int(0, 9)];
        }
        return $code;
    }
}

if (!function_exists('hash_password')) {
    /**
     * 密码加密（使用 bcrypt 算法）
     * 
     * @param string $password 明文密码
     * @return string 加密后的密码哈希
     * @example $hash = hash_password('123456');
     */
    function hash_password($password)
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }
}

if (!function_exists('verify_password')) {
    /**
     * 验证密码是否匹配
     * 
     * @param string $password 明文密码
     * @param string $hash 加密后的密码哈希
     * @return bool true 匹配，false 不匹配
     * @example if (verify_password('123456', $hash)) { // 密码正确 }
     */
    function verify_password($password, $hash)
    {
        return password_verify($password, $hash);
    }
}

if (!function_exists('referer_domain')) {
    /**
     * 获取请求来源的域名
     * 
     * @return string 域名，不存在返回空字符串
     * @example $domain = referer_domain(); // google.com
     */
    function referer_domain()
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        if (empty($referer)) {
            return '';
        }
        $parts = parse_url($referer);
        return $parts['host'] ?? '';
    }
}

if (!function_exists('api_result')) {
    /**
     * API 统一返回格式（快捷方法）
     * 
     * @param int $code 状态码
     * @param string $msg 消息
     * @param mixed $data 数据（默认空数组）
     * @return string JSON 字符串
     * @example api_result(200, '成功', ['id' => 1]);
     * @example api_result(404, '用户不存在');
     */
    function api_result($code, $msg, $data = [])
    {
        header('Content-Type: application/json');
        return json_encode([
            'code' => $code,
            'msg' => $msg,
            'data' => $data
        ], JSON_UNESCAPED_UNICODE);
    }
}

// ═══════════════════════════════════════════════════════════════════════
// 17. Cookie 快捷操作
// ═══════════════════════════════════════════════════════════════════════

if (!function_exists('cookie')) {
    /**
     * Cookie 快捷操作（设置/获取/删除）
     * 底层调用 Long\Cookie 类
     * 
     * @param string $name Cookie 名称
     * @param mixed $value Cookie 值（不传则获取，传入则设置，传 null 则删除）
     * @param int $expire 过期时间（秒），0 表示会话结束后过期
     * @param string $path Cookie 路径，默认 '/'
     * @param string $domain Cookie 域名
     * @param bool $secure 是否仅 HTTPS
     * @param bool $httponly 是否仅 HTTP
     * @param string $samesite SameSite 属性
     * @return mixed
     * @example cookie('user_id', 123, 3600);  // 设置 Cookie，1小时过期
     * @example $id = cookie('user_id');       // 获取 Cookie
     * @example cookie('user_id', null);       // 删除 Cookie
     */
    function cookie($name, $value = null, $expire = 0, $path = '/', $domain = '', $secure = false, $httponly = true, $samesite = 'lax')
    {
        // 删除 Cookie
        if ($value === null && func_num_args() === 2) {
            return \Long\Cookie::delete($name, $path, $domain);
        }
        
        // 设置 Cookie
        if (func_num_args() >= 2) {
            return \Long\Cookie::set($name, $value, $expire, $path, $domain, $secure, $httponly, $samesite);
        }
        
        // 获取 Cookie
        return \Long\Cookie::get($name);
    }
}

if (!function_exists('has_cookie')) {
    /**
     * 检查 Cookie 是否存在
     * 
     * @param string $name Cookie 名称
     * @return bool
     * @example if (has_cookie('user_id')) { // 存在 }
     */
    function has_cookie($name)
    {
        return \Long\Cookie::has($name);
    }
}

if (!function_exists('get_cookie')) {
    /**
     * 获取 Cookie 值
     * 
     * @param string $name Cookie 名称
     * @param mixed $default 默认值
     * @return mixed
     * @example $id = get_cookie('user_id', 0);
     */
    function get_cookie($name, $default = null)
    {
        return \Long\Cookie::get($name, $default);
    }
}

if (!function_exists('set_cookie')) {
    /**
     * 设置 Cookie
     * 
     * @param string $name Cookie 名称
     * @param mixed $value Cookie 值
     * @param int $expire 过期时间（秒）
     * @param string $path 路径
     * @param string $domain 域名
     * @param bool $secure 是否仅 HTTPS
     * @param bool $httponly 是否仅 HTTP
     * @return bool
     * @example set_cookie('user_id', 123, 3600);
     */
    function set_cookie($name, $value, $expire = 0, $path = '/', $domain = '', $secure = false, $httponly = true)
    {
        return \Long\Cookie::set($name, $value, $expire, $path, $domain, $secure, $httponly);
    }
}

if (!function_exists('delete_cookie')) {
    /**
     * 删除 Cookie
     * 
     * @param string $name Cookie 名称
     * @param string $path Cookie 路径，默认 '/'
     * @param string $domain Cookie 域名
     * @return bool
     * @example delete_cookie('user_id');
     */
    function delete_cookie($name, $path = '/', $domain = '')
    {
        return \Long\Cookie::delete($name, $path, $domain);
    }
}

if (!function_exists('all_cookie')) {
    /**
     * 获取所有 Cookie
     * 
     * @return array
     * @example $all = all_cookie();
     */
    function all_cookie()
    {
        return \Long\Cookie::all();
    }
}

if (!function_exists('clear_cookie')) {
    /**
     * 清空所有 Cookie
     * 
     * @param string $path 路径
     * @param string $domain 域名
     * @return void
     * @example clear_cookie();
     */
    function clear_cookie($path = '/', $domain = '')
    {
        \Long\Cookie::clear($path, $domain);
    }
}

// ═══════════════════════════════════════════════════════════════════════
// 18. HTTP 请求（远程请求）
// ═══════════════════════════════════════════════════════════════════════

if (!function_exists('http_get')) {
    /**
     * GET 请求
     * 
     * @param string $url 请求地址
     * @param array $headers 请求头
     * @param int $timeout 超时时间（秒）
     * @return array ['code' => 状态码, 'body' => 响应内容, 'error' => 错误信息]
     * @example $result = http_get('https://api.example.com/user/1');
     */
    function http_get($url, $headers = [], $timeout = 30)
    {
        return http_request('GET', $url, [], $headers, $timeout);
    }
}

if (!function_exists('http_post')) {
    /**
     * POST 请求（表单格式）
     * 
     * @param string $url 请求地址
     * @param array|string $data 请求数据
     * @param array $headers 请求头
     * @param int $timeout 超时时间（秒）
     * @return array ['code' => 状态码, 'body' => 响应内容, 'error' => 错误信息]
     * @example $result = http_post('https://api.example.com/user', ['name' => '张三']);
     */
    function http_post($url, $data = [], $headers = [], $timeout = 30)
    {
        return http_request('POST', $url, $data, $headers, $timeout);
    }
}

if (!function_exists('http_post_json')) {
    /**
     * POST 请求（JSON 格式）
     * 
     * @param string $url 请求地址
     * @param array $data 请求数据（自动转 JSON）
     * @param array $headers 额外请求头
     * @param int $timeout 超时时间（秒）
     * @return array ['code' => 状态码, 'body' => 响应内容, 'error' => 错误信息]
     * @example $result = http_post_json('https://api.example.com/user', ['name' => '张三']);
     */
    function http_post_json($url, $data = [], $headers = [], $timeout = 30)
    {
        $headers = array_merge([
            'Content-Type: application/json'
        ], $headers);
        return http_request('POST', $url, json_encode($data, JSON_UNESCAPED_UNICODE), $headers, $timeout);
    }
}

if (!function_exists('http_put')) {
    /**
     * PUT 请求
     * 
     * @param string $url 请求地址
     * @param array|string $data 请求数据
     * @param array $headers 请求头
     * @param int $timeout 超时时间（秒）
     * @return array ['code' => 状态码, 'body' => 响应内容, 'error' => 错误信息]
     */
    function http_put($url, $data = [], $headers = [], $timeout = 30)
    {
        return http_request('PUT', $url, $data, $headers, $timeout);
    }
}

if (!function_exists('http_delete')) {
    /**
     * DELETE 请求
     * 
     * @param string $url 请求地址
     * @param array $headers 请求头
     * @param int $timeout 超时时间（秒）
     * @return array ['code' => 状态码, 'body' => 响应内容, 'error' => 错误信息]
     */
    function http_delete($url, $headers = [], $timeout = 30)
    {
        return http_request('DELETE', $url, [], $headers, $timeout);
    }
}

if (!function_exists('http_download')) {
    /**
     * 下载远程文件到本地
     * 
     * @param string $url 远程文件地址
     * @param string $savePath 本地保存路径
     * @param array $headers 请求头
     * @param int $timeout 超时时间（秒）
     * @return array ['code' => 状态码, 'size' => 文件大小, 'path' => 保存路径, 'error' => 错误信息]
     * @example http_download('https://example.com/file.zip', '/path/to/save/file.zip');
     */
    function http_download($url, $savePath, $headers = [], $timeout = 60)
    {
        $result = http_request('GET', $url, [], $headers, $timeout);
        
        if ($result['code'] === false) {
            return $result;
        }
        
        if ($result['code'] !== 200 && $result['code'] !== 206) {
            return [
                'code' => $result['code'],
                'error' => '下载失败，HTTP状态码: ' . $result['code'],
                'body' => $result['body'] ?? ''
            ];
        }
        
        // 确保目录存在
        $dir = dirname($savePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        // 保存文件
        $bytes = file_put_contents($savePath, $result['body']);
        if ($bytes === false) {
            return ['code' => false, 'error' => '文件写入失败'];
        }
        
        return [
            'code' => $result['code'],
            'size' => $bytes,
            'path' => $savePath,
            'error' => null
        ];
    }
}

if (!function_exists('http_request')) {
    /**
     * 通用 HTTP 请求（支持所有方法）
     * 
     * @param string $method 请求方法（GET/POST/PUT/DELETE/PATCH）
     * @param string $url 请求地址
     * @param mixed $data 请求数据
     * @param array $headers 请求头
     * @param int $timeout 超时时间（秒）
     * @return array ['code' => 状态码, 'body' => 响应内容, 'error' => 错误信息]
     * @example $result = http_request('GET', 'https://api.example.com/user');
     */
    function http_request($method, $url, $data = [], $headers = [], $timeout = 30)
    {
        $ch = curl_init();
        
        // 设置请求方法
        $method = strtoupper($method);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        
        // 设置 URL
        curl_setopt($ch, CURLOPT_URL, $url);
        
        // 设置请求头
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        
        // 设置数据
        if ($method === 'POST' && !empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        } elseif (in_array($method, ['PUT', 'PATCH']) && !empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        } elseif ($method === 'GET' && is_array($data) && !empty($data)) {
            // GET 参数拼接
            $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($data);
            curl_setopt($ch, CURLOPT_URL, $url);
        }
        
        // 返回内容
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        // 超时
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        
        // 跟随重定向
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        
        // SSL（生产环境建议 true）
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        // 用户代理
        curl_setopt($ch, CURLOPT_USERAGENT, 'LongPHP Framework/1.0');
        
        // 执行
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return [
                'code' => false,
                'body' => null,
                'error' => $error
            ];
        }
        
        return [
            'code' => $httpCode,
            'body' => $response,
            'error' => null
        ];
    }
}

// src/Helper.php

// ═══════════════════════════════════════════════════════════════════════
// 20. 数组操作
// ═══════════════════════════════════════════════════════════════════════

if (!function_exists('array_column_values')) {
    /**
     * 获取数组中指定字段的所有值（支持点号获取嵌套）
     * 
     * @param array $array 数据数组
     * @param string $field 字段名（支持点号，如 'user.id'）
     * @return array
     * @example
     * $users = [['id' => 1, 'name' => '张三'], ['id' => 2, 'name' => '李四']];
     * $ids = array_column_values($users, 'id'); // [1, 2]
     */
    function array_column_values($array, $field)
    {
        $result = [];
        foreach ($array as $item) {
            $value = data_get($item, $field);
            if ($value !== null) {
                $result[] = $value;
            }
        }
        return $result;
    }
}

if (!function_exists('array_contains')) {
    /**
     * 检查数组中是否包含某个字段的指定值
     * 
     * @param array $array 数据数组
     * @param string $field 字段名
     * @param mixed $value 要查找的值
     * @return bool
     * @example
     * $users = [['id' => 1, 'name' => '张三'], ['id' => 2, 'name' => '李四']];
     * array_contains($users, 'id', 1); // true
     * array_contains($users, 'name', '王五'); // false
     */
    function array_contains($array, $field, $value)
    {
        foreach ($array as $item) {
            $val = data_get($item, $field);
            if ($val == $value) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('array_find')) {
    /**
     * 查找数组中第一个匹配指定字段值的元素
     * 
     * @param array $array 数据数组
     * @param string $field 字段名
     * @param mixed $value 要查找的值
     * @return array|null 找到返回元素，否则返回 null
     * @example
     * $user = array_find($users, 'id', 1);
     * $user = array_find($users, 'name', '张三');
     */
    function array_find($array, $field, $value)
    {
        foreach ($array as $item) {
            $val = data_get($item, $field);
            if ($val == $value) {
                return $item;
            }
        }
        return null;
    }
}

if (!function_exists('array_find_all')) {
    /**
     * 查找数组中所有匹配指定字段值的元素
     * 
     * @param array $array 数据数组
     * @param string $field 字段名
     * @param mixed $value 要查找的值
     * @return array
     * @example
     * $users = array_find_all($users, 'status', 1);
     */
    function array_find_all($array, $field, $value)
    {
        $result = [];
        foreach ($array as $item) {
            $val = data_get($item, $field);
            if ($val == $value) {
                $result[] = $item;
            }
        }
        return $result;
    }
}

if (!function_exists('array_to_json')) {
    /**
     * 将数组转换为 JSON（自动处理中文）
     * 
     * @param array $array 数据数组
     * @param int $options JSON 选项
     * @return string JSON 字符串
     * @example
     * $json = array_to_json($users);
     */
    function array_to_json($array, $options = JSON_UNESCAPED_UNICODE)
    {
        return json_encode($array, $options);
    }
}

if (!function_exists('array_group')) {
    /**
     * 按指定字段对数组进行分组
     * 
     * @param array $array 数据数组
     * @param string $field 分组字段
     * @return array
     * @example
     * $users = [
     *   ['id' => 1, 'status' => 1, 'name' => '张三'],
     *   ['id' => 2, 'status' => 0, 'name' => '李四'],
     *   ['id' => 3, 'status' => 1, 'name' => '王五'],
     * ];
     * $grouped = array_group($users, 'status');
     * // [1 => [['id'=>1,'name'=>'张三'], ['id'=>3,'name'=>'王五']], 0 => [['id'=>2,'name'=>'李四']]]
     */
    function array_group($array, $field)
    {
        $result = [];
        foreach ($array as $item) {
            $key = data_get($item, $field);
            if (!isset($result[$key])) {
                $result[$key] = [];
            }
            $result[$key][] = $item;
        }
        return $result;
    }
}

if (!function_exists('array_pluck')) {
    /**
     * 获取数组中指定字段的值（支持多个字段）
     * 
     * @param array $array 数据数组
     * @param string|array $fields 字段名或字段数组
     * @return array
     * @example
     * $users = [['id' => 1, 'name' => '张三', 'age' => 20], ['id' => 2, 'name' => '李四', 'age' => 25]];
     * array_pluck($users, 'name'); // ['张三', '李四']
     * array_pluck($users, ['id', 'name']); // [[1,'张三'], [2,'李四']]
     */
    function array_pluck($array, $fields)
    {
        $result = [];
        foreach ($array as $item) {
            if (is_array($fields)) {
                $row = [];
                foreach ($fields as $field) {
                    $row[$field] = data_get($item, $field);
                }
                $result[] = $row;
            } else {
                $result[] = data_get($item, $fields);
            }
        }
        return $result;
    }
}

if (!function_exists('data_get')) {
    /**
     * 使用点号从数组/对象中获取值
     * 
     * @param array|object $target 目标数据
     * @param string $key 键名（支持点号，如 'user.profile.name'）
     * @param mixed $default 默认值
     * @return mixed
     * @example
     * $data = ['user' => ['profile' => ['name' => '张三']]];
     * data_get($data, 'user.profile.name'); // '张三'
     * data_get($data, 'user.age', 0); // 0
     */
    function data_get($target, $key, $default = null)
    {
        if ($key === null || $key === '') {
            return $target;
        }
        
        if (is_object($target) && isset($target->$key)) {
            return $target->$key;
        }
        
        if (is_array($target) && isset($target[$key])) {
            return $target[$key];
        }
        
        $keys = explode('.', $key);
        
        foreach ($keys as $segment) {
            if (is_array($target)) {
                if (!array_key_exists($segment, $target)) {
                    return $default;
                }
                $target = $target[$segment];
            } elseif (is_object($target)) {
                if (!property_exists($target, $segment)) {
                    return $default;
                }
                $target = $target->$segment;
            } else {
                return $default;
            }
        }
        
        return $target;
    }
}

if (!function_exists('data_set')) {
    /**
     * 使用点号设置数组/对象的值
     * 
     * @param array $target 目标数组（引用）
     * @param string $key 键名（支持点号）
     * @param mixed $value 要设置的值
     * @return void
     * @example
     * $data = [];
     * data_set($data, 'user.profile.name', '张三');
     * // $data = ['user' => ['profile' => ['name' => '张三']]]
     */
    function data_set(&$target, $key, $value)
    {
        if ($key === null || $key === '') {
            $target = $value;
            return;
        }
        
        $keys = explode('.', $key);
        $current = &$target;
        
        foreach ($keys as $i => $segment) {
            if ($i === count($keys) - 1) {
                $current[$segment] = $value;
            } else {
                if (!isset($current[$segment]) || !is_array($current[$segment])) {
                    $current[$segment] = [];
                }
                $current = &$current[$segment];
            }
        }
    }
}

if (!function_exists('array_sort')) {
    /**
     * 按指定字段对数组进行排序（不改变键名）
     * 
     * @param array $array 数据数组（引用）
     * @param string $field 排序字段
     * @param string $direction 排序方向（ASC / DESC）
     * @param int $sortFlag 排序标志（SORT_REGULAR / SORT_NUMERIC / SORT_STRING）
     * @return void
     * @example
     * $users = [['id' => 3, 'name' => '王五'], ['id' => 1, 'name' => '张三']];
     * array_sort($users, 'id', 'ASC');
     */
    function array_sort(&$array, $field, $direction = 'ASC', $sortFlag = SORT_REGULAR)
    {
        $direction = strtoupper($direction);
        $sort = [];
        foreach ($array as $key => $row) {
            $sort[$key] = data_get($row, $field);
        }
        
        if ($direction === 'DESC') {
            arsort($sort, $sortFlag);
        } else {
            asort($sort, $sortFlag);
        }
        
        $result = [];
        foreach ($sort as $key => $value) {
            $result[$key] = $array[$key];
        }
        $array = $result;
    }
}

if (!function_exists('array_unique_by')) {
    /**
     * 按指定字段对数组去重
     * 
     * @param array $array 数据数组
     * @param string $field 去重字段
     * @return array
     * @example
     * $users = [['id' => 1, 'name' => '张三'], ['id' => 2, 'name' => '张三']];
     * $unique = array_unique_by($users, 'name'); // 保留第一个
     */
    function array_unique_by($array, $field)
    {
        $result = [];
        $seen = [];
        foreach ($array as $item) {
            $key = data_get($item, $field);
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $result[] = $item;
            }
        }
        return $result;
    }
}

if (!function_exists('array_index_by')) {
    /**
     * 将数组按指定字段重组为以该字段值为键的索引数组
     * 
     * @param array $array 数据数组
     * @param string $field 索引字段
     * @return array
     * @example
     * $users = [['id' => 1, 'name' => '张三'], ['id' => 2, 'name' => '李四']];
     * $indexed = array_index_by($users, 'id');
     * // [1 => ['id'=>1,'name'=>'张三'], 2 => ['id'=>2,'name'=>'李四']]
     */
    function array_index_by($array, $field)
    {
        $result = [];
        foreach ($array as $item) {
            $key = data_get($item, $field);
            $result[$key] = $item;
        }
        return $result;
    }
}
// ─── 统计数量 ───

if (!function_exists('array_count_by')) {
    /**
     * 按指定字段统计数量（返回每个值的出现次数）
     * 
     * @param array $array 数据数组
     * @param string $field 统计字段
     * @return array
     * @example
     * $users = [
     *   ['id' => 1, 'status' => 1],
     *   ['id' => 2, 'status' => 0],
     *   ['id' => 3, 'status' => 1],
     * ];
     * array_count_by($users, 'status'); // [1 => 2, 0 => 1]
     */
    function array_count_by($array, $field)
    {
        $result = [];
        foreach ($array as $item) {
            $key = data_get($item, $field);
            if (!isset($result[$key])) {
                $result[$key] = 0;
            }
            $result[$key]++;
        }
        return $result;
    }
}

if (!function_exists('array_count_value')) {
    /**
     * 统计数组中某个字段值出现的次数
     * 
     * @param array $array 数据数组
     * @param string $field 字段名
     * @param mixed $value 要统计的值
     * @return int
     * @example
     * $users = [
     *   ['id' => 1, 'status' => 1],
     *   ['id' => 2, 'status' => 0],
     *   ['id' => 3, 'status' => 1],
     * ];
     * array_count_value($users, 'status', 1); // 2
     */
    function array_count_value($array, $field, $value)
    {
        $count = 0;
        foreach ($array as $item) {
            $val = data_get($item, $field);
            if ($val == $value) {
                $count++;
            }
        }
        return $count;
    }
}

if (!function_exists('array_sum_by')) {
    /**
     * 按指定字段分组求和
     * 
     * @param array $array 数据数组
     * @param string $groupField 分组字段
     * @param string $sumField 求和字段
     * @return array
     * @example
     * $orders = [
     *   ['user_id' => 1, 'amount' => 100],
     *   ['user_id' => 2, 'amount' => 200],
     *   ['user_id' => 1, 'amount' => 50],
     * ];
     * array_sum_by($orders, 'user_id', 'amount');
     * // [1 => 150, 2 => 200]
     */
    function array_sum_by($array, $groupField, $sumField)
    {
        $result = [];
        foreach ($array as $item) {
            $key = data_get($item, $groupField);
            $value = data_get($item, $sumField);
            if (!isset($result[$key])) {
                $result[$key] = 0;
            }
            $result[$key] += $value;
        }
        return $result;
    }
}

if (!function_exists('array_avg_by')) {
    /**
     * 按指定字段分组求平均值
     * 
     * @param array $array 数据数组
     * @param string $groupField 分组字段
     * @param string $avgField 求平均字段
     * @return array
     * @example
     * $scores = [
     *   ['class' => 'A', 'score' => 80],
     *   ['class' => 'B', 'score' => 90],
     *   ['class' => 'A', 'score' => 100],
     * ];
     * array_avg_by($scores, 'class', 'score');
     * // ['A' => 90, 'B' => 90]
     */
    function array_avg_by($array, $groupField, $avgField)
    {
        $sum = [];
        $count = [];
        foreach ($array as $item) {
            $key = data_get($item, $groupField);
            $value = data_get($item, $avgField);
            if (!isset($sum[$key])) {
                $sum[$key] = 0;
                $count[$key] = 0;
            }
            $sum[$key] += $value;
            $count[$key]++;
        }
        $result = [];
        foreach ($sum as $key => $val) {
            $result[$key] = $val / $count[$key];
        }
        return $result;
    }
}

if (!function_exists('array_max_by')) {
    /**
     * 按指定字段分组求最大值
     * 
     * @param array $array 数据数组
     * @param string $groupField 分组字段
     * @param string $maxField 求最大字段
     * @return array
     */
    function array_max_by($array, $groupField, $maxField)
    {
        $result = [];
        foreach ($array as $item) {
            $key = data_get($item, $groupField);
            $value = data_get($item, $maxField);
            if (!isset($result[$key]) || $value > $result[$key]) {
                $result[$key] = $value;
            }
        }
        return $result;
    }
}

if (!function_exists('array_min_by')) {
    /**
     * 按指定字段分组求最小值
     * 
     * @param array $array 数据数组
     * @param string $groupField 分组字段
     * @param string $minField 求最小字段
     * @return array
     */
    function array_min_by($array, $groupField, $minField)
    {
        $result = [];
        foreach ($array as $item) {
            $key = data_get($item, $groupField);
            $value = data_get($item, $minField);
            if (!isset($result[$key]) || $value < $result[$key]) {
                $result[$key] = $value;
            }
        }
        return $result;
    }
}

if (!function_exists('array_total')) {
    /**
     * 获取数组中指定字段的累计总和
     * 
     * @param array $array 数据数组
     * @param string $field 累计字段
     * @return float
     * @example
     * $orders = [['amount' => 100], ['amount' => 200], ['amount' => 50]];
     * array_total($orders, 'amount'); // 350
     */
    function array_total($array, $field)
    {
        $total = 0;
        foreach ($array as $item) {
            $total += data_get($item, $field);
        }
        return $total;
    }
}

// ═══════════════════════════════════════════════════════════════════════
// 21. 集合操作
// ═══════════════════════════════════════════════════════════════════════

if (!function_exists('collect')) {
    /**
     * 创建集合（支持链式操作）
     * 
     * @param array $items 数据
     * @return \Long\Collection
     * @example
     * $users = collect($data)->where('status', 1)->pluck('name');
     */
    function collect($items = [])
    {
        return new \Long\Collection($items);
    }
}
// ═══════════════════════════════════════════════════════════════════════
// 22. 字符串扩展
// ═══════════════════════════════════════════════════════════════════════

if (!function_exists('str_before')) {
    /**
     * 获取指定字符前的部分
     * 
     * @param string $subject 原字符串
     * @param string $search 搜索字符
     * @return string
     * @example str_before('user@example.com', '@'); // 'user'
     */
    function str_before($subject, $search)
    {
        $pos = strpos($subject, $search);
        if ($pos === false) {
            return $subject;
        }
        return substr($subject, 0, $pos);
    }
}

if (!function_exists('str_after')) {
    /**
     * 获取指定字符后的部分
     * 
     * @param string $subject 原字符串
     * @param string $search 搜索字符
     * @return string
     * @example str_after('user@example.com', '@'); // 'example.com'
     */
    function str_after($subject, $search)
    {
        $pos = strpos($subject, $search);
        if ($pos === false) {
            return $subject;
        }
        return substr($subject, $pos + strlen($search));
    }
}

if (!function_exists('str_snake')) {
    /**
     * 驼峰转下划线（Snake Case）
     * 
     * @param string $value 原字符串
     * @param string $delimiter 分隔符
     * @return string
     * @example str_snake('UserModel'); // 'user_model'
     * @example str_snake('userProfile'); // 'user_profile'
     */
    function str_snake($value, $delimiter = '_')
    {
        if (!ctype_lower($value)) {
            $value = preg_replace('/\s+/u', '', ucwords($value));
            $value = preg_replace('/(.)(?=[A-Z])/u', '$1' . $delimiter, $value);
            $value = strtolower($value);
        }
        return $value;
    }
}

if (!function_exists('str_camel')) {
    /**
     * 下划线转驼峰（Camel Case）
     * 
     * @param string $value 原字符串
     * @param bool $ucfirst 是否首字母大写
     * @return string
     * @example str_camel('user_model'); // 'UserModel'
     * @example str_camel('user_model', false); // 'userModel'
     */
    function str_camel($value, $ucfirst = true)
    {
        $value = str_replace('_', ' ', $value);
        $value = ucwords($value);
        $value = str_replace(' ', '', $value);
        if (!$ucfirst) {
            $value = lcfirst($value);
        }
        return $value;
    }
}
// ═══════════════════════════════════════════════════════════════════════
// 24. 时间扩展
// ═══════════════════════════════════════════════════════════════════════

if (!function_exists('time_ago')) {
    /**
     * 获取相对时间（如：3分钟前）
     * 
     * @param int $timestamp 时间戳
     * @param bool $short 是否短格式
     * @return string
     * @example time_ago(time() - 180); // '3分钟前'
     * @example time_ago(time() - 7200); // '2小时前'
     * @example time_ago(time() - 30, true); // '30秒前'
     */
    function time_ago($timestamp, $short = false)
    {
        $diff = time() - $timestamp;
        
        if ($diff < 0) {
            return '未来';
        }
        
        $units = [
            '年'   => 31536000,
            '月'   => 2592000,
            '周'   => 604800,
            '天'   => 86400,
            '小时' => 3600,
            '分钟' => 60,
            '秒'   => 1,
        ];
        
        foreach ($units as $key => $value) {
            if ($diff >= $value) {
                $num = floor($diff / $value);
                if ($short) {
                    $format = [
                        '年' => 'y', '月' => 'm', '周' => 'w',
                        '天' => 'd', '小时' => 'h', '分钟' => 'm',
                        '秒' => 's'
                    ];
                    return $num . $format[$key] . '前';
                }
                return $num . $key . '前';
            }
        }
        
        return $short ? '刚刚' : '刚刚';
    }
}

if (!function_exists('today_start')) {
    /**
     * 获取今天开始时间戳（00:00:00）
     * 
     * @return int
     * @example today_start(); // 今天00:00:00的时间戳
     */
    function today_start()
    {
        return strtotime(date('Y-m-d 00:00:00'));
    }
}

if (!function_exists('today_end')) {
    /**
     * 获取今天结束时间戳（23:59:59）
     * 
     * @return int
     * @example today_end(); // 今天23:59:59的时间戳
     */
    function today_end()
    {
        return strtotime(date('Y-m-d 23:59:59'));
    }
}

if (!function_exists('days_between')) {
    /**
     * 计算两个日期之间的天数
     * 
     * @param string $date1 日期1（Y-m-d）
     * @param string $date2 日期2（Y-m-d）
     * @return int
     * @example days_between('2026-01-01', '2026-01-10'); // 9
     */
    function days_between($date1, $date2)
    {
        $time1 = strtotime($date1);
        $time2 = strtotime($date2);
        return abs(($time2 - $time1) / 86400);
    }
}