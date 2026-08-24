<?php
// long/console/Task.php
// LongPHP Framework - 定时任务基类

namespace Long\Console;

abstract class Task
{
    protected $name = '';
    protected $enabled = true;

    abstract public function handle();

    public function getName()
    {
        return $this->name ?: (new \ReflectionClass($this))->getShortName();
    }

    public function isEnabled()
    {
        return $this->enabled;
    }

    protected function before() {}
    protected function after() {}
    protected function failed(\Exception $e) {}

    public function execute()
    {
        $this->before();
        try {
            $result = $this->handle();
            $this->after();
            return $result;
        } catch (\Exception $e) {
            $this->failed($e);
            throw $e;
        }
    }

    protected function log($message, $level = 'info')
    {
        if (function_exists('trace')) {
            trace("[Task: {$this->getName()}] " . $message, $level);
        }
    }
}