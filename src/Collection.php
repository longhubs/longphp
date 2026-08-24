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
     * @param string $field 字段名
     * @param mixed $value 值
     * @return self
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
     * @param string $field 字段名
     * @param array $values 值数组
     * @return self
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

    /**
     * 获取指定字段的值列表
     * @param string $field 字段名
     * @return self
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
     * @param string $keyField 键字段
     * @param string $valueField 值字段
     * @return self
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
     * @param string $field 分组字段
     * @return self
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

    /**
     * 按字段排序
     * @param string $field 排序字段
     * @param string $direction ASC / DESC
     * @return self
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

    /**
     * 取前 N 条
     * @param int $limit 数量
     * @return self
     */
    public function take($limit)
    {
        return new self(array_slice($this->items, 0, $limit));
    }

    /**
     * 跳过 N 条
     * @param int $skip 数量
     * @return self
     */
    public function skip($skip)
    {
        return new self(array_slice($this->items, $skip));
    }

    /**
     * 取第一条
     * @return mixed
     */
    public function first()
    {
        return $this->items[0] ?? null;
    }

    /**
     * 取最后一条
     * @return mixed
     */
    public function last()
    {
        return end($this->items) ?: null;
    }

    /**
     * 获取数量
     * @return int
     */
    public function count()
    {
        return count($this->items);
    }

    /**
     * 是否为空
     * @return bool
     */
    public function isEmpty()
    {
        return empty($this->items);
    }

    /**
     * 是否不为空
     * @return bool
     */
    public function isNotEmpty()
    {
        return !empty($this->items);
    }

    /**
     * 转为数组
     * @return array
     */
    public function toArray()
    {
        return $this->items;
    }

    /**
     * 转为 JSON
     * @return string
     */
    public function toJson()
    {
        return json_encode($this->items, JSON_UNESCAPED_UNICODE);
    }

    /**
     * 遍历并返回新集合
     * @param callable $callback
     * @return self
     */
    public function map($callback)
    {
        $result = [];
        foreach ($this->items as $key => $item) {
            $result[] = $callback($item, $key);
        }
        return new self($result);
    }

    /**
     * 聚合：求和
     * @param string $field 字段名
     * @return float
     */
    public function sum($field)
    {
        $total = 0;
        foreach ($this->items as $item) {
            $total += data_get($item, $field);
        }
        return $total;
    }

    /**
     * 聚合：平均值
     */
    public function avg($field)
    {
        $count = $this->count();
        if ($count === 0) {
            return 0;
        }
        return $this->sum($field) / $count;
    }

    /**
     * 聚合：最大值
     */
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

    /**
     * 聚合：最小值
     */
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

    /**
     * 转字符串
     */
    public function __toString()
    {
        return $this->toJson();
    }
}