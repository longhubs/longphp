<?php
// src/Csrf.php
// LongPHP Framework - CSRF 防护核心类
// 龙行天下 🐉

namespace Long;

class Csrf
{
    /**
     * 获取 CSRF Token
     * 如果 Session 中没有 Token，则自动生成一个
     * 
     * @return string 32 位随机 Token
     * @example $token = Csrf::token();
     */
    public static function token()
    {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * 验证 CSRF Token 是否有效
     * 
     * @param string $token 用户提交的 Token
     * @return bool 验证通过返回 true，失败返回 false
     * @example if (Csrf::verify($_POST['_csrf'])) { // 验证通过 }
     */
    public static function verify($token)
    {
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }
        // 使用 hash_equals 防止时序攻击
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * 生成 CSRF 隐藏域 HTML
     * 用于表单中自动插入 _csrf 隐藏字段
     * 
     * @return string HTML 隐藏域
     * @example <form method="POST"><?= csrf_field() ?></form>
     */
    public static function field()
    {
        return '<input type="hidden" name="_csrf" value="' . self::token() . '">';
    }
}