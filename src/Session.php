<?php
// src/Session/Session.php
// LongPHP Framework - Session 管理
// 龙行天下 🐉

namespace Long;

class Session
{
    /**
     * Session 是否已启动
     * @var bool
     */
    protected static $started = false;

    /**
     * Session 配置
     * @var array
     */
    protected static $config = [];
    /**
     * 单例实例
     * @var self
     */
    protected static $instance = null;
    /**
     * 初始化 Session
     * @param array $config 配置
     */
    public static function init($config = [])
    {
        self::$config = array_merge([
            'name' => 'LONGPHP_SESSION',
            'lifetime' => 7200,
            'path' => ROOT_PATH . '/runtime/sessions/',
            'httponly' => true,
            'secure' => false,
            'samesite' => 'lax',
        ], $config);

        session_name(self::$config['name']);
        session_save_path(self::$config['path']);

        // ✅ 设置 Session 过期时间
        ini_set('session.gc_maxlifetime', self::$config['lifetime']);
        ini_set('session.cookie_lifetime', self::$config['lifetime']);

        session_set_cookie_params([
            'lifetime' => self::$config['lifetime'],
            'path' => '/',
            'domain' => '',
            'secure' => self::$config['secure'],
            'httponly' => self::$config['httponly'],
            'samesite' => self::$config['samesite'],
        ]);

        self::start();
    }

    /**
     * 获取单例实例
     * @return self
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    /**
     * 启动 Session
     */
    public static function start()
    {
        if (!self::$started) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            self::$started = true;
        }
    }

    /**
     * 设置 Session 值
     * @param string $key 键名
     * @param mixed $value 值
     * @return void
     */
    public static function set($key, $value)
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    /**
     * 批量设置 Session
     * @param array $data
     */
    public static function setMultiple($data)
    {
        self::start();
        foreach ($data as $key => $value) {
            $_SESSION[$key] = $value;
        }
    }

    /**
     * 获取 Session 值
     * @param string $key 键名
     * @param mixed $default 默认值
     * @return mixed
     */
    public static function get($key, $default = null)
    {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    /**
     * 获取所有 Session 数据
     * @return array
     */
    public static function all()
    {
        self::start();
        return $_SESSION;
    }

    /**
     * 检查 Session 是否存在
     * @param string $key
     * @return bool
     */
    public static function has($key)
    {
        self::start();
        return isset($_SESSION[$key]);
    }

    /**
     * 删除 Session
     * @param string $key
     * @return void
     */
    public static function delete($key)
    {
        self::start();
        unset($_SESSION[$key]);
    }

    /**
     * 删除多个 Session
     * @param array $keys
     */
    public static function deleteMultiple($keys)
    {
        self::start();
        foreach ($keys as $key) {
            unset($_SESSION[$key]);
        }
    }

    /**
     * 清空所有 Session
     * @return void
     */
    public static function clear()
    {
        self::start();
        $_SESSION = [];
    }

    /**
     * 销毁 Session（完全清除）
     * @return void
     */
    public static function destroy()
    {
        if (self::$started) {
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params['path'],
                    $params['domain'],
                    $params['secure'],
                    $params['httponly']
                );
            }
            session_destroy();
            self::$started = false;
        }
    }

    /**
     * 设置一次性 Session（闪存）
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public static function flash($key, $value)
    {
        self::start();
        $_SESSION['_flash'][$key] = $value;
    }

    /**
     * 获取闪存数据（获取后自动删除）
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function getFlash($key, $default = null)
    {
        self::start();
        $value = $_SESSION['_flash'][$key] ?? $default;
        unset($_SESSION['_flash'][$key]);
        return $value;
    }

    /**
     * 检查是否有闪存数据
     * @param string $key
     * @return bool
     */
    public static function hasFlash($key)
    {
        self::start();
        return isset($_SESSION['_flash'][$key]);
    }

    /**
     * 获取 Session ID
     * @return string
     */
    public static function getId()
    {
        self::start();
        return session_id();
    }

    /**
     * 重新生成 Session ID
     * @param bool $deleteOld 是否删除旧 Session
     * @return bool
     */
    public static function regenerate($deleteOld = true)
    {
        self::start();
        return session_regenerate_id($deleteOld);
    }

    /**
     * 保存 Session 并关闭
     */
    public static function save()
    {
        if (self::$started) {
            session_write_close();
            self::$started = false;
        }
    }

    /**
     * 获取 Session 状态
     * @return int
     */
    public static function status()
    {
        return session_status();
    }

    /**
     * 是否已启动
     * @return bool
     */
    public static function isStarted()
    {
        return self::$started;
    }
}