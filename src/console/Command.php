<?php
namespace Long\Console;

use Long\App;

abstract class Command
{
    protected $argv;
    protected $config;
    protected $app;

    public function __construct($argv = [], App $app = null)
    {
        $this->argv = $argv;
        $this->app = $app ?: App::getInstance();
        $this->config = $this->app->getConfig();
    }

    abstract public function run();

    protected function getArg($index, $default = null)
    {
        return $this->argv[$index] ?? $default;
    }

    protected function hasArg($name)
    {
        return in_array($name, $this->argv);
    }

    protected function log($msg, $level = 'info')
    {
        $time = date('Y-m-d H:i:s');
        echo "[{$time}] [{$level}] {$msg}\n";
    }

    protected function success($msg)
    {
        $this->log("✅ {$msg}", 'info');
    }

    protected function error($msg)
    {
        $this->log("❌ {$msg}", 'error');
    }

    protected function warning($msg)
    {
        $this->log("⚠️ {$msg}", 'warning');
    }
}