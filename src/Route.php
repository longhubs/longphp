<?php
// long/Route.php
// LongPHP Framework - 路由类

namespace Long;

class Route
{
    private $routes = [];
    private $currentGroup = null;
    private $missHandler = null;

    // ─────────────────────────────────────────────────────────────
    // 路由注册
    // ─────────────────────────────────────────────────────────────

    public function add($method, $uri, $handler, $middleware = [])
    {
        $this->routes[] = [
            'method'     => strtoupper($method),
            'uri'        => $uri,
            'handler'    => $handler,
            'middleware' => $middleware,
            'group'      => $this->currentGroup
        ];
        return $this;
    }

    public function get($uri, $handler, $middleware = [])
    {
        return $this->add('GET', $uri, $handler, $middleware);
    }

    public function post($uri, $handler, $middleware = [])
    {
        return $this->add('POST', $uri, $handler, $middleware);
    }

    public function put($uri, $handler, $middleware = [])
    {
        return $this->add('PUT', $uri, $handler, $middleware);
    }

    public function delete($uri, $handler, $middleware = [])
    {
        return $this->add('DELETE', $uri, $handler, $middleware);
    }

    public function any($uri, $handler, $middleware = [])
    {
        return $this->add('ANY', $uri, $handler, $middleware);
    }

    /**
     * 注册路由（支持所有请求方法）
     */
    public function rule($uri, $handler, $method = 'ANY', $middleware = [])
    {
        if (is_string($method) && strpos($method, '|') !== false) {
            $methods = explode('|', $method);
            foreach ($methods as $m) {
                $this->add(trim($m), $uri, $handler, $middleware);
            }
            return $this;
        }
        
        if (is_array($method)) {
            foreach ($method as $m) {
                $this->add(trim($m), $uri, $handler, $middleware);
            }
            return $this;
        }
        
        return $this->add($method, $uri, $handler, $middleware);
    }

    // ─────────────────────────────────────────────────────────────
    // 路由分组
    // ─────────────────────────────────────────────────────────────

    public function group($prefix, $callback, $middleware = [])
    {
        $previousGroup = $this->currentGroup;
        $this->currentGroup = [
            'prefix'     => $prefix,
            'middleware' => $middleware
        ];
        $callback($this);
        $this->currentGroup = $previousGroup;
    }

    // ─────────────────────────────────────────────────────────────
    // 资源路由
    // ─────────────────────────────────────────────────────────────

    public function resource($uri, $controller, $middleware = [])
    {
        $this->get($uri, "{$controller}@index", $middleware);
        $this->get($uri . '/{id}', "{$controller}@show", $middleware);
        $this->post($uri, "{$controller}@store", $middleware);
        $this->put($uri . '/{id}', "{$controller}@update", $middleware);
        $this->delete($uri . '/{id}', "{$controller}@delete", $middleware);
    }

    // ─────────────────────────────────────────────────────────────
    // 404 路由
    // ─────────────────────────────────────────────────────────────

    /**
     * 注册 404 路由（miss 方法）
     */
    public function miss($handler)
    {
        $this->missHandler = $handler;
        return $this;
    }

    /**
     * 获取 404 处理器
     */
    public function getMissHandler()
    {
        return $this->missHandler;
    }

    // ─────────────────────────────────────────────────────────────
    // 调试方法
    // ─────────────────────────────────────────────────────────────

    public function getRoutes()
    {
        return $this->routes;
    }

    // ─────────────────────────────────────────────────────────────
    // 路由匹配
    // ─────────────────────────────────────────────────────────────

    public function dispatch($request)
    {
        $method = $request->method();
        $uri = $this->normalizeUri($request->path());

        // 1. 先匹配普通路由
        foreach ($this->routes as $route) {
            // 方法匹配（ANY 匹配所有）
            if ($route['method'] !== 'ANY' && $route['method'] !== $method) {
                continue;
            }

            // 处理分组前缀
            $routeUri = $this->buildRouteUri($route);

            // ✅ 跳过通配符 *（不在这里处理）
            if ($routeUri === '*' || $routeUri === '/*') {
                continue;
            }

            // 静态匹配
            if ($routeUri === $uri) {
                return $this->buildRouteInfo($route, $uri);
            }

            // ✅ 只有非通配符才进行正则匹配
            // 动态匹配
            $pattern = '#^' . preg_replace('/\{[a-zA-Z_]+\}/', '([a-zA-Z0-9_\-]+)', $routeUri) . '$#';
            if (preg_match($pattern, $uri, $matches)) {
                array_shift($matches);
                return $this->buildRouteInfo($route, $uri, $matches);
            }
        }

        // 2. 匹配通配符路由 *（any('*')）
        foreach ($this->routes as $route) {
            $routeUri = $this->buildRouteUri($route);
            if (($routeUri === '*' || $routeUri === '/*') && $route['method'] === 'ANY') {
                return $this->buildRouteInfo($route, $uri);
            }
        }

        // 3. 匹配通配符路由 *（指定方法）
        foreach ($this->routes as $route) {
            $routeUri = $this->buildRouteUri($route);
            if (($routeUri === '*' || $routeUri === '/*') && $route['method'] === $method) {
                return $this->buildRouteInfo($route, $uri);
            }
        }

        // 4. 使用 miss 方法（404）
        if ($this->missHandler) {
            return $this->buildMissRouteInfo($uri);
        }

        return null;
    }

    // ─────────────────────────────────────────────────────────────
    // 内部辅助方法
    // ─────────────────────────────────────────────────────────────

    private function normalizeUri($uri)
    {
        if (empty($uri) || $uri === '') {
            $uri = '/';
        }
        if ($uri !== '/') {
            $uri = rtrim($uri, '/');
        }
        return $uri;
    }

    private function buildRouteUri($route)
    {
        $uri = $route['uri'];
        
        // 通配符 * 直接返回，不处理分组前缀
        if ($uri === '*' || $uri === '/*') {
            return $uri;
        }
        
        if ($uri === '') {
            $uri = '/';
        }
        
        if ($route['group'] && isset($route['group']['prefix'])) {
            $prefix = rtrim($route['group']['prefix'], '/');
            if ($uri === '/') {
                $uri = $prefix;
            } else {
                $uri = $prefix . '/' . ltrim($uri, '/');
            }
        }
        
        return $uri;
    }

    private function buildRouteInfo($route, $uri, $params = [])
    {
        $handler = $route['handler'];
        $middleware = $route['middleware'] ?? [];

        if ($route['group'] && isset($route['group']['middleware'])) {
            $middleware = array_merge($route['group']['middleware'], $middleware);
        }

        if (is_callable($handler)) {
            return [
                'controller' => $handler,
                'action'     => '__invoke',
                'params'     => $params,
                'middleware' => $middleware,
                'is_closure' => true
            ];
        }

        list($controller, $action) = explode('@', $handler);
        if (strpos($controller, '\\') === false) {
            $controller = 'App\\Controller\\' . $controller;
        }

        return [
            'controller' => $controller,
            'action'     => $action,
            'params'     => $params,
            'middleware' => $middleware,
            'is_closure' => false
        ];
    }

    /**
     * 构建 404 路由信息
     */
    private function buildMissRouteInfo($uri)
    {
        $handler = $this->missHandler;

        if (is_callable($handler)) {
            return [
                'controller' => $handler,
                'action'     => '__invoke',
                'params'     => [],
                'middleware' => [],
                'is_closure' => true,
                'is_miss'    => true
            ];
        }

        list($controller, $action) = explode('@', $handler);
        if (strpos($controller, '\\') === false) {
            $controller = 'App\\Controller\\' . $controller;
        }

        return [
            'controller' => $controller,
            'action'     => $action,
            'params'     => [],
            'middleware' => [],
            'is_closure' => false,
            'is_miss'    => true
        ];
    }
}