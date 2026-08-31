<?php
// long/Request.php
// LongPHP  请求对象
namespace Long;

class Request
{
    private $get, $post, $json, $server, $files, $cookies, $rawBody, $method, $path = '/', $routeParams = [], $user = [];
    /**
     * 当前控制器名
     * @var string
     */
    protected $controller = '';

    /**
     * 当前方法名（操作名）
     * @var string
     */
    protected $action = '';
    public function __construct()
    {
        $this->get = $_GET;
        $this->post = $_POST;
        $this->server = $_SERVER;
        $this->files = $_FILES;
        $this->cookies = $_COOKIE;
        $this->rawBody = file_get_contents('php://input');
        $this->method = strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');

        // 解析路径
        $this->path = $this->server['REQUEST_URI'] ?? '/';
        if ($pos = strpos($this->path, '?')) $this->path = substr($this->path, 0, $pos);
        $this->path = $this->path !== '/' ? rtrim($this->path, '/') : '/';

        // 自动解析 JSON
        $this->json = [];
        if ($this->isJson()) {
            $decoded = json_decode($this->rawBody, true);
            if (is_array($decoded)) $this->json = $decoded;
        }

        // 处理 PUT/DELETE 表单数据
        if (in_array($this->method, ['PUT', 'DELETE', 'PATCH']) && empty($this->post) && empty($this->json)) {
            parse_str($this->rawBody, $parsed);
            if (!empty($parsed)) $this->post = $parsed;
        }
    }
    /**
     * 设置当前控制器名（由框架调用）
     * @param string $controller
     */
    public function setController($controller)
    {
        $this->controller = $controller;
    }

    /**
     * 设置当前方法名（由框架调用）
     * @param string $action
     */
    public function setAction($action)
    {
        $this->action = $action;
    }

    /**
     * 获取当前控制器名
     * @param bool $full 是否返回完整命名空间
     * @return string
     */
    public function controller($full = false)
    {
        if ($full) {
            return $this->controller;
        }
        // 只返回类名（去掉命名空间）
        $parts = explode('\\', $this->controller);
        return end($parts);
    }

    /**
     * 获取当前方法名（操作名）
     * @param bool $full 是否返回完整方法名
     * @return string
     */
    public function action($full = false)
    {
        return $this->action;
    }
    /**
     * 获取所有参数（包含所有请求方式）
     * @return array
     */
    public function all()
    {
        // ✅ 合并所有来源：路由参数 > GET > POST > JSON > PUT > DELETE
        $data = array_merge(
            $this->routeParams,
            $this->get,
            $this->post,
            $this->json,
            $this->put(),
            $this->delete()
        );
        return $data;
    }

    /**
     * 获取所有输入（别名）
     */
    public function input()
    {
        return $this->all();
    }
    // --- 核心参数获取 ---
    public function param($key = null, $default = null)
    {
        if ($key === null) return array_merge($this->routeParams, $this->get, $this->post, $this->json);
        foreach ([$this->routeParams, $this->get, $this->post, $this->json] as $source) {
            if (isset($source[$key])) return $source[$key];
        }
        return $default;
    }

    public function only($keys) { $all = $this->param(); return array_intersect_key($all, array_flip($keys)); }
    public function except($keys) { $all = $this->param(); return array_diff_key($all, array_flip($keys)); }
    public function has($key) { return array_key_exists($key, $this->param()); }

    // --- 获取各类输入 ---
    public function get($key = null, $default = null) { return $key === null ? $this->get : ($this->get[$key] ?? $default); }
    public function post($key = null, $default = null) { return $key === null ? $this->post : ($this->post[$key] ?? $default); }
    public function json($key = null, $default = null) { return $key === null ? $this->json : ($this->json[$key] ?? $default); }
    public function raw() { return $this->rawBody; }
    public function file($key = null) { return $key === null ? $this->files : ($this->files[$key] ?? null); }
    public function cookie($key = null, $default = null) { return $key === null ? $this->cookies : ($this->cookies[$key] ?? $default); }

    // --- 元信息 ---
    public function method() { return $this->method; }
    public function path() { return $this->path; }
    public function header($key, $default = null) { $key = 'HTTP_' . strtoupper(str_replace('-', '_', $key)); return $this->server[$key] ?? $default; }
    public function ip() { return $this->server['HTTP_X_FORWARDED_FOR'] ?? $this->server['REMOTE_ADDR'] ?? '0.0.0.0'; }

    // --- 请求类型判断 ---
    public function isGet() { return $this->method === 'GET'; }
    public function isPost() { return $this->method === 'POST'; }
    public function isPut() { return $this->method === 'PUT'; }
    public function isDelete() { return $this->method === 'DELETE'; }
    public function isAjax() { return isset($this->server['HTTP_X_REQUESTED_WITH']) && strtolower($this->server['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'; }
    public function isJson() { return strpos($this->header('Content-Type'), 'application/json') !== false; }
    /**
     * 判断是否为表单请求
     * @return bool
     */
    public function isForm()
    {
        $contentType = $this->header('Content-Type', '');
        return strpos($contentType, 'application/x-www-form-urlencoded') !== false
            || strpos($contentType, 'multipart/form-data') !== false;
    }
    // --- 中间件注入用户 ---
    public function setUser($user) { $this->user = $user; }
    public function getUser() { return $this->user; }

    // --- 路由参数（由框架设置） ---
    public function setRouteParams($params) { $this->routeParams = $params; }
    /**
     * 获取 PUT 参数
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    public function put($key = null, $default = null)
    {
        // 如果是 JSON 请求，从 JSON 获取
        if ($this->isJson()) {
            return $this->json($key, $default);
        }
        
        // 如果是表单，从 POST 获取
        if ($this->isForm()) {
            return $this->post($key, $default);
        }
        
        // 解析原始请求体
        parse_str($this->rawBody, $parsed);
        if ($key === null) {
            return $parsed;
        }
        return $parsed[$key] ?? $default;
    }
    /**
     * 获取 DELETE 参数
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    public function delete($key = null, $default = null)
    {
        // DELETE 请求和 PUT 逻辑相同
        return $this->put($key, $default);
    }
}