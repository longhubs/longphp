<?php
// src/Collection.php
// LongPHP Framework - 集合类
// 龙行天下 🐉

namespace Long;

class Collection
{
    /**
     * 集合数据
     * @var array
     */
    protected $items = [];

    /**
     * 构造函数
     * @param array $items
     */
    public function __construct($items = [])
    {
        $this->items = is_array($items) ? $items : [];
    }

    /**
     * 获取所有数据
     * @return array
     */
    public function all()
    {
        return $this->items;
    }

    /**
     * 转为数组（支持模型对象递归转换）
     * @return array
     */
    public function toArray()
    {
        $result = [];
        foreach ($this->items as $item) {
            if (is_object($item) && method_exists($item, 'toArray')) {
                $result[] = $item->toArray();
            } elseif (is_array($item)) {
                $result[] = $this->arrayToArray($item);
            } else {
                $result[] = $item;
            }
        }
        return $result;
    }

    /**
     * 递归转换数组中的对象
     * @param array $array
     * @return array
     */
    protected function arrayToArray($array)
    {
        $result = [];
        foreach ($array as $key => $value) {
            if (is_object($value) && method_exists($value, 'toArray')) {
                $result[$key] = $value->toArray();
            } elseif (is_array($value)) {
                $result[$key] = $this->arrayToArray($value);
            } else {
                $result[$key] = $value;
            }
        }
        return $result;
    }
    // ─── 过滤 ───
    /**
     * 按条件过滤（等于）
     * @param string $field 字段名
     * @param mixed $value 值
     * @return self
     */
    public function where($field, $value)
    {
        $result = [];
        foreach ($this->items as $item) {
            $val = data_get($item, $field);
            if ($val == $value) {
                $result[] = $item;
            }
        }
        return new self($result);
    }

    /**
     * 按条件过滤（大于）
     */
    public function whereGt($field, $value)
    {
        $result = [];
        foreach ($this->items as $item) {
            $val = data_get($item, $field);
            if ($val > $value) {
                $result[] = $item;
            }
        }
        return new self($result);
    }

    /**
     * 按条件过滤（小于）
     */
    public function whereLt($field, $value)
    {
        $result = [];
        foreach ($this->items as $item) {
            $val = data_get($item, $field);
            if ($val < $value) {
                $result[] = $item;
            }
        }
        return new self($result);
    }

    /**
     * 按条件过滤（IN）
     */
    public function whereIn($field, $values)
    {
        $result = [];
        foreach ($this->items as $item) {
            $val = data_get($item, $field);
            if (in_array($val, $values)) {
                $result[] = $item;
            }
        }
        return new self($result);
    }

    // ─── 提取 ───

    /**
     * 获取指定字段的值列表
     */
    public function pluck($field)
    {
        $result = [];
        foreach ($this->items as $item) {
            $result[] = data_get($item, $field);
        }
        return new self($result);
    }

    /**
     * 获取指定字段的键值对
     */
    public function pluckMap($keyField, $valueField)
    {
        $result = [];
        foreach ($this->items as $item) {
            $key = data_get($item, $keyField);
            $value = data_get($item, $valueField);
            $result[$key] = $value;
        }
        return new self($result);
    }

    /**
     * 按字段分组
     */
    public function groupBy($field)
    {
        $result = [];
        foreach ($this->items as $item) {
            $key = data_get($item, $field);
            if (!isset($result[$key])) {
                $result[$key] = [];
            }
            $result[$key][] = $item;
        }
        return new self($result);
    }

    // ─── 排序 ───

    /**
     * 按字段排序
     */
    public function sortBy($field, $direction = 'ASC')
    {
        $result = $this->items;
        $direction = strtoupper($direction);
        usort($result, function($a, $b) use ($field, $direction) {
            $va = data_get($a, $field);
            $vb = data_get($b, $field);
            if ($va == $vb) return 0;
            if ($direction === 'DESC') {
                return $va > $vb ? -1 : 1;
            }
            return $va > $vb ? 1 : -1;
        });
        return new self($result);
    }

    // ─── 截取 ───

    public function take($limit)
    {
        return new self(array_slice($this->items, 0, $limit));
    }

    public function skip($skip)
    {
        return new self(array_slice($this->items, $skip));
    }

    // ─── 获取单条 ───

    public function first()
    {
        return $this->items[0] ?? null;
    }

    public function last()
    {
        return end($this->items) ?: null;
    }

    // ─── 统计 ───

    public function count()
    {
        return count($this->items);
    }

    public function isEmpty()
    {
        return empty($this->items);
    }

    public function isNotEmpty()
    {
        return !empty($this->items);
    }

    // ─── 遍历 ───

    public function map($callback)
    {
        $result = [];
        foreach ($this->items as $key => $item) {
            $result[] = $callback($item, $key);
        }
        return new self($result);
    }

    // ─── 聚合 ───

    public function sum($field)
    {
        $total = 0;
        foreach ($this->items as $item) {
            $total += data_get($item, $field);
        }
        return $total;
    }

    public function avg($field)
    {
        $count = $this->count();
        if ($count === 0) return 0;
        return $this->sum($field) / $count;
    }

    public function max($field)
    {
        $max = null;
        foreach ($this->items as $item) {
            $val = data_get($item, $field);
            if ($max === null || $val > $max) {
                $max = $val;
            }
        }
        return $max;
    }

    public function min($field)
    {
        $min = null;
        foreach ($this->items as $item) {
            $val = data_get($item, $field);
            if ($min === null || $val < $min) {
                $min = $val;
            }
        }
        return $min;
    }

    // ─── 输出 ───

    public function toJson()
    {
        return json_encode($this->toArray(), JSON_UNESCAPED_UNICODE);
    }

    public function __toString()
    {
        return $this->toJson();
    }
}