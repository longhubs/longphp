<?php
// src/EventManager.php
// LongPHP Framework - 事件管理器
// 龙行天下 🐉

namespace Long;

class EventManager
{
    /**
     * 事件监听器列表
     * @var array
     */
    protected $listeners = [];

    /**
     * 单例实例
     * @var self
     */
    protected static $instance;

    /**
     * 获取单例
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 注册事件监听
     * @param string $event 事件名称
     * @param callable|string $listener 监听器（闭包或类方法）
     * @param int $priority 优先级（数字越大越先执行）
     */
    public function listen($event, $listener, $priority = 0)
    {
        if (!isset($this->listeners[$event])) {
            $this->listeners[$event] = [];
        }
        $this->listeners[$event][$priority][] = $listener;
    }

    /**
     * 注册多个监听器
     * @param array $events ['event' => [listener1, listener2]]
     */
    public function listenMultiple($events)
    {
        foreach ($events as $event => $listeners) {
            foreach ((array)$listeners as $listener) {
                $this->listen($event, $listener);
            }
        }
    }

    /**
     * 触发事件
     * @param string $event 事件名称
     * @param array $payload 事件数据
     * @return array 监听器返回的结果
     */
    public function dispatch($event, $payload = [])
    {
        $results = [];

        if (!isset($this->listeners[$event])) {
            return $results;
        }

        // 按优先级排序
        krsort($this->listeners[$event]);

        foreach ($this->listeners[$event] as $listeners) {
            foreach ($listeners as $listener) {
                $result = $this->callListener($listener, $payload);
                if ($result !== null) {
                    $results[] = $result;
                }
            }
        }

        return $results;
    }

    /**
     * 执行监听器
     * @param callable|string $listener
     * @param array $payload
     * @return mixed
     */
    protected function callListener($listener, $payload)
    {
        // 闭包
        if (is_callable($listener)) {
            return $listener($payload);
        }

        // 类方法：'App\Listener\UserListener@handle'
        if (is_string($listener) && strpos($listener, '@') !== false) {
            list($class, $method) = explode('@', $listener);
            if (class_exists($class)) {
                $object = new $class();
                return $object->$method($payload);
            }
        }

        // 类：'App\Listener\UserListener'（默认调用 handle）
        if (is_string($listener) && class_exists($listener)) {
            $object = new $listener();
            if (method_exists($object, 'handle')) {
                return $object->handle($payload);
            }
        }

        return null;
    }

    /**
     * 获取所有监听器（调试用）
     */
    public function getListeners()
    {
        return $this->listeners;
    }

    /**
     * 移除事件的所有监听器
     */
    public function forget($event)
    {
        unset($this->listeners[$event]);
    }

    /**
     * 清空所有监听器
     */
    public function clear()
    {
        $this->listeners = [];
    }
}