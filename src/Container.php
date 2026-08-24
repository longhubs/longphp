<?php
// src/Container.php
// LongPHP Framework - 服务容器（IoC）
// 龙行天下 🐉

namespace Long;

use Closure;
use ReflectionClass;
use ReflectionParameter;

class Container
{
    /**
     * 单例实例
     * @var self
     */
    protected static $instance;

    /**
     * 绑定关系（接口 → 实现）
     * @var array
     */
    protected $bindings = [];

    /**
     * 单例缓存
     * @var array
     */
    protected $instances = [];

    /**
     * 别名映射
     * @var array
     */
    protected $aliases = [];

    /**
     * 构造函数（私有，防止外部 new）
     */
    protected function __construct()
    {
    }

    /**
     * 获取容器单例
     * @return self
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // ─────────────────────────────────────────────────────────────
    // 绑定
    // ─────────────────────────────────────────────────────────────

    /**
     * 绑定一个类到容器
     * @param string $abstract 抽象类/接口名
     * @param string|Closure|object $concrete 具体实现
     * @param bool $singleton 是否单例
     * @return $this
     */
    public function bind($abstract, $concrete = null, $singleton = false)
    {
        if ($concrete === null) {
            $concrete = $abstract;
        }

        $this->bindings[$abstract] = [
            'concrete' => $concrete,
            'singleton' => $singleton,
        ];

        return $this;
    }

    /**
     * 绑定一个单例
     * @param string $abstract
     * @param string|Closure|object $concrete
     * @return $this
     */
    public function singleton($abstract, $concrete = null)
    {
        return $this->bind($abstract, $concrete, true);
    }

    /**
     * 绑定一个实例（直接注册已存在的对象）
     * @param string $abstract
     * @param object $instance
     * @return $this
     */
    public function instance($abstract, $instance)
    {
        $this->instances[$abstract] = $instance;
        return $this;
    }

    /**
     * 绑定别名
     * @param string $alias 别名
     * @param string $abstract 目标类
     * @return $this
     */
    public function alias($alias, $abstract)
    {
        $this->aliases[$alias] = $abstract;
        return $this;
    }

    // ─────────────────────────────────────────────────────────────
    // 解析
    // ─────────────────────────────────────────────────────────────

    /**
     * 从容器中解析类
     * @param string $abstract 类名/接口名
     * @param array $parameters 参数
     * @return object
     * @throws \Exception
     */
    public function make($abstract, $parameters = [])
    {
        // 处理别名
        $abstract = $this->getAlias($abstract);

        // 检查是否已有实例（单例）
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        // 检查是否有绑定
        if (isset($this->bindings[$abstract])) {
            $binding = $this->bindings[$abstract];
            $concrete = $binding['concrete'];

            // 如果是闭包，直接执行
            if ($concrete instanceof Closure) {
                $object = $concrete($this, $parameters);
            } else {
                $object = $this->build($concrete, $parameters);
            }

            // 如果是单例，缓存
            if ($binding['singleton']) {
                $this->instances[$abstract] = $object;
            }

            return $object;
        }

        // 没有绑定，尝试自动构建
        return $this->build($abstract, $parameters);
    }

    /**
     * 自动构建类（依赖注入）
     * @param string $class 类名
     * @param array $parameters 参数
     * @return object
     * @throws \Exception
     */
    public function build($class, $parameters = [])
    {
        // 如果是闭包，直接执行
        if ($class instanceof Closure) {
            return $class($this, $parameters);
        }

        // 检查类是否存在
        if (!class_exists($class)) {
            throw new \Exception("类不存在: {$class}");
        }

        // 使用反射获取构造函数
        $reflection = new ReflectionClass($class);

        // 如果类是抽象类或接口，抛出异常
        if ($reflection->isAbstract() || $reflection->isInterface()) {
            throw new \Exception("无法实例化抽象类或接口: {$class}");
        }

        // 获取构造函数参数
        $constructor = $reflection->getConstructor();

        // 没有构造函数，直接实例化
        if ($constructor === null) {
            return $reflection->newInstance();
        }

        // 解析构造函数参数
        $args = [];
        foreach ($constructor->getParameters() as $param) {
            $arg = $this->resolveParameter($param, $parameters);
            $args[] = $arg;
        }

        return $reflection->newInstanceArgs($args);
    }

    /**
     * 解析单个参数
     * @param ReflectionParameter $param
     * @param array $parameters
     * @return mixed
     * @throws \Exception
     */
    protected function resolveParameter(ReflectionParameter $param, $parameters = [])
    {
        $type = $param->getType();

        // 没有类型声明，尝试从参数数组中获取
        if ($type === null) {
            $name = $param->getName();
            if (isset($parameters[$name])) {
                return $parameters[$name];
            }
            if ($param->isDefaultValueAvailable()) {
                return $param->getDefaultValue();
            }
            throw new \Exception("无法解析参数: \${$name}");
        }

        // 内置类型（int, string, array, etc.）
        if ($type->isBuiltin()) {
            $name = $param->getName();
            if (isset($parameters[$name])) {
                return $parameters[$name];
            }
            if ($param->isDefaultValueAvailable()) {
                return $param->getDefaultValue();
            }
            throw new \Exception("无法解析内置类型参数: \${$name}");
        }

        // 类类型（依赖注入）
        $className = $type->getName();
        return $this->make($className);
    }

    /**
     * 获取别名对应的类名
     * @param string $abstract
     * @return string
     */
    protected function getAlias($abstract)
    {
        return $this->aliases[$abstract] ?? $abstract;
    }

    // ─────────────────────────────────────────────────────────────
    // 常用快捷方法
    // ─────────────────────────────────────────────────────────────

    /**
     * 判断是否已绑定
     * @param string $abstract
     * @return bool
     */
    public function has($abstract)
    {
        return isset($this->bindings[$abstract]) || isset($this->instances[$abstract]);
    }

    /**
     * 获取已绑定的类（调试用）
     * @return array
     */
    public function getBindings()
    {
        return array_keys($this->bindings);
    }

    /**
     * 清除所有绑定和实例
     */
    public function clear()
    {
        $this->bindings = [];
        $this->instances = [];
        $this->aliases = [];
    }
}