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
     * 获取单例实例
     * 
     * @return self
     */
    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 注册事件监听器
     * 
     * @param string $event 事件名称
     * @param callable $callback 回调函数
     * @param int $priority 优先级（数字越大越先执行）
     * @return self
     */
    public function listen($event, $callback, $priority = 0)
    {
        if (!isset($this->listeners[$event])) {
            $this->listeners[$event] = [];
        }
        
        // 按优先级存储
        $this->listeners[$event][$priority][] = $callback;
        
        return $this;
    }

    /**
     * 移除事件监听器
     * 
     * @param string $event 事件名称
     * @param callable|null $callback 要移除的回调，为 null 则移除所有
     * @return self
     */
    public function remove($event, $callback = null)
    {
        if (!isset($this->listeners[$event])) {
            return $this;
        }

        if ($callback === null) {
            unset($this->listeners[$event]);
            return $this;
        }

        foreach ($this->listeners[$event] as $priority => $callbacks) {
            foreach ($callbacks as $key => $registeredCallback) {
                if ($registeredCallback === $callback) {
                    unset($this->listeners[$event][$priority][$key]);
                }
            }
            // 清理空数组
            if (empty($this->listeners[$event][$priority])) {
                unset($this->listeners[$event][$priority]);
            }
        }

        return $this;
    }

    /**
     * 触发事件
     * 
     * @param string|Event $event 事件名称或事件对象
     * @param mixed $data 事件数据
     * @return Event
     */
    public function dispatch($event, $data = null)
    {
        // 如果传入的是字符串，创建 Event 对象
        if (is_string($event)) {
            $event = new Event($event, $data);
        }

        $eventName = $event->getName();

        if (!isset($this->listeners[$eventName])) {
            return $event;
        }

        // 按优先级排序（从高到低）
        $priorities = array_keys($this->listeners[$eventName]);
        rsort($priorities);

        foreach ($priorities as $priority) {
            foreach ($this->listeners[$eventName][$priority] as $callback) {
                if ($event->isPropagationStopped()) {
                    break 2;
                }

                if (is_callable($callback)) {
                    call_user_func($callback, $event);
                } elseif (is_string($callback) && class_exists($callback)) {
                    // 支持类名作为监听器
                    $listener = new $callback();
                    if (method_exists($listener, 'handle')) {
                        $listener->handle($event);
                    }
                }
            }
        }

        return $event;
    }

    /**
     * 检查事件是否有监听器
     * 
     * @param string $event 事件名称
     * @return bool
     */
    public function hasListeners($event)
    {
        return isset($this->listeners[$event]) && !empty($this->listeners[$event]);
    }

    /**
     * 获取所有监听器
     * 
     * @param string|null $event 事件名称，为 null 则返回所有
     * @return array
     */
    public function getListeners($event = null)
    {
        if ($event === null) {
            return $this->listeners;
        }
        return $this->listeners[$event] ?? [];
    }

    /**
     * 清除所有监听器
     * 
     * @return self
     */
    public function clear()
    {
        $this->listeners = [];
        return $this;
    }
}