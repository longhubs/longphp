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
    protected $basePath;

    /**
     * 自定义路径
     */
    protected $appPath;
    protected $configPath;
    protected $storagePath;
    protected $runtimePath;
    protected $publicPath;

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

    /**
     * 是否已加载 Helper
     */
    protected $helperLoaded = false;

    // ─────────────────────────────────────────────────────────────
    // 构造函数
    // ─────────────────────────────────────────────────────────────

    public function __construct()
    {
        $basePath =  dirname(__DIR__,4);
        self::$instance = $this;
    
        // 设置根目录（关键步骤）
        $this->setBasePath($basePath);

        // 2. 加载 .env 环境变量
        Env::load();

        // 3. 加载系统辅助函数
        $this->loadHelper();

        // 4. 加载配置文件
        $this->config = $this->loadConfig();

        // 5. 加载中间件配置
        $this->loadMiddlewareConfig();

        // 6. 注册服务
        $this->registerServices();

        // 7. 加载事件配置
        $this->loadEventConfig();

        // 8. 初始化异常处理
        $this->initException();

        // 9. 初始化路由
        $this->route = new Route();
        $this->loadRoutes();
    }

    // ============================================================
    // 路径管理
    // ============================================================

    /**
     * 设置根目录
     */
    public function setBasePath($path)
    {
        $this->basePath = rtrim($path, '/\\');
        return $this;
    }

    /**
     * 设置应用目录
     */
    public function setAppPath($path)
    {
        $this->appPath = rtrim($path, '/\\');
        return $this;
    }

    /**
     * 设置配置目录
     */
    public function setConfigPath($path)
    {
        $this->configPath = rtrim($path, '/\\');
        return $this;
    }

    /**
     * 设置存储目录
     */
    public function setStoragePath($path)
    {
        $this->storagePath = rtrim($path, '/\\');
        return $this;
    }

    /**
     * 设置运行日志目录
     */
    public function setRuntimePath($path)
    {
        $this->runtimePath = rtrim($path, '/\\');
        return $this;
    }

    /**
     * 设置公共目录
     */
    public function setPublicPath($path)
    {
        $this->publicPath = rtrim($path, '/\\');
        return $this;
    }

    /**
     * 获取根目录
     */
    public function getBasePath($path = '')
    {
        return $this->basePath . ($path ? DIRECTORY_SEPARATOR . $path : '');
    }

    /**
     * 获取应用目录
     */
    public function getAppPath($path = '')
    {
        $dir = $this->appPath ?: $this->basePath . DIRECTORY_SEPARATOR . 'app';
        return $dir . ($path ? DIRECTORY_SEPARATOR . $path : '');
    }

    /**
     * 获取配置目录
     */
    public function getConfigPath($path = '')
    {
        $dir = $this->configPath ?: $this->basePath . DIRECTORY_SEPARATOR . 'config';
        return $dir . ($path ? DIRECTORY_SEPARATOR . $path : '');
    }

    /**
     * 获取存储目录
     */
    public function getStoragePath($path = '')
    {
        $dir = $this->storagePath ?: $this->basePath . DIRECTORY_SEPARATOR . 'storage';
        return $dir . ($path ? DIRECTORY_SEPARATOR . $path : '');
    }

    /**
     * 获取运行日志目录
     */
    public function getRuntimePath($path = '')
    {
        $dir = $this->runtimePath ?: $this->basePath . DIRECTORY_SEPARATOR . 'runlogs';
        return $dir . ($path ? DIRECTORY_SEPARATOR . $path : '');
    }

    /**
     * 获取公共目录
     */
    public function getPublicPath($path = '')
    {
        $dir = $this->publicPath ?: $this->basePath . DIRECTORY_SEPARATOR . 'public';
        return $dir . ($path ? DIRECTORY_SEPARATOR . $path : '');
    }

    /**
     * 获取 .env 文件路径
     */
    public function getEnvPath()
    {
        return $this->basePath . DIRECTORY_SEPARATOR . '.env';
    }

    /**
     * 确保目录存在
     */
    public function ensureDirectory($path)
    {
        if (!is_dir($path)) {
            @mkdir($path, 0755, true);
        }
        return $path;
    }

    /**
     * 确保常用目录存在
     */
    public function ensureDirectories()
    {
        $this->ensureDirectory($this->getRuntimePath());
        $this->ensureDirectory($this->getRuntimePath('logs'));
        $this->ensureDirectory($this->getRuntimePath('cache'));
        $this->ensureDirectory($this->getStoragePath());
        return $this;
    }

    /**
     * 获取根目录（兼容旧代码）
     * @deprecated 使用 getBasePath()
     */
    public function getRootPath()
    {
        return $this->basePath;
    }

    // ─────────────────────────────────────────────────────────────
    // 加载方法
    // ─────────────────────────────────────────────────────────────

    /**
     * 加载系统辅助函数
     */
    protected function loadHelper()
    {
        if ($this->helperLoaded) {
            return;
        }
        $helperPath = __DIR__ . '/Helper.php';
        if (file_exists($helperPath)) {
            require_once $helperPath;
            $this->helperLoaded = true;
        }
    }

    /**
     * 加载配置文件
     * @return array
     */
    protected function loadConfig()
    {
        $configFile = $this->getConfigPath('app.php');
        return file_exists($configFile) ? require $configFile : [];
    }

    /**
     * 加载中间件配置
     */
    protected function loadMiddlewareConfig()
    {
        $middlewareFile = $this->getConfigPath('middleware.php');
        if (file_exists($middlewareFile)) {
            $this->middleware = require $middlewareFile;
        }
    }

    /**
     * 加载事件配置
     */
    protected function loadEventConfig()
    {
        $eventFile = $this->getConfigPath('event.php');
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
        $routeDir = $this->basePath . DIRECTORY_SEPARATOR . 'route';
        if (!is_dir($routeDir)) {
            return;
        }
        $files = glob($routeDir . DIRECTORY_SEPARATOR . '*.php');
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
     */
    protected function registerServices()
    {
        $container = Container::getInstance();
        
        $container->singleton('app', function() {
            return $this;
        });
        
        $container->singleton('config', function() {
            return $this->config;
        });
        
        $container->singleton('request', function() {
            return $this->request;
        });
        
        // 加载用户自定义服务配置
        $providerFile = $this->getConfigPath('provider.php');
        if (file_exists($providerFile)) {
            $services = require $providerFile;

            foreach ($services['binds'] ?? [] as $abstract => $concrete) {
                $container->bind($abstract, $concrete);
            }
            foreach ($services['singletons'] ?? [] as $abstract => $concrete) {
                $container->singleton($abstract, $concrete);
            }
            foreach ($services['aliases'] ?? [] as $alias => $abstract) {
                $container->alias($alias, $abstract);
            }
        }
    }

    /**
     * 加载用户服务绑定（延迟加载）
     */
    protected function loadProviderBindings()
    {
        static $loaded = false;
        if ($loaded) {
            return;
        }
        
        $providerFile = $this->getConfigPath('provider.php');
        if (file_exists($providerFile)) {
            $services = require $providerFile;
            $container = Container::getInstance();
            
            foreach ($services['binds'] ?? [] as $abstract => $concrete) {
                $container->bind($abstract, $concrete);
            }
            foreach ($services['singletons'] ?? [] as $abstract => $concrete) {
                $container->singleton($abstract, $concrete);
            }
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
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // ─────────────────────────────────────────────────────────────
    // 获取器
    // ─────────────────────────────────────────────────────────────

    /**
     * 获取配置
     * @param string|null $key
     * @param mixed $default
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
     */
    public function run()
    {
        $this->request = new Request();

        $routeInfo = $this->route->dispatch($this->request);

        if (!$routeInfo) {
            return new Response('路由未找到', 404);
        }

        $result = $this->runWithMiddleware($routeInfo);
        
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
     */
    protected function runWithMiddleware($routeInfo)
    {
        $global = $this->middleware['global'] ?? [];
        $routeMiddlewares = $routeInfo['middleware'] ?? [];
        $middlewares = array_merge($global, $routeMiddlewares);

        $request = $this->request;

        $next = function($request) use ($routeInfo) {
            return $this->execute($routeInfo);
        };

        foreach (array_reverse($middlewares) as $middlewareClass) {
            $next = function($request) use ($middlewareClass, $next) {
                if (!class_exists($middlewareClass)) {
                    return "中间件不存在: {$middlewareClass}";
                }
                $middleware = new $middlewareClass();
                return $middleware->handle($request, $next);
            };
        }

        return $next($request);
    }

    /**
     * 执行控制器
     */
    protected function execute($routeInfo)
    {
        if (isset($routeInfo['is_closure']) && $routeInfo['is_closure']) {
            return $routeInfo['controller'](...$routeInfo['params']);
        }

        $controllerClass = $routeInfo['controller'];
        $action = $routeInfo['action'];
        $params = $routeInfo['params'] ?? [];

        $this->request->setController($controllerClass);
        $this->request->setAction($action);

        if (!class_exists($controllerClass)) {
            return "控制器不存在: {$controllerClass}";
        }

        $this->loadProviderBindings();
        $controller = app($controllerClass);

        if (!method_exists($controller, $action)) {
            return "方法不存在: {$action}";
        }

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

    /**
     * 结束应用
     */
    public function end($response)
    {
        if (function_exists('logs')) {
            logs("请求结束: " . ($_SERVER['REQUEST_URI'] ?? '/'), 'info');
        }
    }
}