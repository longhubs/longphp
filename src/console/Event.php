<?php
// long/console/Event.php
// LongPHP Framework - 调度事件（Laravel 风格）

namespace Long\Console;

class Event
{
    protected $expression;
    protected $callback;
    protected $enabled = true;
    protected $name = '';

    public function __construct($expression, $callback)
    {
        $this->expression = $expression;
        $this->callback = $callback;
    }

    public function name($name)
    {
        $this->name = $name;
        return $this;
    }

    public function disable()
    {
        $this->enabled = false;
        return $this;
    }

    public function enable()
    {
        $this->enabled = true;
        return $this;
    }

    public function getExpression()
    {
        return $this->expression;
    }

    public function isEnabled()
    {
        return $this->enabled;
    }

    public function getName()
    {
        return $this->name;
    }

    public function run()
    {
        if (!$this->enabled) return null;
        return call_user_func($this->callback);
    }
}