<?php
// src/Model.php
// LongPHP Framework - 模型基类
// 龙行天下 🐉

namespace Long;

class Model
{
    /**
     * 数据表名
     * @var string
     */
    protected $table = '';

    /**
     * 主键名
     * @var string
     */
    protected $pk = 'id';

    /**
     * 模型数据
     * @var array
     */
    protected $data = [];

    /**
     * Db 实例
     * @var Db
     */
    protected $db = null;

    /**
     * 是否启用缓存
     * @var bool
     */
    protected $cacheEnabled = false;

    /**
     * 缓存键
     * @var string
     */
    protected $cacheKey = '';

    /**
     * 缓存过期时间（秒）
     * @var int
     */
    protected $cacheTtl = 60;

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->db = new Db();
        
        // 获取前缀
        $config = require ROOT_PATH . '/config/database.php';
        $prefix = $config['connections'][$config['default']]['prefix'] ?? '';
        
        if (empty($this->table)) {
            $className = (new \ReflectionClass($this))->getShortName();
            $this->table = strtolower(preg_replace('/(?<=[a-z])([A-Z])/', '_$1', $className));
            $this->table = str_replace('_model', '', $this->table);
        }
        
        // ✅ 自动添加前缀（如果表名没有前缀）
        if (!empty($prefix) && strpos($this->table, $prefix) !== 0) {
            $this->table = $prefix . $this->table;
        }
    }

    // ─────────────────────────────────────────────────────────────
    // 核心：所有方法自动转发到 Db
    // ─────────────────────────────────────────────────────────────

    /**
     * 静态调用转发（如 UserModel::where('id', 1)）
     */
    public static function __callStatic($method, $args)
    {
        $model = new static();
        $result = $model->db->table($model->table)->$method(...$args);

        // 链式调用返回 Model 对象
        if ($result instanceof Db) {
            return $model;
        }

        // find 返回数组 → 转成 Model 对象
        if ($method === 'find') {
            if ($result) {
                $model->data = $result;
                return $model;
            }
            return null;
        }

        // select 返回数组 → 转成 Model 对象数组
        if ($method === 'select') {
            $list = [];
            foreach ($result as $data) {
                $m = new static();
                $m->data = $data;
                $list[] = $m;
            }
            return $list;
        }

        // 聚合方法直接返回
        if (in_array($method, ['count', 'sum', 'avg', 'max', 'min'])) {
            return $result;
        }

        // paginate 转换
        if ($method === 'paginate') {
            $list = [];
            foreach ($result['list'] as $data) {
                $m = new static();
                $m->data = $data;
                $list[] = $m;
            }
            $result['list'] = $list;
            return $result;
        }

        return $result;
    }

    /**
     * 实例调用转发（如 $model->where('id', 1)）
     */
    public function __call($method, $args)
    {
        $result = $this->db->table($this->table)->$method(...$args);

        if ($result instanceof Db) {
            return $this;
        }

        if ($method === 'find') {
            if ($result) {
                $this->data = $result;
                return $this;
            }
            return null;
        }

        if ($method === 'select') {
            $list = [];
            foreach ($result as $data) {
                $m = new static();
                $m->data = $data;
                $list[] = $m;
            }
            return $list;
        }

        if (in_array($method, ['count', 'sum', 'avg', 'max', 'min'])) {
            return $result;
        }

        if ($method === 'paginate') {
            $list = [];
            foreach ($result['list'] as $data) {
                $m = new static();
                $m->data = $data;
                $list[] = $m;
            }
            $result['list'] = $list;
            return $result;
        }

        return $result;
    }

    // ─────────────────────────────────────────────────────────────
    // 缓存
    // ─────────────────────────────────────────────────────────────

    /**
     * 启用查询缓存
     */
    public function cache($ttl = 60, $key = null)
    {
        $this->cacheEnabled = true;
        $this->cacheTtl = $ttl;
        $this->cacheKey = $key ?: 'model_' . md5($this->table . '_' . get_class($this));
        return $this;
    }

    // ─────────────────────────────────────────────────────────────
    // 数据操作
    // ─────────────────────────────────────────────────────────────

    public function getData($key = null)
    {
        return $key === null ? $this->data : ($this->data[$key] ?? null);
    }

    public function setData($key, $value = null)
    {
        if (is_array($key)) {
            $this->data = array_merge($this->data, $key);
        } else {
            $this->data[$key] = $value;
        }
        return $this;
    }

    public function toArray()
    {
        return $this->data;
    }

    // ─────────────────────────────────────────────────────────────
    // 魔术方法
    // ─────────────────────────────────────────────────────────────

    public function __get($name)
    {
        return $this->data[$name] ?? null;
    }

    public function __set($name, $value)
    {
        $this->data[$name] = $value;
    }

    public function __isset($name)
    {
        return isset($this->data[$name]);
    }

    public function __unset($name)
    {
        unset($this->data[$name]);
    }
}