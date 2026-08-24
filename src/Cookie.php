<?php
// src/Cookie/Cookie.php
// LongPHP Framework - Cookie 管理
// 龙行天下 🐉

namespace Long;

class Cookie
{
    /**
     * 默认配置
     * @var array
     */
    protected static $defaults = [
        'path' => '/',
        'domain' => '',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'lax',
    ];

    /**
     * 设置 Cookie
     * @param string $name 名称
     * @param string $value 值
     * @param int $expire 过期时间（秒），0 表示会话结束后过期
     * @param string $path 路径
     * @param string $domain 域名
     * @param bool $secure 是否仅 HTTPS
     * @param bool $httponly 是否仅 HTTP
     * @param string $samesite SameSite 属性
     * @return bool
     */
    public static function set(
        $name,
        $value,
        $expire = 0,
        $path = null,
        $domain = null,
        $secure = null,
        $httponly = null,
        $samesite = null
    ) {
        $path = $path ?: self::$defaults['path'];
        $domain = $domain ?: self::$defaults['domain'];
        $secure = $secure !== null ? $secure : self::$defaults['secure'];
        $httponly = $httponly !== null ? $httponly : self::$defaults['httponly'];
        $samesite = $samesite ?: self::$defaults['samesite'];

        // 计算过期时间
        if ($expire > 0) {
            $expire = time() + $expire;
        }

        // 值进行 URL 编码
        $value = urlencode($value);

        // 设置 Cookie
        return setcookie($name, $value, [
            'expires' => $expire,
            'path' => $path,
            'domain' => $domain,
            'secure' => $secure,
            'httponly' => $httponly,
            'samesite' => $samesite,
        ]);
    }

    /**
     * 获取 Cookie
     * @param string $name 名称
     * @param mixed $default 默认值
     * @return mixed
     */
    public static function get($name, $default = null)
    {
        if (isset($_COOKIE[$name])) {
            return urldecode($_COOKIE[$name]);
        }
        return $default;
    }

    /**
     * 获取所有 Cookie
     * @return array
     */
    public static function all()
    {
        $cookies = [];
        foreach ($_COOKIE as $key => $value) {
            $cookies[$key] = urldecode($value);
        }
        return $cookies;
    }

    /**
     * 检查 Cookie 是否存在
     * @param string $name 名称
     * @return bool
     */
    public static function has($name)
    {
        return isset($_COOKIE[$name]);
    }

    /**
     * 删除 Cookie
     * @param string $name 名称
     * @param string $path 路径
     * @param string $domain 域名
     * @return bool
     */
    public static function delete($name, $path = null, $domain = null)
    {
        $path = $path ?: self::$defaults['path'];
        $domain = $domain ?: self::$defaults['domain'];

        // 设置过期时间为过去的时间
        return setcookie($name, '', [
            'expires' => time() - 3600,
            'path' => $path,
            'domain' => $domain,
            'secure' => self::$defaults['secure'],
            'httponly' => self::$defaults['httponly'],
            'samesite' => self::$defaults['samesite'],
        ]);
    }

    /**
     * 删除多个 Cookie
     * @param array $names
     * @param string $path
     * @param string $domain
     */
    public static function deleteMultiple($names, $path = null, $domain = null)
    {
        foreach ($names as $name) {
            self::delete($name, $path, $domain);
        }
    }

    /**
     * 清空所有 Cookie
     * @param string $path
     * @param string $domain
     */
    public static function clear($path = null, $domain = null)
    {
        $path = $path ?: self::$defaults['path'];
        $domain = $domain ?: self::$defaults['domain'];

        foreach ($_COOKIE as $name => $value) {
            self::delete($name, $path, $domain);
        }
    }

    /**
     * 设置默认配置
     * @param array $config
     */
    public static function setDefaults($config)
    {
        self::$defaults = array_merge(self::$defaults, $config);
    }

    /**
     * 获取默认配置
     * @return array
     */
    public static function getDefaults()
    {
        return self::$defaults;
    }

    /**
     * 设置加密 Cookie（需要传递密钥）
     * @param string $name
     * @param mixed $value
     * @param int $expire
     * @param string $key 加密密钥
     * @return bool
     */
    public static function setEncrypted($name, $value, $expire = 0, $key = null)
    {
        if ($key === null) {
            $key = config('app.key', '');
        }

        if (empty($key)) {
            throw new \Exception('加密 Cookie 需要设置 app.key');
        }

        // 简单加密（实际项目可用 openssl_encrypt）
        $encoded = base64_encode(json_encode($value));
        $signature = hash_hmac('sha256', $encoded, $key);
        $encrypted = $encoded . '|' . $signature;

        return self::set($name, $encrypted, $expire);
    }

    /**
     * 获取加密 Cookie
     * @param string $name
     * @param mixed $default
     * @param string $key 加密密钥
     * @return mixed
     */
    public static function getEncrypted($name, $default = null, $key = null)
    {
        $value = self::get($name);

        if ($value === null) {
            return $default;
        }

        if ($key === null) {
            $key = config('app.key', '');
        }

        if (empty($key)) {
            throw new \Exception('加密 Cookie 需要设置 app.key');
        }

        $parts = explode('|', $value);
        if (count($parts) !== 2) {
            return $default;
        }

        list($encoded, $signature) = $parts;

        // 验证签名
        if (hash_hmac('sha256', $encoded, $key) !== $signature) {
            return $default;
        }

        return json_decode(base64_decode($encoded), true);
    }
}