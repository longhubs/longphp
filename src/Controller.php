<?php
// src/Controller.php
// LongPHP Framework - 控制器基类
// 龙行天下 🐉

namespace Long;

use Long\View;
use Long\App;  // ⬅️ 添加引用

class Controller
{
    /**
     * 请求实例
     * @var Request
     */
    protected $request;

    /**
     * 响应码配置
     * @var array
     */
    protected $codes = [];
    
    /**
     * App 实例
     * @var App
     */
    protected $app;  // ⬅️ 添加 App 实例

    /**
     * 构造函数
     */
    public function __construct()
    {
        // ⬅️ 获取 App 实例
        $this->app = App::getInstance();
        
        $this->request = new Request();
        
        // ✅ 使用框架的 getConfigPath() 方法
        $configFile = $this->app->getConfigPath('response.php');
        $this->codes = file_exists($configFile) ? require $configFile : [];

        // ✅ 自动调用 initialize()（如果子类定义了该方法）
        if (method_exists($this, 'initialize')) {
            $this->initialize();
        }
    }

    // ─────────────────────────────────────────────────────────────
    // 视图渲染
    // ─────────────────────────────────────────────────────────────

    /**
     * 渲染视图（支持 Blade 和 PHP 原生）
     * @param string $view 视图名称（如 'user.detail'）
     * @param array $data 传递到模板的数据
     * @param string $engine 引擎：blade 或 php
     * @return string
     */
    protected function view($view, $data = [], $engine = 'auto')
    {
        return View::render($view, $data, $engine);
    }
    
    protected function assign($name, $value = null)
    {
        View::assign($name, $value);
        return $this;
    }
    
    // ─────────────────────────────────────────────────────────────
    // JSON 响应
    // ─────────────────────────────────────────────────────────────

    /**
     * 统一 JSON 响应
     * @param mixed $data 数据
     * @param int $code 状态码
     * @param string $msg 消息
     * @return string
     */
    protected function json($data, $code = 0, $msg = '')
    {
        header('Content-Type: application/json');
        return json_encode([
            'code' => $code,
            'msg'  => $msg,
            'data' => $data
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * 成功响应
     * @param mixed $data 数据
     * @param string $msg 消息
     * @return string
     */
    protected function success($data = [], $msg = '成功')
    {
        return $this->json($data, 0, $msg);
    }

    /**
     * 错误响应
     * @param string $msg 消息
     * @param int $code 状态码
     * @param array $data 数据
     * @return string
     */
    protected function error($msg = '错误', $code = 1, $data = [])
    {
        return $this->json($data, $code, $msg);
    }

    /**
     * 获取响应码
     * @param string $key 响应码键名
     * @return int
     */
    protected function getCode($key = 'SUCCESS')
    {
        return $this->codes[$key] ?? ($key === 'SUCCESS' ? 2000 : 3000);
    }

    /**
     * 带响应码的 JSON 响应
     * @param mixed $data
     * @param string $msg
     * @param string $codeKey
     * @param int $httpCode
     * @return string
     */
    protected function response($data = [], $msg = '', $codeKey = 'SUCCESS', $httpCode = 200)
    {
        http_response_code($httpCode);
        header('Content-Type: application/json');
        return json_encode([
            'code' => $this->getCode($codeKey),
            'msg'  => $msg ?: $this->getDefaultMsg($codeKey),
            'data' => $data
        ], JSON_UNESCAPED_UNICODE);
    }

    // ─────────────────────────────────────────────────────────────
    // 私有方法
    // ─────────────────────────────────────────────────────────────

    /**
     * 获取默认消息
     * @param string $codeKey
     * @return string
     */
    private function getDefaultMsg($codeKey)
    {
        $msgs = [
            'SUCCESS'           => '操作成功',
            'CREATED'           => '创建成功',
            'UPDATED'           => '更新成功',
            'DELETED'           => '删除成功',
            'UPLOADED'          => '上传成功',
            'LOGIN_SUCCESS'     => '登录成功',
            'LOGOUT_SUCCESS'    => '退出成功',
            'REFRESH_SUCCESS'   => '刷新成功',
            'ERROR'             => '操作失败',
            'BAD_REQUEST'       => '请求参数错误',
            'UNAUTHORIZED'      => '请先登录',
            'FORBIDDEN'         => '没有操作权限',
            'NOT_FOUND'         => '资源不存在',
            'VALIDATE_ERROR'    => '数据验证失败',
            'LOGIN_ERROR'       => '登录失败',
            'TOKEN_EXPIRED'     => 'Token已过期，请重新登录',
            'TOKEN_INVALID'     => 'Token无效',
            'USER_EXISTS'       => '用户已存在',
            'USER_NOT_FOUND'    => '用户不存在',
            'PASSWORD_ERROR'    => '密码错误',
            'UPLOAD_ERROR'      => '上传失败',
            'SERVER_ERROR'      => '服务器内部错误',
        ];
        return $msgs[$codeKey] ?? '操作完成';
    }

    // ─────────────────────────────────────────────────────────────
    // 便捷方法（子类可重写）
    // ─────────────────────────────────────────────────────────────

    /**
     * 获取请求对象
     * @return Request
     */
    protected function getRequest()
    {
        return $this->request;
    }

    /**
     * 获取当前用户（从 Token 解析）
     * @return array|null
     */
    protected function getUser()
    {
        return jwt_user();
    }

    /**
     * 检查是否已登录
     * @return bool
     */
    protected function isLogin()
    {
        return !empty($this->getUser());
    }
}