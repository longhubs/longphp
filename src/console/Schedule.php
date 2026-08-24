<?php
// long/console/Schedule.php
// LongPHP Framework - 定时任务调度器（Laravel 风格）

namespace Long\Console;

class Schedule
{
    protected $events = [];

    public function call($expression, $callback)
    {
        $event = new Event($expression, $callback);
        $this->events[] = $event;
        return $event;
    }

    public function task($expression, $taskClass)
    {
        return $this->call($expression, function() use ($taskClass) {
            if (!class_exists($taskClass)) {
                throw new \Exception("任务类不存在: {$taskClass}");
            }
            $task = new $taskClass();
            return $task->handle();
        });
    }

    public function getEvents()
    {
        return $this->events;
    }

    public function run()
    {
        $now = time();
        foreach ($this->events as $event) {
            if (!$event->isEnabled()) continue;
            if ($this->isDue($event, $now)) {
                $event->run();
            }
        }
    }

    protected function isDue($event, $now)
    {
        $expression = $event->getExpression();
        return $this->matchCron($expression, $now);
    }

    protected function matchCron($expression, $now)
    {
        if ($expression === '* * * * *') return true;

        $parts = explode(' ', $expression);
        if (count($parts) !== 5) return false;

        list($minute, $hour, $day, $month, $weekday) = $parts;

        return $this->matchPart($minute, (int)date('i', $now))
            && $this->matchPart($hour, (int)date('H', $now))
            && $this->matchPart($day, (int)date('d', $now))
            && $this->matchPart($month, (int)date('m', $now))
            && $this->matchPart($weekday, (int)date('w', $now));
    }

    protected function matchPart($pattern, $value)
    {
        if ($pattern === '*') return true;

        if (strpos($pattern, '*/') === 0) {
            $step = (int)substr($pattern, 2);
            return $value % $step === 0;
        }

        if (strpos($pattern, '-') !== false) {
            list($start, $end) = explode('-', $pattern);
            return $value >= (int)$start && $value <= (int)$end;
        }

        if (strpos($pattern, ',') !== false) {
            $values = explode(',', $pattern);
            return in_array($value, array_map('intval', $values));
        }

        return (int)$pattern === $value;
    }
}