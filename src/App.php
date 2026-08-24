<?php
// src/App.php
// LongPHP Framework - 应用核心类
// 龙行天下 🐉

namespace Long;

class App
{
    /**
     * 应用配置数组
     * @var array
     */
    protected $config = [];

    /**
     * 路由实例
     * @var Route
     */
    protected $route;

    /**
     * 请求实例
     * @var Request
     */
    protected $request;

    /**
     * 项目根目录路径
     * @var string
     */
    protected $rootPath;

    /**
     * 单例实例
     * @var self
     */
    protected static $instance;

    /**
     * 中间件配置
     * @var array
     */
    protected $middleware = [];

    // ─────────────────────────────────────────────────────────────
    // 构造函数
    // ─────────────────────────────────────────────────────────────

    /**
     * 构造函数：初始化应用
     * 加载环境变量、系统函数、配置、中间件、服务、异常处理、路由
     */
    public function __construct()
    {
        // 加载 .env 环境变量
        Env::load();
        
        // 加载系统辅助函数
        $this->loadHelper();
        
        // 设置项目根目录
        $this->rootPath = ROOT_PATH;
        self::$instance = $this;
        
        // 加载配置文件
        $this->config = $this->loadConfig();
        
        // 加载中间件配置
        $this->loadMiddlewareConfig();
        
        // 注册服务
        $this->registerServices();
        
        // 加载事件配置
        $this->loadEventConfig();
        
        // 初始化异常处理
        $this->initException();
        
        // 初始化路由
        $this->route = new Route();
        $this->loadRoutes();
    }

    // ─────────────────────────────────────────────────────────────
    // 加载方法
    // ─────────────────────────────────────────────────────────────

    /**
     * 加载系统辅助函数
     */
    protected function loadHelper()
    {
        require_once __DIR__ . '/Helper.php';
    }

    /**
     * 加载配置文件
     * @return array
     */
    protected function loadConfig()
    {
        $configFile = ROOT_PATH . '/config/app.php';
        return file_exists($configFile) ? require $configFile : [];
    }

    /**
     * 加载中间件配置
     */
    protected function loadMiddlewareConfig()
    {
        $middlewareFile = ROOT_PATH . '/config/middleware.php';
        if (file_exists($middlewareFile)) {
            $this->middleware = require $middlewareFile;
        }
    }

    /**
     * 加载事件配置
     */
    protected function loadEventConfig()
    {
        $eventFile = ROOT_PATH . '/config/event.php';
        if (file_exists($eventFile)) {
            $config = require $eventFile;
            $listeners = $config['listeners'] ?? [];
            
            $manager = \Long\EventManager::getInstance();
            foreach ($listeners as $event => $eventListeners) {
                foreach ((array)$eventListeners as $listener) {
                    $manager->listen($event, $listener);
                }
            }
        }
    }

    /**
     * 加载路由文件（自动加载 route/ 目录下所有 .php 文件）
     */
    protected function loadRoutes()
    {
        $routeDir = ROOT_PATH . '/route/';
        $files = glob($routeDir . '*.php');
        
        foreach ($files as $file) {
            $route = $this->route;
            require $file;
        }
    }

    // ─────────────────────────────────────────────────────────────
    // 服务注册
    // ─────────────────────────────────────────────────────────────

    /**
     * 注册服务到容器
     * 包括默认服务和用户自定义服务
     */
    protected function registerServices()
    {
        $container = Container::getInstance();
        
        // 注册默认服务
        $container->singleton('config', function() {
            return $this->config;
        });
        
        $container->singleton('request', function() {
            return $this->request;
        });
        
        // 加载用户自定义服务配置
        $providerFile = ROOT_PATH . '/config/provider.php';
        if (file_exists($providerFile)) {
            $services = require $providerFile;

            // 接口绑定
            foreach ($services['binds'] ?? [] as $abstract => $concrete) {
                $container->bind($abstract, $concrete);
            }
            
            // 单例绑定
            foreach ($services['singletons'] ?? [] as $abstract => $concrete) {
                $container->singleton($abstract, $concrete);
            }
            
            // 别名绑定
            foreach ($services['aliases'] ?? [] as $alias => $abstract) {
                $container->alias($alias, $abstract);
            }
        }
    }

    /**
     * 加载用户服务绑定（延迟加载，在控制器执行前调用）
     */
    protected function loadProviderBindings()
    {
        static $loaded = false;
        
        if ($loaded) {
            return;
        }
        
        $providerFile = ROOT_PATH . '/config/provider.php';
        if (file_exists($providerFile)) {
            $services = require $providerFile;
            $container = Container::getInstance();
            
            // 接口绑定
            foreach ($services['binds'] ?? [] as $abstract => $concrete) {
                $container->bind($abstract, $concrete);
            }
            
            // 单例绑定
            foreach ($services['singletons'] ?? [] as $abstract => $concrete) {
                $container->singleton($abstract, $concrete);
            }
            
            // 别名绑定
            foreach ($services['aliases'] ?? [] as $alias => $abstract) {
                $container->alias($alias, $abstract);
            }
        }
        
        $loaded = true;
    }

    // ─────────────────────────────────────────────────────────────
    // 异常处理
    // ─────────────────────────────────────────────────────────────

    /**
     * 初始化异常处理
     */
    protected function initException()
    {
        $debug = $this->config['debug'] ?? false;

        if ($debug) {
            error_reporting(E_ALL);
            ini_set('display_errors', 1);
        } else {
            error_reporting(0);
            ini_set('display_errors', 0);
        }

        Exception::init($debug);
    }

    // ─────────────────────────────────────────────────────────────
    // 单例
    // ─────────────────────────────────────────────────────────────

    /**
     * 获取应用单例实例
     * @return self
     */
    public static function getInstance()
    {
        return self::$instance;
    }

    // ─────────────────────────────────────────────────────────────
    // 获取器
    // ─────────────────────────────────────────────────────────────

    /**
     * 获取项目根目录
     * @return string
     */
    public function getRootPath()
    {
        return $this->rootPath;
    }

    /**
     * 获取配置
     * @param string|null $key 配置键名
     * @param mixed $default 默认值
     * @return mixed
     */
    public function getConfig($key = null, $default = null)
    {
        if ($key === null) {
            return $this->config;
        }
        return $this->config[$key] ?? $default;
    }

    /**
     * 获取路由实例
     * @return Route
     */
    public function getRoute()
    {
        return $this->route;
    }

    /**
     * 获取请求实例
     * @return Request
     */
    public function getRequest()
    {
        return $this->request;
    }

    // ─────────────────────────────────────────────────────────────
    // 应用运行
    // ─────────────────────────────────────────────────────────────

    /**
     * 运行应用
     * 执行路由匹配、中间件、控制器，返回响应
     * @return Response
     */
    public function run()
    {
        $this->request = new Request();

        $routeInfo = $this->route->dispatch($this->request);

        if (!$routeInfo) {
            return new Response('路由未找到', 404);
        }

        $result = $this->runWithMiddleware($routeInfo);
        
        // 统一返回 Response 对象
        if ($result instanceof Response) {
            return $result;
        }
        
        if (is_string($result)) {
            return new Response($result, 200);
        }
        
        if ($result === null) {
            return new Response('', 200);
        }
        
        return new Response('', 200);
    }

    /**
     * 执行中间件链
     * @param array $routeInfo 路由信息
     * @return mixed
     */
    protected function runWithMiddleware($routeInfo)
    {
        // 合并全局中间件和路由中间件
        $global = $this->middleware['global'] ?? [];
        $routeMiddlewares = $routeInfo['middleware'] ?? [];
        $middlewares = array_merge($global, $routeMiddlewares);

        $request = $this->request;

        $next = function($request) use ($routeInfo) {
            return $this->execute($routeInfo);
        };

        // 洋葱模型：从后往前包裹中间件
        foreach (array_reverse($middlewares) as $middlewareClass) {
            $next = function($request) use ($middlewareClass, $next) {
                if (!class_exists($middlewareClass)) {
                    return "中间件不存在: {$middlewareClass}";
                }
                $middleware = new $middlewareClass();
                $result = $middleware->handle($request, $next);
                
                if (is_string($result)) {
                    return $result;
                }
                return $result;
            };
        }

        return $next($request);
    }

    /**
     * 执行控制器
     * @param array $routeInfo 路由信息
     * @return string
     */
    protected function execute($routeInfo)
    {
        // 闭包路由
        if (isset($routeInfo['is_closure']) && $routeInfo['is_closure']) {
            return $routeInfo['controller'](...$routeInfo['params']);
        }

        $controllerClass = $routeInfo['controller'];
        $action = $routeInfo['action'];
        $params = $routeInfo['params'] ?? [];

        if (!class_exists($controllerClass)) {
            return "控制器不存在: {$controllerClass}";
        }

        // 在解析控制器之前加载用户服务绑定
        $this->loadProviderBindings();

        // 使用容器解析控制器（自动注入构造函数依赖）
        $controller = app($controllerClass);

        if (!method_exists($controller, $action)) {
            return "方法不存在: {$action}";
        }

        // 方法参数依赖注入（自动注入 Request）
        $reflection = new \ReflectionMethod($controller, $action);
        $args = [];

        foreach ($reflection->getParameters() as $param) {
            $type = $param->getType();

            if ($type && !$type->isBuiltin()) {
                $typeName = $type->getName();
                if ($typeName === 'Long\Request' || $typeName === 'Request') {
                    $args[] = $this->request;
                    continue;
                }
            }

            $args[] = array_shift($params);
        }

        return $controller->$action(...$args);
    }

    // ─────────────────────────────────────────────────────────────
    // 应用结束
    // ─────────────────────────────────────────────────────────────

    /**
     * 结束应用（收尾工作）
     * @param Response $response 响应对象
     */
    public function end($response)
    {
        if (function_exists('logs')) {
            logs("请求结束: " . ($_SERVER['REQUEST_URI'] ?? '/'), 'info');
        }
    }
}