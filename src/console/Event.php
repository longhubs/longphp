<?php
// src/Console/Event.php
// LongPHP Framework - 定时任务事件
// 龙行天下 🐉

namespace Long\Console;

class Event
{
    /**
     * 任务名称
     * @var string
     */
    protected $name;

    /**
     * Cron 表达式
     * @var string
     */
    protected $expression;

    /**
     * 回调函数
     * @var callable
     */
    protected $callback;

    /**
     * 是否启用
     * @var bool
     */
    protected $enabled = true;

    /**
     * 构造函数
     * 
     * @param string $expression Cron 表达式
     * @param callable $callback 回调函数
     * @param string $name 任务名称
     */
    public function __construct($expression, $callback, $name = '')
    {
        $this->expression = $expression;
        $this->callback = $callback;
        $this->name = $name;
    }

    /**
     * 设置任务名称
     * 
     * @param string $name
     * @return self
     */
    public function name($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * 获取任务名称
     * 
     * @return string
     */
    public function getName()
    {
        return $this->name ?: '未命名';
    }

    /**
     * 获取 Cron 表达式
     * 
     * @return string
     */
    public function getExpression()
    {
        return $this->expression;
    }

    /**
     * 检查是否启用
     * 
     * @return bool
     */
    public function isEnabled()
    {
        return $this->enabled;
    }

    /**
     * 设置启用状态
     * 
     * @param bool $enabled
     * @return self
     */
    public function setEnabled($enabled)
    {
        $this->enabled = $enabled;
        return $this;
    }

    /**
     * 判断当前时间是否满足 Cron 表达式
     * 
     * @param int|null $timestamp
     * @return bool
     */
    public function isDue($timestamp = null)
    {
        if (!$this->enabled) {
            return false;
        }

        $timestamp = $timestamp ?: time();
        return $this->cronMatch($this->expression, $timestamp);
    }

    /**
     * 执行任务
     * 
     * @return mixed
     */
    public function run()
    {
        if (is_callable($this->callback)) {
            return call_user_func($this->callback);
        }
        return null;
    }

    /**
     * 简单的 Cron 表达式匹配
     * 
     * @param string $expression
     * @param int $timestamp
     * @return bool
     */
    protected function cronMatch($expression, $timestamp)
    {
        $parts = explode(' ', $expression);
        if (count($parts) !== 5) {
            return false;
        }

        list($minute, $hour, $day, $month, $weekday) = $parts;

        $date = getdate($timestamp);

        return $this->matchPart($minute, $date['minutes'])
            && $this->matchPart($hour, $date['hours'])
            && $this->matchPart($day, $date['mday'])
            && $this->matchPart($month, $date['mon'])
            && $this->matchPart($weekday, $date['wday'] + 1);
    }

    /**
     * 匹配单个 Cron 部分
     * 
     * @param string $pattern
     * @param int $value
     * @return bool
     */
    protected function matchPart($pattern, $value)
    {
        if ($pattern === '*') {
            return true;
        }

        // 支持 */5 格式
        if (strpos($pattern, '*/') === 0) {
            $step = (int)substr($pattern, 2);
            return $value % $step === 0;
        }

        // 支持 1,2,3 格式
        if (strpos($pattern, ',') !== false) {
            $parts = explode(',', $pattern);
            return in_array($value, $parts);
        }

        // 支持 1-5 格式
        if (strpos($pattern, '-') !== false) {
            list($start, $end) = explode('-', $pattern);
            return $value >= $start && $value <= $end;
        }

        return (int)$pattern === $value;
    }
}