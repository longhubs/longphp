<?php
// src/Db.php
// LongPHP Framework - 数据库查询构造器
// 龙行天下 🐉

namespace Long;

use PDO;
use PDOException;
use Long\Cache\CacheManager;
use Long\App;

class Db
{
    // ─────────────────────────────────────────────────────────────
    // 连接和配置
    // ─────────────────────────────────────────────────────────────

    /**
     * PDO 单例实例
     * @var PDO|null
     */
    private static $pdo = null;

    /**
     * SQL 执行日志（带占位符）
     * @var array
     */
    private static $logs = [];

    /**
     * SQL 执行日志（完整替换后）
     * @var array
     */
    private static $fullLogs = [];

    /**
     * 数据库配置
     * @var array
     */
    private $config = [];

    /**
     * 当前操作的表名
     * @var string
     */
    private $table = '';

    /**
     * 查询字段（SELECT 部分）
     * @var string
     */
    private $field = '*';

    /**
     * WHERE 条件列表
     * @var array
     */
    private $where = [];

    /**
     * WHERE IN / NOT IN 绑定的参数
     * @var array
     */
    private $whereParams = [];

    /**
     * ORDER BY 排序条件
     * @var array
     */
    private $order = [];

    /**
     * GROUP BY 分组条件
     * @var string
     */
    private $group = '';

    /**
     * HAVING 分组筛选条件
     * @var string
     */
    private $having = '';

    /**
     * LIMIT 分页限制
     * @var string
     */
    private $limit = '';

    /**
     * JOIN 关联查询
     * @var array
     */
    private $join = [];

    /**
     * DISTINCT 去重标志
     * @var bool
     */
    private $distinct = false;

    /**
     * 行锁（FOR UPDATE / LOCK IN SHARE MODE）
     * @var string
     */
    private $lock = '';

    // ─────────────────────────────────────────────────────────────
    // 查询缓存
    // ─────────────────────────────────────────────────────────────

    /**
     * 是否启用缓存
     * @var bool
     */
    private $cacheEnabled = false;

    /**
     * 缓存键
     * @var string
     */
    private $cacheKey = '';

    /**
     * 缓存过期时间（秒）
     * @var int
     */
    private $cacheTtl = 60;

    /**
     * 表前缀
     * @var string
     */
    private $prefix = '';
    
    /**
     * App 实例
     * @var App
     */
    private $app;  // ⬅️ 添加 App 实例

    // ─────────────────────────────────────────────────────────────
    // 构造函数和连接
    // ─────────────────────────────────────────────────────────────

    /**
     * 构造函数：加载配置并连接数据库
     * @param array $config 数据库配置（可选，默认读取 config/database.php）
     * @throws \Exception 连接失败时抛出异常
     */
    public function __construct($config = [])
    {
        // ⬅️ 获取 App 实例
        $this->app = App::getInstance();
        
        // ✅ 使用框架的 getConfigPath() 方法
        $configFile = $this->app->getConfigPath('database.php');
        $defaultConfig = file_exists($configFile) ? require $configFile : [];
        
        $this->config = $config ?: $defaultConfig;
        $this->prefix = $this->config['connections'][$this->config['default']]['prefix'] ?? '';
        $this->connect();
    }

    /**
     * 连接数据库（单例模式）
     * @throws \Exception 连接失败时抛出异常
     */
    private function connect()
    {
        if (self::$pdo === null) {
            $cfg = $this->config['connections'][$this->config['default']];
            $dsn = "{$cfg['type']}:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['database']};charset={$cfg['charset']}";

            try {
                self::$pdo = new PDO($dsn, $cfg['username'], $cfg['password']);
                self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                throw new \Exception('数据库连接失败：' . $e->getMessage());
            }
        }
    }

    /**
     * 切换数据库连接
     * @param string $name 连接名
     * @return self
     */
    public static function connection($name = null)
    {
        $instance = new self();
        if ($name) {
            $app = App::getInstance();
            $configFile = $app->getConfigPath('database.php');
            $config = file_exists($configFile) ? require $configFile : [];
            
            if (isset($config['connections'][$name])) {
                $instance->config['default'] = $name;
                self::$pdo = null;
                $instance->connect();
            }
        }
        return $instance;
    }
    
    /**
     * 获取当前表名
     * @return string
     */
    public function getTable()
    {
        return $this->table;
    }
    
    /**
     * 设置表名
     * @param string $table
     * @return $this
     */
    public function setTable($table)
    {
        $this->table = $table;
        return $this;
    }
    
    // ─────────────────────────────────────────────────────────────
    // 静态入口
    // ─────────────────────────────────────────────────────────────
    
    /**
     * 检查表名是否已包含前缀
     * @param string $table 表名
     * @return bool
     */
    private function hasPrefix($table)
    {
        if (empty($this->prefix)) {
            return false;
        }
        // 如果表名包含空格（带别名），只检查第一部分
        if (strpos($table, ' ') !== false) {
            $parts = explode(' ', $table);
            $table = $parts[0];
        }
        return strpos($table, $this->prefix) === 0;
    }
    
    /**
     * 自动添加表前缀（支持带别名的表名）
     * @param string $table 表名，可带别名如 'sys_tags t'
     * @return string
     */
    private function addPrefix($table)
    {
        if (empty($this->prefix)) {
            return $table;
        }
        
        // 如果已经包含前缀，直接返回
        if ($this->hasPrefix($table)) {
            return $table;
        }
        
        // 检查是否包含别名（空格分隔）
        if (strpos($table, ' ') !== false) {
            $parts = preg_split('/\s+/', $table, 2);
            $tableName = $parts[0];
            $alias = isset($parts[1]) ? ' ' . $parts[1] : '';
            return $this->prefix . $tableName . $alias;
        }
        
        return $this->prefix . $table;
    }
    
    /**
     * 指定数据表（静态入口）
     * @param string $table 表名
     * @return self 当前实例（支持链式调用）
     * @example Db::table('users')->where('id', 1)->find()
     */
    public static function table($table)
    {
        $instance = new self();
        $instance->table = $instance->addPrefix($table);
        return $instance;
    }

    // ─────────────────────────────────────────────────────────────
    // 表别名
    // ─────────────────────────────────────────────────────────────

    /**
     * 设置表别名（alias 方式，ThinkPHP 风格）
     * @param string $alias 别名
     * @return self
     */
    public function alias($alias)
    {
        $this->table = $this->table . ' AS ' . $alias;
        return $this;
    }

    /**
     * 设置表别名（as 方式，Laravel 风格）
     * @param string $alias 别名
     * @return self
     */
    public function as($alias)
    {
        $this->table = $this->table . ' AS ' . $alias;
        return $this;
    }

    // ─────────────────────────────────────────────────────────────
    // 查询构造器（链式方法）
    // ─────────────────────────────────────────────────────────────

    /**
     * 指定查询字段（不验证，完全支持别名）
     * @param string|array $field 字段列表，支持别名格式如 'u.id, t.title'
     * @return self 当前实例（支持链式调用）
     * @example ->field('id, name, email')
     * @example ->field('u.id, t.title, u.create_time')
     * @example ->field(['id', 'name', 'email'])
     * @example ->field(['u.id', 't.title', 'u.create_time'])
     */
    public function field($field)
    {
        if (is_array($field)) {
            $this->field = implode(', ', $field);
        } else {
            $this->field = $field;
        }
        return $this;
    }

    /**
     * DISTINCT 去重查询
     * @param bool $flag 是否启用去重，默认 true
     * @return self 当前实例（支持链式调用）
     * @example ->distinct()->field('name')
     */
    public function distinct($flag = true)
    {
        $this->distinct = $flag;
        return $this;
    }

    /**
     * WHERE 条件（支持多种调用方式）
     * @param string|array $field 字段名，或条件数组
     * @param string|null  $operator 操作符（=, >, <, >=, <=, <> 等）
     * @param mixed|null   $value 值
     * @return self 当前实例（支持链式调用）
     * @example
     * ->where('id', 1)
     * ->where('age', '>', 18)
     * ->where(['name' => '张三', 'status' => 1])
     * ->where(['id', '=', 1])
     * ->where([['id', '=', 1], ['status', '=', 1]])
     * ->where('id', 'in', [1,2,3])
     * ->where('id', 'in', '1,2,3')
     * ->where('id', 'not in', '1,2,3')
     * ->where('u.deleted', 0)
     */
    public function where($field, $operator = null, $value = null)
    {
        // 处理数组形式的条件
        if (is_array($field)) {
            foreach ($field as $key => $val) {
                // 格式1: [['field', 'operator', 'value']] 索引数组
                if (is_int($key) && is_array($val) && count($val) === 3) {
                    $this->where[] = [$val[0], $val[1], $val[2]];
                }
                // 格式2: ['field', 'value'] 简写（默认为 =）
                else if (is_int($key) && is_array($val) && count($val) === 2) {
                    $this->where[] = [$val[0], '=', $val[1]];
                }
                // 格式3: ['field' => 'value'] 关联数组
                else if (!is_int($key)) {
                    // 检查值是否包含逗号（自动转为 IN）
                    if (is_string($val) && strpos($val, ',') !== false) {
                        $this->whereComma($key, $val);
                    } else {
                        $this->where[] = [$key, '=', $val];
                    }
                }
                // 格式4: 嵌套数组，递归处理
                else if (is_array($val)) {
                    $this->where($val);
                }
            }
            return $this;
        }
        
        // 验证字段名
        if (is_string($field) && !preg_match('/^[a-zA-Z0-9_\.]+$/', $field)) {
            throw new \Exception('非法的字段名: ' . $field);
        }
        
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }
        
        $operator = strtoupper($operator);
        
        // 处理 IN / NOT IN
        if (in_array($operator, ['IN', 'NOT IN'])) {
            if (is_string($value)) {
                $value = array_map('trim', explode(',', $value));
                $value = array_filter($value, function($v) {
                    return $v !== '' && $v !== null;
                });
                $value = array_values($value);
            }
            if (is_array($value) && !empty($value)) {
                return $this->whereIn($field, $value, $operator === 'NOT IN');
            }
            return $this;
        }
        
        // 如果值包含逗号且操作符是 =，自动转为 IN
        if ($operator === '=' && is_string($value) && strpos($value, ',') !== false) {
            return $this->whereComma($field, $value);
        }
        
        // 如果值本身就是数组，但操作符不是 IN，则转为 IN
        if (is_array($value) && !in_array($operator, ['BETWEEN'])) {
            return $this->whereIn($field, $value);
        }
        
        $this->where[] = [$field, $operator, $value];
        return $this;
    }

    /**
     * WHERE IN 条件
     * @param string $field 字段名
     * @param array|string $values 值数组 或 逗号分隔的字符串（如 '1,2,3'）
     * @param bool $not 是否为 NOT IN
     * @return self 当前实例（支持链式调用）
     * @example ->whereIn('id', [1, 2, 3])
     * @example ->whereIn('id', '1,2,3')
     */
    public function whereIn($field, $values, $not = false)
    {
        // 如果是字符串，按逗号分隔转成数组
        if (is_string($values)) {
            $values = array_map('trim', explode(',', $values));
            $values = array_filter($values, function($v) {
                return $v !== '' && $v !== null;
            });
            $values = array_values($values);
        }
        
        if (!is_array($values) || empty($values)) {
            return $this;
        }

        $operator = $not ? 'NOT IN' : 'IN';
        $placeholders = [];
        
        foreach ($values as $i => $val) {
            $key = ($not ? ":nin_" : ":in_") . $field . "_" . $i;
            $placeholders[] = $key;
            $this->whereParams[$key] = $val;
        }

        $this->where[] = [$field, $operator, '(' . implode(', ', $placeholders) . ')'];
        return $this;
    }

    /**
     * WHERE NOT IN 条件
     * @param string $field 字段名
     * @param array|string $values 值数组 或 逗号分隔的字符串
     * @return self 当前实例（支持链式调用）
     * @example ->whereNotIn('id', [1, 2, 3])
     * @example ->whereNotIn('id', '1,2,3')
     */
    public function whereNotIn($field, $values)
    {
        return $this->whereIn($field, $values, true);
    }

    /**
     * WHERE LIKE 模糊查询
     * @param string $field 字段名
     * @param string $value 匹配值（可包含 % 通配符）
     * @return self 当前实例（支持链式调用）
     * @example ->whereLike('name', '%张三%')
     */
    public function whereLike($field, $value)
    {
        $this->where[] = [$field, 'LIKE', $value];
        return $this;
    }

    /**
     * WHERE BETWEEN 区间查询
     * @param string $field 字段名
     * @param mixed  $start 起始值
     * @param mixed  $end 结束值
     * @return self 当前实例（支持链式调用）
     * @example ->whereBetween('age', 18, 30)
     */
    public function whereBetween($field, $start, $end)
    {
        $this->where[] = [$field, 'BETWEEN', $start . ' AND ' . $end];
        return $this;
    }

    /**
     * WHERE IS NULL 条件
     * @param string $field 字段名
     * @return self 当前实例（支持链式调用）
     * @example ->whereNull('deleted_at')
     */
    public function whereNull($field)
    {
        $this->where[] = [$field, 'IS NULL', ''];
        return $this;
    }

    /**
     * WHERE IS NOT NULL 条件
     * @param string $field 字段名
     * @return self 当前实例（支持链式调用）
     * @example ->whereNotNull('name')
     */
    public function whereNotNull($field)
    {
        $this->where[] = [$field, 'IS NOT NULL', ''];
        return $this;
    }

    /**
     * OR WHERE 条件
     * @param string|array $field 字段名，或条件数组
     * @param string|null  $operator 操作符
     * @param mixed|null   $value 值
     * @return self 当前实例（支持链式调用）
     * @example ->orWhere('status', 0)
     * @example ->orWhere('age', '>', 18)
     * @example ->orWhere(['id', 'in', [1,2,3]])
     * @example ->orWhere('title', 'like', '%test%')
     */
    public function orWhere($field, $operator = null, $value = null)
    {
        // 处理数组形式的条件
        if (is_array($field)) {
            foreach ($field as $key => $val) {
                if (is_int($key) && is_array($val) && count($val) === 3) {
                    $this->where[] = ['OR', $val[0], $val[1], $val[2]];
                } else if (is_int($key) && is_array($val) && count($val) === 2) {
                    $this->where[] = ['OR', $val[0], '=', $val[1]];
                } else if (!is_int($key)) {
                    $this->where[] = ['OR', $key, '=', $val];
                } else if (is_array($val)) {
                    $this->orWhere($val);
                }
            }
            return $this;
        }
        
        // 验证字段名
        if (is_string($field) && !preg_match('/^[a-zA-Z0-9_\.]+$/', $field)) {
            throw new \Exception('非法的字段名: ' . $field);
        }
        
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }
        
        $operator = strtoupper($operator);
        
        // 处理 IN / NOT IN
        if (in_array($operator, ['IN', 'NOT IN'])) {
            if (is_string($value)) {
                $value = array_map('trim', explode(',', $value));
                $value = array_filter($value, function($v) {
                    return $v !== '' && $v !== null;
                });
                $value = array_values($value);
            }
            if (is_array($value) && !empty($value)) {
                return $this->orWhereIn($field, $value, $operator === 'NOT IN');
            }
            return $this;
        }
        
        if (is_array($value) && !in_array($operator, ['BETWEEN'])) {
            return $this->orWhereIn($field, $value);
        }
        
        $this->where[] = ['OR', $field, $operator, $value];
        return $this;
    }

    /**
     * OR WHERE IN 条件
     * @param string $field 字段名
     * @param array|string $values 值数组 或 逗号分隔的字符串
     * @param bool $not 是否为 NOT IN
     * @return self
     */
    public function orWhereIn($field, $values, $not = false)
    {
        if (is_string($values)) {
            $values = array_map('trim', explode(',', $values));
            $values = array_filter($values, function($v) {
                return $v !== '' && $v !== null;
            });
            $values = array_values($values);
        }
        
        if (!is_array($values) || empty($values)) {
            return $this;
        }

        $operator = $not ? 'NOT IN' : 'IN';
        $placeholders = [];
        
        foreach ($values as $i => $val) {
            $key = ($not ? ":or_nin_" : ":or_in_") . $field . "_" . $i;
            $placeholders[] = $key;
            $this->whereParams[$key] = $val;
        }

        $this->where[] = ['OR', $field, $operator, '(' . implode(', ', $placeholders) . ')'];
        return $this;
    }

    /**
     * OR WHERE NOT IN 条件
     * @param string $field 字段名
     * @param array|string $values 值数组 或 逗号分隔的字符串
     * @return self
     */
    public function orWhereNotIn($field, $values)
    {
        return $this->orWhereIn($field, $values, true);
    }

    /**
     * OR WHERE LIKE 模糊查询
     * @param string $field 字段名
     * @param string $value 匹配值
     * @return self
     */
    public function orWhereLike($field, $value)
    {
        $this->where[] = ['OR', $field, 'LIKE', $value];
        return $this;
    }

    /**
     * OR WHERE NULL 条件
     * @param string $field 字段名
     * @return self
     */
    public function orWhereNull($field)
    {
        $this->where[] = ['OR', $field, 'IS NULL', ''];
        return $this;
    }

    /**
     * OR WHERE NOT NULL 条件
     * @param string $field 字段名
     * @return self
     */
    public function orWhereNotNull($field)
    {
        $this->where[] = ['OR', $field, 'IS NOT NULL', ''];
        return $this;
    }

    /**
     * OR WHERE BETWEEN 区间查询
     * @param string $field 字段名
     * @param mixed $start 起始值
     * @param mixed $end 结束值
     * @return self
     */
    public function orWhereBetween($field, $start, $end)
    {
        $this->where[] = ['OR', $field, 'BETWEEN', $start . ' AND ' . $end];
        return $this;
    }

    // ─────────────────────────────────────────────────────────────
    // 逗号查询（字段值包含逗号，查询是否包含某个值）
    // ─────────────────────────────────────────────────────────────

    /**
     * 逗号查询（自动转为 IN 查询）
     * @param string $field 字段名
     * @param string|array $value 逗号分隔的字符串 或 数组
     * @param bool $not 是否为 NOT IN
     * @return self
     * @example ->whereComma('id', '1,2,3')
     * @example ->whereComma('status', '1,2,3')
     * @example ->whereComma('id', [1,2,3])
     */
    public function whereComma($field, $value, $not = false)
    {
        if (is_string($value)) {
            $value = array_map('trim', explode(',', $value));
            $value = array_filter($value, function($v) {
                return $v !== '' && $v !== null;
            });
            $value = array_values($value);
        }
        
        if (!is_array($value) || empty($value)) {
            return $this;
        }
        
        return $this->whereIn($field, $value, $not);
    }

    /**
     * 逗号查询 NOT IN
     * @param string $field 字段名
     * @param string|array $value 逗号分隔的字符串 或 数组
     * @return self
     * @example ->whereCommaNot('id', '1,2,3')
     */
    public function whereCommaNot($field, $value)
    {
        return $this->whereComma($field, $value, true);
    }

    /**
     * OR 逗号查询
     * @param string $field 字段名
     * @param string|array $value 逗号分隔的字符串 或 数组
     * @param bool $not 是否为 NOT IN
     * @return self
     * @example ->orWhereComma('id', '1,2,3')
     */
    public function orWhereComma($field, $value, $not = false)
    {
        if (is_string($value)) {
            $value = array_map('trim', explode(',', $value));
            $value = array_filter($value, function($v) {
                return $v !== '' && $v !== null;
            });
            $value = array_values($value);
        }
        
        if (!is_array($value) || empty($value)) {
            return $this;
        }
        
        return $this->orWhereIn($field, $value, $not);
    }

    /**
     * OR 逗号查询 NOT IN
     * @param string $field 字段名
     * @param string|array $value 逗号分隔的字符串 或 数组
     * @return self
     * @example ->orWhereCommaNot('id', '1,2,3')
     */
    public function orWhereCommaNot($field, $value)
    {
        return $this->orWhereComma($field, $value, true);
    }

    // ─────────────────────────────────────────────────────────────
    // FIND_IN_SET 查询（逗号分隔字段查询）
    // ─────────────────────────────────────────────────────────────

    /**
     * FIND_IN_SET 查询（查询逗号分隔字段中是否包含某个值）
     * @param string $field 字段名（存储逗号分隔值的字段）
     * @param string|int $value 要查找的值
     * @param bool $not 是否为 NOT FIND_IN_SET
     * @return self
     * @example ->whereFindInSet('title', '2')  // 查询 title 字段中包含 '2' 的记录
     * @example ->whereFindInSet('tags', 'php')  // 查询 tags 字段中包含 'php' 的记录
     */
    public function whereFindInSet($field, $value, $not = false)
    {
        $operator = $not ? 'NOT FIND_IN_SET' : 'FIND_IN_SET';
        $this->where[] = [$field, $operator, $value];
        return $this;
    }

    /**
     * NOT FIND_IN_SET 查询
     * @param string $field 字段名
     * @param string|int $value 要查找的值
     * @return self
     * @example ->whereNotFindInSet('title', '2')
     */
    public function whereNotFindInSet($field, $value)
    {
        return $this->whereFindInSet($field, $value, true);
    }

    /**
     * OR FIND_IN_SET 查询
     * @param string $field 字段名
     * @param string|int $value 要查找的值
     * @param bool $not 是否为 NOT FIND_IN_SET
     * @return self
     * @example ->orWhereFindInSet('title', '2')
     */
    public function orWhereFindInSet($field, $value, $not = false)
    {
        $operator = $not ? 'NOT FIND_IN_SET' : 'FIND_IN_SET';
        $this->where[] = ['OR', $field, $operator, $value];
        return $this;
    }

    /**
     * OR NOT FIND_IN_SET 查询
     * @param string $field 字段名
     * @param string|int $value 要查找的值
     * @return self
     * @example ->orWhereNotFindInSet('title', '2')
     */
    public function orWhereNotFindInSet($field, $value)
    {
        return $this->orWhereFindInSet($field, $value, true);
    }

    // ─────────────────────────────────────────────────────────────
    // 原生条件查询
    // ─────────────────────────────────────────────────────────────

    /**
     * 原生 WHERE 条件（支持原生 SQL 字符串）
     * @param string $sql 原生 SQL 条件字符串
     * @param array $params 绑定参数（可选）
     * @param bool $not 是否为 NOT
     * @return self
     * @example ->whereRaw('status = 1')
     * @example ->whereRaw('age > ?', [18])
     * @example ->whereRaw('FIND_IN_SET(?, tags)', ['php'])
     * @example ->whereRaw('EXISTS (SELECT 1 FROM orders WHERE orders.user_id = users.id)')
     * @example ->whereRaw('id IN (SELECT user_id FROM orders)')
     */
    public function whereRaw($sql, $params = [], $not = false)
    {
        if ($not) {
            $this->where[] = ['RAW', 'NOT', $sql, $params];
        } else {
            $this->where[] = ['RAW', '', $sql, $params];
        }
        return $this;
    }

    /**
     * 原生 WHERE NOT 条件
     * @param string $sql 原生 SQL 条件字符串
     * @param array $params 绑定参数（可选）
     * @return self
     * @example ->whereNotRaw('status = 1')
     * @example ->whereNotRaw('age > ?', [18])
     */
    public function whereNotRaw($sql, $params = [])
    {
        return $this->whereRaw($sql, $params, true);
    }

    /**
     * OR 原生 WHERE 条件
     * @param string $sql 原生 SQL 条件字符串
     * @param array $params 绑定参数（可选）
     * @param bool $not 是否为 NOT
     * @return self
     * @example ->orWhereRaw('status = 1')
     * @example ->orWhereRaw('age > ?', [18])
     */
    public function orWhereRaw($sql, $params = [], $not = false)
    {
        if ($not) {
            $this->where[] = ['OR', 'RAW', 'NOT', $sql, $params];
        } else {
            $this->where[] = ['OR', 'RAW', '', $sql, $params];
        }
        return $this;
    }

    /**
     * OR 原生 WHERE NOT 条件
     * @param string $sql 原生 SQL 条件字符串
     * @param array $params 绑定参数（可选）
     * @return self
     * @example ->orWhereNotRaw('status = 1')
     */
    public function orWhereNotRaw($sql, $params = [])
    {
        return $this->orWhereRaw($sql, $params, true);
    }

    /**
     * ORDER BY 排序
     * @param string|array $field 字段名或排序数组
     * @param string|null $direction 排序方向（ASC / DESC），为 null 时从 $field 解析
     * @return self 当前实例（支持链式调用）
     * @example ->order('id', 'DESC')
     * @example ->order('sort DESC, id DESC')
     * @example ->order(['sort' => 'DESC', 'id' => 'DESC'])
     * @example ->order(['sort DESC', 'id DESC'])
     * @example ->order('u.create_time DESC')
     */
    public function order($field, $direction = null)
    {
        // 如果 $field 是数组，批量处理
        if (is_array($field)) {
            foreach ($field as $f => $dir) {
                if (is_int($f)) {
                    // 索引数组：['id DESC', 'create_time ASC']
                    $this->parseOrderString($dir);
                } else {
                    // 关联数组：['id' => 'DESC', 'create_time' => 'ASC']
                    $this->parseOrderString($f . ' ' . $dir);
                }
            }
            return $this;
        }
        
        // 如果 $direction 为 null，尝试解析完整字符串
        if ($direction === null) {
            // 检查是否包含逗号（多字段排序）
            if (strpos($field, ',') !== false) {
                $fields = explode(',', $field);
                foreach ($fields as $f) {
                    $this->parseOrderString(trim($f));
                }
                return $this;
            }
            
            // 单个字段，尝试解析方向和字段
            $parts = preg_split('/\s+/', trim($field));
            if (count($parts) >= 2) {
                $field = $parts[0];
                $direction = strtoupper($parts[1]);
            } else {
                $direction = 'ASC';
            }
        }
        
        // 验证字段名
        if (!preg_match('/^[a-zA-Z0-9_\.]+$/', $field)) {
            throw new \Exception('非法的排序字段: ' . $field);
        }
        
        $direction = strtoupper($direction);
        if (!in_array($direction, ['ASC', 'DESC'])) {
            $direction = 'ASC';
        }
        $this->order[] = [$field, $direction];
        return $this;
    }

    /**
     * 解析排序字符串
     * @param string $string 排序字符串，如 'id DESC'
     */
    private function parseOrderString($string)
    {
        $parts = preg_split('/\s+/', trim($string));
        $field = $parts[0];
        $direction = isset($parts[1]) ? strtoupper($parts[1]) : 'ASC';
        
        if (!preg_match('/^[a-zA-Z0-9_\.]+$/', $field)) {
            throw new \Exception('非法的排序字段: ' . $field);
        }
        
        if (!in_array($direction, ['ASC', 'DESC'])) {
            $direction = 'ASC';
        }
        $this->order[] = [$field, $direction];
    }

    /**
     * GROUP BY 分组
     * @param string $field 字段名
     * @return self 当前实例（支持链式调用）
     * @example ->group('status')
     */
    public function group($field)
    {
        $this->group = "GROUP BY {$field}";
        return $this;
    }

    /**
     * HAVING 分组筛选条件
     * @param string $field 字段名
     * @param string|null $operator 操作符
     * @param mixed|null  $value 值
     * @return self 当前实例（支持链式调用）
     * @example ->having('count', '>', 1)
     */
    public function having($field, $operator = null, $value = null)
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }
        $this->having = "HAVING {$field} {$operator} '{$value}'";
        return $this;
    }

    /**
     * LIMIT 分页限制
     * @param int $offset 偏移量（或限制条数）
     * @param int|null $length 限制条数
     * @return self 当前实例（支持链式调用）
     * @example ->limit(10)       // LIMIT 10
     * @example ->limit(0, 10)    // LIMIT 0, 10
     */
    public function limit($offset, $length = null)
    {
        if ($length === null) {
            $this->limit = "LIMIT {$offset}";
        } else {
            $this->limit = "LIMIT {$offset}, {$length}";
        }
        return $this;
    }

    /**
     * 分页辅助方法（自动计算 offset）
     * @param int $page 页码
     * @param int $pageSize 每页条数
     * @return self 当前实例（支持链式调用）
     * @example ->page(2, 10)
     */
    public function page($page, $pageSize = 15)
    {
        $page = max(1, (int)$page);
        $pageSize = max(1, (int)$pageSize);
        $offset = ($page - 1) * $pageSize;
        return $this->limit($offset, $pageSize);
    }

    /**
     * JOIN 关联查询
     * @param string $table 关联表名（支持别名，如 'sys_tags t'）
     * @param string $on 关联条件
     * @param string $type 连接类型（INNER / LEFT / RIGHT）
     * @return self 当前实例（支持链式调用）
     * @example ->join('profile', 'users.id = profile.user_id')
     * @example ->join('sys_tags t', 't.id = u.tag_id')
     */
    public function join($table, $on, $type = 'INNER')
    {
        $table = $this->addPrefix($table);
        $this->join[] = strtoupper($type) . " JOIN {$table} ON {$on}";
        return $this;
    }

    /**
     * LEFT JOIN 左关联
     * @param string $table 关联表名（支持别名）
     * @param string $on 关联条件
     * @return self 当前实例（支持链式调用）
     * @example ->leftJoin('profile', 'users.id = profile.user_id')
     */
    public function leftJoin($table, $on)
    {
        return $this->join($table, $on, 'LEFT');
    }

    /**
     * RIGHT JOIN 右关联
     * @param string $table 关联表名（支持别名）
     * @param string $on 关联条件
     * @return self 当前实例（支持链式调用）
     * @example ->rightJoin('profile', 'users.id = profile.user_id')
     */
    public function rightJoin($table, $on)
    {
        return $this->join($table, $on, 'RIGHT');
    }

    /**
     * FOR UPDATE 行锁（悲观锁）
     * @return self 当前实例（支持链式调用）
     * @example ->lockForUpdate()->find()
     */
    public function lockForUpdate()
    {
        $this->lock = 'FOR UPDATE';
        return $this;
    }

    /**
     * LOCK IN SHARE MODE 共享锁
     * @return self 当前实例（支持链式调用）
     * @example ->sharedLock()->find()
     */
    public function sharedLock()
    {
        $this->lock = 'LOCK IN SHARE MODE';
        return $this;
    }

    /**
     * 查询缓存
     * @param int $ttl 缓存时间（秒），默认 60
     * @param string|null $key 缓存键，不传则自动生成
     * @return self 当前实例（支持链式调用）
     * @example ->cache(3600)->find()
     */
    public function cache($ttl = 60, $key = null)
    {
        $this->cacheEnabled = true;
        $this->cacheTtl = $ttl;
        if ($key === null) {
            $this->cacheKey = 'db_' . md5($this->table . '_' . $this->field . '_' . serialize($this->where));
        } else {
            $this->cacheKey = $key;
        }
        return $this;
    }

    // ─────────────────────────────────────────────────────────────
    // 查询执行
    // ─────────────────────────────────────────────────────────────

    /**
     * 查询单条记录
     * @return array|null 查询结果数组，不存在返回 null
     * @example $user = Db::table('users')->where('id', 1)->find()
     */
    public function find()
    {
        if ($this->cacheEnabled) {
            $cache = CacheManager::getInstance();
            $result = $cache->get($this->cacheKey);
            if ($result !== null) {
                $this->reset();
                return $result;
            }
        }

        $this->limit(1);
        $sql = $this->buildSelectSql();
        
        $params = $this->getAllParams();
        $this->logSql($sql, $params);

        $stmt = self::$pdo->prepare($sql);
        $this->bindWhereValues($stmt);
        $stmt->execute();
        $result = $stmt->fetch();

        if ($this->cacheEnabled && $result) {
            $cache = CacheManager::getInstance();
            $cache->set($this->cacheKey, $result, $this->cacheTtl);
        }

        $this->reset();
        return $result ?: null;
    }

    /**
     * 查询多条记录
     * @return array 查询结果数组
     * @example $users = Db::table('users')->where('status', 1)->select()
     */
    public function select()
    {
        if ($this->cacheEnabled) {
            $cache = CacheManager::getInstance();
            $result = $cache->get($this->cacheKey);
            if ($result !== null) {
                $this->reset();
                return $result;
            }
        }

        $sql = $this->buildSelectSql();
        
        $params = $this->getAllParams();
        $this->logSql($sql, $params);

        $stmt = self::$pdo->prepare($sql);
        $this->bindWhereValues($stmt);
        $stmt->execute();
        $result = $stmt->fetchAll();

        if ($this->cacheEnabled && $result) {
            $cache = CacheManager::getInstance();
            $cache->set($this->cacheKey, $result, $this->cacheTtl);
        }

        $this->reset();
        return $result;
    }

    /**
     * 统计记录数
     * @param string $field 统计字段，默认 *
     * @return int 记录总数
     * @example $count = Db::table('users')->where('status', 1)->count()
     */
    public function count($field = '*')
    {
        $tempField = $this->field;
        $tempOrder = $this->order;
        $tempLimit = $this->limit;
        $tempGroup = $this->group;
        $tempHaving = $this->having;
        $tempLock = $this->lock;
        $tempJoin = $this->join;
        $tempDistinct = $this->distinct;
        $tempWhere = $this->where;
        $tempWhereParams = $this->whereParams;

        $this->field = "COUNT({$field}) as total";
        $this->order = [];
        $this->limit = '';

        $sql = $this->buildSelectSql();
        
        $params = $this->getAllParams();
        $this->logSql($sql, $params);

        $stmt = self::$pdo->prepare($sql);
        $this->bindWhereValues($stmt);
        $stmt->execute();

        $result = $stmt->fetch();

        $this->field = $tempField;
        $this->order = $tempOrder;
        $this->limit = $tempLimit;
        $this->group = $tempGroup;
        $this->having = $tempHaving;
        $this->lock = $tempLock;
        $this->join = $tempJoin;
        $this->distinct = $tempDistinct;
        $this->where = $tempWhere;
        $this->whereParams = $tempWhereParams;

        return (int)($result['total'] ?? 0);
    }

    /**
     * 求和
     * @param string $field 字段名
     * @param string $alias 别名
     * @return float
     */
    public function sum($field, $alias = '')
    {
        $tempField = $this->field;
        $tempOrder = $this->order;
        $tempLimit = $this->limit;
        $tempGroup = $this->group;
        $tempHaving = $this->having;
        $tempLock = $this->lock;
        $tempJoin = $this->join;
        $tempDistinct = $this->distinct;
        $tempWhere = $this->where;
        $tempWhereParams = $this->whereParams;

        $this->field = "SUM({$field})" . ($alias ? " AS {$alias}" : '');
        $this->order = [];
        $this->limit = '';

        $sql = $this->buildSelectSql();
        
        $params = $this->getAllParams();
        $this->logSql($sql, $params);

        $stmt = self::$pdo->prepare($sql);
        $this->bindWhereValues($stmt);
        $stmt->execute();

        $result = $stmt->fetch();

        $this->field = $tempField;
        $this->order = $tempOrder;
        $this->limit = $tempLimit;
        $this->group = $tempGroup;
        $this->having = $tempHaving;
        $this->lock = $tempLock;
        $this->join = $tempJoin;
        $this->distinct = $tempDistinct;
        $this->where = $tempWhere;
        $this->whereParams = $tempWhereParams;

        return (float)($result[$alias ?: $field] ?? 0);
    }

    /**
     * 求平均值
     * @param string $field 字段名
     * @param string $alias 别名
     * @return float
     */
    public function avg($field, $alias = '')
    {
        $tempField = $this->field;
        $tempOrder = $this->order;
        $tempLimit = $this->limit;
        $tempGroup = $this->group;
        $tempHaving = $this->having;
        $tempLock = $this->lock;
        $tempJoin = $this->join;
        $tempDistinct = $this->distinct;
        $tempWhere = $this->where;
        $tempWhereParams = $this->whereParams;

        $this->field = "AVG({$field})" . ($alias ? " AS {$alias}" : '');
        $this->order = [];
        $this->limit = '';

        $sql = $this->buildSelectSql();
        
        $params = $this->getAllParams();
        $this->logSql($sql, $params);

        $stmt = self::$pdo->prepare($sql);
        $this->bindWhereValues($stmt);
        $stmt->execute();

        $result = $stmt->fetch();

        $this->field = $tempField;
        $this->order = $tempOrder;
        $this->limit = $tempLimit;
        $this->group = $tempGroup;
        $this->having = $tempHaving;
        $this->lock = $tempLock;
        $this->join = $tempJoin;
        $this->distinct = $tempDistinct;
        $this->where = $tempWhere;
        $this->whereParams = $tempWhereParams;

        return (float)($result[$alias ?: $field] ?? 0);
    }

    /**
     * 求最大值
     * @param string $field 字段名
     * @param string $alias 别名
     * @return mixed
     */
    public function max($field, $alias = '')
    {
        $tempField = $this->field;
        $tempOrder = $this->order;
        $tempLimit = $this->limit;
        $tempGroup = $this->group;
        $tempHaving = $this->having;
        $tempLock = $this->lock;
        $tempJoin = $this->join;
        $tempDistinct = $this->distinct;
        $tempWhere = $this->where;
        $tempWhereParams = $this->whereParams;

        $this->field = "MAX({$field})" . ($alias ? " AS {$alias}" : '');
        $this->order = [];
        $this->limit = '';

        $sql = $this->buildSelectSql();
        
        $params = $this->getAllParams();
        $this->logSql($sql, $params);

        $stmt = self::$pdo->prepare($sql);
        $this->bindWhereValues($stmt);
        $stmt->execute();

        $result = $stmt->fetch();

        $this->field = $tempField;
        $this->order = $tempOrder;
        $this->limit = $tempLimit;
        $this->group = $tempGroup;
        $this->having = $tempHaving;
        $this->lock = $tempLock;
        $this->join = $tempJoin;
        $this->distinct = $tempDistinct;
        $this->where = $tempWhere;
        $this->whereParams = $tempWhereParams;

        return $result[$alias ?: $field] ?? null;
    }

    /**
     * 求最小值
     * @param string $field 字段名
     * @param string $alias 别名
     * @return mixed
     */
    public function min($field, $alias = '')
    {
        $tempField = $this->field;
        $tempOrder = $this->order;
        $tempLimit = $this->limit;
        $tempGroup = $this->group;
        $tempHaving = $this->having;
        $tempLock = $this->lock;
        $tempJoin = $this->join;
        $tempDistinct = $this->distinct;
        $tempWhere = $this->where;
        $tempWhereParams = $this->whereParams;

        $this->field = "MIN({$field})" . ($alias ? " AS {$alias}" : '');
        $this->order = [];
        $this->limit = '';

        $sql = $this->buildSelectSql();
        
        $params = $this->getAllParams();
        $this->logSql($sql, $params);

        $stmt = self::$pdo->prepare($sql);
        $this->bindWhereValues($stmt);
        $stmt->execute();

        $result = $stmt->fetch();

        $this->field = $tempField;
        $this->order = $tempOrder;
        $this->limit = $tempLimit;
        $this->group = $tempGroup;
        $this->having = $tempHaving;
        $this->lock = $tempLock;
        $this->join = $tempJoin;
        $this->distinct = $tempDistinct;
        $this->where = $tempWhere;
        $this->whereParams = $tempWhereParams;

        return $result[$alias ?: $field] ?? null;
    }

    /**
     * 分页查询
     * @param int $page 当前页码
     * @param int $limit 每页条数
     * @return array 分页数据（含 data, total, page, limit, last_page, has_more）
     * @example $result = Db::table('users')->paginate(2, 10)
     */
    public function paginate($page = 1, $limit = 15)
    {
        $page = max(1, (int)$page);
        $limit = max(1, (int)$limit);
        $offset = ($page - 1) * $limit;

        $tempOrder = $this->order;
        $tempField = $this->field;
        $tempLimit = $this->limit;
        $tempGroup = $this->group;
        $tempHaving = $this->having;
        $tempLock = $this->lock;
        $tempJoin = $this->join;
        $tempDistinct = $this->distinct;
        $tempWhere = $this->where;
        $tempWhereParams = $this->whereParams;

        $total = $this->count();

        $this->order = $tempOrder;
        $this->field = $tempField;
        $this->limit = $tempLimit;
        $this->group = $tempGroup;
        $this->having = $tempHaving;
        $this->lock = $tempLock;
        $this->join = $tempJoin;
        $this->distinct = $tempDistinct;
        $this->where = $tempWhere;
        $this->whereParams = $tempWhereParams;

        $data = $this->limit($offset, $limit)->select();

        return [
            'data'      => $data,
            'total'     => (int)$total,
            'page'      => $page,
            'limit'     => $limit,
            'last_page' => ceil($total / $limit),
            'has_more'  => $page < ceil($total / $limit),
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // 写入操作
    // ─────────────────────────────────────────────────────────────

    /**
     * 插入单条记录
     * @param array $data 要插入的数据 ['field' => 'value']
     * @return int|string 插入后的自增 ID
     * @example $id = Db::table('users')->insert(['name' => '张三', 'email' => 'test@test.com'])
     */
    public function insert($data)
    {
        $this->clearCache();
        $fields = array_keys($data);
        $placeholders = ':' . implode(', :', $fields);
        $sql = "INSERT INTO {$this->table} (" . implode(', ', $fields) . ") VALUES ({$placeholders})";

        $params = [];
        foreach ($data as $key => $val) {
            $params[":{$key}"] = $val;
        }
        
        $this->logSql($sql, $params);

        $stmt = self::$pdo->prepare($sql);
        foreach ($data as $key => $val) {
            $stmt->bindValue(":{$key}", $val);
        }
        $stmt->execute();

        $id = self::$pdo->lastInsertId();
        $this->reset();
        return $id;
    }

    /**
     * 批量插入多条记录
     * @param array $dataList 二维数组，每个元素为一条记录
     * @return int 插入的记录数
     * @example $count = Db::table('users')->insertAll([['name'=>'张三'], ['name'=>'李四']])
     */
    public function insertAll($dataList)
    {
        if (empty($dataList)) {
            return 0;
        }

        $this->clearCache();
        $fields = array_keys($dataList[0]);
        $placeholders = ':' . implode(', :', $fields);
        $sql = "INSERT INTO {$this->table} (" . implode(', ', $fields) . ") VALUES ({$placeholders})";

        $count = 0;
        foreach ($dataList as $data) {
            $params = [];
            foreach ($data as $key => $val) {
                $params[":{$key}"] = $val;
            }
            $this->logSql($sql, $params);
            
            $stmt = self::$pdo->prepare($sql);
            foreach ($data as $key => $val) {
                $stmt->bindValue(":{$key}", $val);
            }
            $stmt->execute();
            $count++;
        }

        $this->reset();
        return $count;
    }

    /**
     * 更新记录
     * @param array $data 要更新的数据 ['field' => 'value']
     * @return int 影响的行数
     * @example $result = Db::table('users')->where('id', 1)->update(['name' => '李四'])
     */
    public function update($data)
    {
        $this->clearCache();
        $set = [];
        $bindValues = [];
        foreach ($data as $key => $val) {
            $set[] = "{$key} = :update_{$key}";
            $bindValues["update_{$key}"] = $val;
        }

        $sql = "UPDATE {$this->table} SET " . implode(', ', $set);
        $sql .= $this->buildWhereSql();
        
        $params = $bindValues;
        foreach ($this->where as $index => $item) {
            $isOr = isset($item[0]) && $item[0] === 'OR';
            
            // 检查是否是 RAW 条件
            if (!$isOr && isset($item[0]) && $item[0] === 'RAW') {
                $rawParams = $item[3] ?? [];
                foreach ($rawParams as $key => $val) {
                    if (is_int($key)) {
                        $params[":raw_{$index}_{$key}"] = $val;
                    } else {
                        $params[$key] = $val;
                    }
                }
                continue;
            } else if ($isOr && isset($item[1]) && $item[1] === 'RAW') {
                $rawParams = $item[4] ?? [];
                foreach ($rawParams as $key => $val) {
                    if (is_int($key)) {
                        $params[":or_raw_{$index}_{$key}"] = $val;
                    } else {
                        $params[$key] = $val;
                    }
                }
                continue;
            }
            
            if ($isOr) {
                $operator = $item[2] ?? '=';
                $value = $item[3] ?? null;
                if (!in_array($operator, ['IN', 'NOT IN', 'BETWEEN', 'IS NULL', 'IS NOT NULL', 'FIND_IN_SET', 'NOT FIND_IN_SET'])) {
                    $params[":or_where_{$index}"] = $value;
                }
            } else {
                $operator = $item[1] ?? '=';
                $value = $item[2] ?? null;
                if (!in_array($operator, ['IN', 'NOT IN', 'BETWEEN', 'IS NULL', 'IS NOT NULL', 'FIND_IN_SET', 'NOT FIND_IN_SET'])) {
                    $params[":where_{$index}"] = $value;
                }
            }
        }
        foreach ($this->whereParams as $key => $val) {
            $params[$key] = $val;
        }
        
        $this->logSql($sql, $params);

        $stmt = self::$pdo->prepare($sql);
        foreach ($bindValues as $key => $val) {
            $stmt->bindValue(":{$key}", $val);
        }
        $this->bindWhereValues($stmt);

        $stmt->execute();
        $affected = $stmt->rowCount();
        $this->reset();
        return $affected;
    }

    /**
     * 删除记录
     * @return int 影响的行数
     * @example $result = Db::table('users')->where('id', 1)->delete()
     */
    public function delete()
    {
        $this->clearCache();
        $sql = "DELETE FROM {$this->table}";
        $sql .= $this->buildWhereSql();
        
        $params = [];
        foreach ($this->where as $index => $item) {
            $isOr = isset($item[0]) && $item[0] === 'OR';
            
            // 检查是否是 RAW 条件
            if (!$isOr && isset($item[0]) && $item[0] === 'RAW') {
                $rawParams = $item[3] ?? [];
                foreach ($rawParams as $key => $val) {
                    if (is_int($key)) {
                        $params[":raw_{$index}_{$key}"] = $val;
                    } else {
                        $params[$key] = $val;
                    }
                }
                continue;
            } else if ($isOr && isset($item[1]) && $item[1] === 'RAW') {
                $rawParams = $item[4] ?? [];
                foreach ($rawParams as $key => $val) {
                    if (is_int($key)) {
                        $params[":or_raw_{$index}_{$key}"] = $val;
                    } else {
                        $params[$key] = $val;
                    }
                }
                continue;
            }
            
            if ($isOr) {
                $operator = $item[2] ?? '=';
                $value = $item[3] ?? null;
                if (!in_array($operator, ['IN', 'NOT IN', 'BETWEEN', 'IS NULL', 'IS NOT NULL', 'FIND_IN_SET', 'NOT FIND_IN_SET'])) {
                    $params[":or_where_{$index}"] = $value;
                }
            } else {
                $operator = $item[1] ?? '=';
                $value = $item[2] ?? null;
                if (!in_array($operator, ['IN', 'NOT IN', 'BETWEEN', 'IS NULL', 'IS NOT NULL', 'FIND_IN_SET', 'NOT FIND_IN_SET'])) {
                    $params[":where_{$index}"] = $value;
                }
            }
        }
        foreach ($this->whereParams as $key => $val) {
            $params[$key] = $val;
        }
        
        $this->logSql($sql, $params);

        $stmt = self::$pdo->prepare($sql);
        $this->bindWhereValues($stmt);

        $stmt->execute();
        $affected = $stmt->rowCount();
        $this->reset();
        return $affected;
    }

    /**
     * 清除查询缓存
     */
    protected function clearCache()
    {
        if ($this->cacheEnabled && $this->cacheKey) {
            $cache = CacheManager::getInstance();
            $cache->delete($this->cacheKey);
        }
        $this->cacheEnabled = false;
        $this->cacheKey = '';
    }

    // ─────────────────────────────────────────────────────────────
    // 事务
    // ─────────────────────────────────────────────────────────────

    /**
     * 开启事务
     * @return bool
     * @example Db::beginTransaction()
     */
    public function beginTransaction()
    {
        return self::$pdo->beginTransaction();
    }

    /**
     * 提交事务
     * @return bool
     * @example Db::commit()
     */
    public function commit()
    {
        return self::$pdo->commit();
    }

    /**
     * 回滚事务
     * @return bool
     * @example Db::rollback()
     */
    public function rollback()
    {
        return self::$pdo->rollback();
    }

    // ─────────────────────────────────────────────────────────────
    // 原生查询
    // ─────────────────────────────────────────────────────────────

    /**
     * 执行原生查询（返回结果集）
     * @param string $sql SQL 语句
     * @param array  $params 绑定参数
     * @return array 查询结果
     * @example Db::query("SELECT * FROM users WHERE id = ?", [1])
     */
    public static function query($sql, $params = [])
    {
        $instance = new self();
        $instance->logSql($sql, $params);
        $stmt = self::$pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * 执行原生写入（不返回结果集）
     * @param string $sql SQL 语句
     * @param array  $params 绑定参数
     * @return int 影响的行数
     * @example Db::execute("UPDATE users SET name = ? WHERE id = ?", ['李四', 1])
     */
    public static function execute($sql, $params = [])
    {
        $instance = new self();
        $instance->logSql($sql, $params);
        $stmt = self::$pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    // ─────────────────────────────────────────────────────────────
    // 日志
    // ─────────────────────────────────────────────────────────────

    /**
     * 获取所有 SQL 日志（带占位符）
     * @return array SQL 列表
     * @example $logs = Db::getLogs()
     */
    public static function getLogs()
    {
        return self::$logs;
    }

    /**
     * 获取完整 SQL 日志（参数已替换）
     * @return array 完整 SQL 列表
     * @example $fullLogs = Db::getFullLogs()
     */
    public static function getFullLogs()
    {
        return self::$fullLogs;
    }

    /**
     * 获取最后一条完整 SQL
     * @return string|null
     * @example $lastSql = Db::getLastFullSql()
     */
    public static function getLastFullSql()
    {
        $logs = self::$fullLogs;
        return end($logs);
    }

    /**
     * 清空 SQL 日志
     * @example Db::clearLogs()
     */
    public static function clearLogs()
    {
        self::$logs = [];
        self::$fullLogs = [];
    }

    /**
     * 记录 SQL 日志（内部方法）
     * @param string $sql 要记录的 SQL
     * @param array $params 绑定参数
     */
    private function logSql($sql, $params = [])
    {
        self::$logs[] = $sql;
        
        $fullSql = $this->buildFullSql($sql, $params);
        self::$fullLogs[] = $fullSql;
        
        if (function_exists('trace')) {
            trace($fullSql, 'sql');
        }
    }

    /**
     * 构建完整 SQL（替换占位符为实际值）
     * @param string $sql 带占位符的 SQL
     * @param array $params 参数数组
     * @return string
     */
    private function buildFullSql($sql, $params = [])
    {
        if (empty($params)) {
            return $sql;
        }
        
        // 检测占位符类型
        $isPositional = false;
        if (isset($params[0]) || (count($params) > 0 && is_int(array_keys($params)[0]))) {
            $isPositional = true;
        }
        
        if ($isPositional) {
            // 处理 ? 占位符
            $result = '';
            $paramIndex = 0;
            $sqlLength = strlen($sql);
            
            for ($i = 0; $i < $sqlLength; $i++) {
                if ($sql[$i] === '?' && isset($params[$paramIndex])) {
                    $result .= $this->formatSqlValue($params[$paramIndex]);
                    $paramIndex++;
                } else {
                    $result .= $sql[$i];
                }
            }
            return $result;
        }
        
        // 处理命名占位符 (:name)
        uksort($params, function($a, $b) {
            return strlen($b) - strlen($a);
        });

        foreach ($params as $key => $value) {
            if (is_array($value)) {
                $placeholders = [];
                foreach ($value as $v) {
                    $placeholders[] = $this->formatSqlValue($v);
                }
                $sql = str_replace($key, implode(', ', $placeholders), $sql);
            } else {
                $sql = str_replace($key, $this->formatSqlValue($value), $sql);
            }
        }

        return $sql;
    }

    /**
     * 格式化 SQL 值
     * @param mixed $value
     * @return string
     */
    private function formatSqlValue($value)
    {
        if (is_null($value)) {
            return 'NULL';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_numeric($value)) {
            return (string)$value;
        }
        return "'" . addslashes($value) . "'";
    }

    /**
     * 获取所有绑定的参数
     * @return array
     */
    private function getAllParams()
    {
        $params = [];
        
        foreach ($this->where as $index => $item) {
            $isOr = isset($item[0]) && $item[0] === 'OR';
            
            // 检查是否是 RAW 条件
            if (!$isOr && isset($item[0]) && $item[0] === 'RAW') {
                $rawParams = $item[3] ?? [];
                foreach ($rawParams as $key => $val) {
                    if (is_int($key)) {
                        $params[":raw_{$index}_{$key}"] = $val;
                    } else {
                        $params[$key] = $val;
                    }
                }
                continue;
            } else if ($isOr && isset($item[1]) && $item[1] === 'RAW') {
                $rawParams = $item[4] ?? [];
                foreach ($rawParams as $key => $val) {
                    if (is_int($key)) {
                        $params[":or_raw_{$index}_{$key}"] = $val;
                    } else {
                        $params[$key] = $val;
                    }
                }
                continue;
            }
            
            if ($isOr) {
                $operator = $item[2] ?? '=';
                $value = $item[3] ?? null;
                if (!in_array($operator, ['IN', 'NOT IN', 'BETWEEN', 'IS NULL', 'IS NOT NULL', 'FIND_IN_SET', 'NOT FIND_IN_SET'])) {
                    $params[":or_where_{$index}"] = $value;
                }
            } else {
                $operator = $item[1] ?? '=';
                $value = $item[2] ?? null;
                if (!in_array($operator, ['IN', 'NOT IN', 'BETWEEN', 'IS NULL', 'IS NOT NULL', 'FIND_IN_SET', 'NOT FIND_IN_SET'])) {
                    $params[":where_{$index}"] = $value;
                }
            }
        }
        
        foreach ($this->whereParams as $key => $val) {
            $params[$key] = $val;
        }
        
        return $params;
    }

    // ─────────────────────────────────────────────────────────────
    // 内部方法
    // ─────────────────────────────────────────────────────────────

    /**
     * 构建 SELECT 查询 SQL
     * @return string 完整的 SELECT SQL
     * @throws \Exception 表名为空时抛出异常
     */
    private function buildSelectSql()
    {
        if (empty($this->table)) {
            throw new \Exception('数据表未指定');
        }

        $distinct = $this->distinct ? 'DISTINCT ' : '';
        $sql = "SELECT {$distinct}{$this->field} FROM {$this->table}";

        if (!empty($this->join)) {
            $sql .= ' ' . implode(' ', $this->join);
        }

        $sql .= $this->buildWhereSql();

        if (!empty($this->group)) {
            $sql .= " {$this->group}";
        }

        if (!empty($this->having)) {
            $sql .= " {$this->having}";
        }

        if (!empty($this->order)) {
            $order = [];
            foreach ($this->order as $item) {
                $order[] = $item[0] . ' ' . $item[1];
            }
            $sql .= " ORDER BY " . implode(', ', $order);
        }

        if (!empty($this->limit)) {
            $sql .= " {$this->limit}";
        }

        if (!empty($this->lock)) {
            $sql .= " {$this->lock}";
        }

        return $sql;
    }

    /**
     * 构建 WHERE 子句
     * @return string WHERE 子句，无条件时返回空字符串
     */
    public function buildWhereSql()
    {
        if (empty($this->where)) {
            return '';
        }

        $conditions = [];
        $orConditions = [];
        
        foreach ($this->where as $index => $item) {
            $isOr = isset($item[0]) && $item[0] === 'OR';
            
            // 检查是否是 RAW 条件
            $isRaw = false;
            if (!$isOr && isset($item[0]) && $item[0] === 'RAW') {
                $isRaw = true;
                $not = isset($item[1]) && $item[1] === 'NOT';
                $sql = $item[2] ?? '';
                $condition = $not ? "NOT ({$sql})" : $sql;
            } else if ($isOr && isset($item[1]) && $item[1] === 'RAW') {
                $isRaw = true;
                $not = isset($item[2]) && $item[2] === 'NOT';
                $sql = $item[3] ?? '';
                $condition = $not ? "NOT ({$sql})" : $sql;
            }
            
            if ($isRaw) {
                if ($isOr) {
                    $orConditions[] = $condition;
                } else {
                    if (!empty($orConditions)) {
                        $conditions[] = '(' . implode(' OR ', $orConditions) . ')';
                        $orConditions = [];
                    }
                    $conditions[] = $condition;
                }
                continue;
            }
            
            // 处理普通条件
            if ($isOr) {
                $field = $item[1];
                $operator = $item[2];
                $value = $item[3] ?? '';
                $orConditions[] = $this->buildCondition($field, $operator, $value, $index, true);
            } else {
                if (!empty($orConditions)) {
                    $conditions[] = '(' . implode(' OR ', $orConditions) . ')';
                    $orConditions = [];
                }
                $field = $item[0];
                $operator = $item[1];
                $value = $item[2] ?? '';
                $conditions[] = $this->buildCondition($field, $operator, $value, $index, false);
            }
        }
        
        if (!empty($orConditions)) {
            $conditions[] = '(' . implode(' OR ', $orConditions) . ')';
        }
        
        return " WHERE " . implode(' AND ', $conditions);
    }

    /**
     * 构建单个条件
     */
    private function buildCondition($field, $operator, $value, $index, $isOr = false)
    {
        // 处理 FIND_IN_SET
        if ($operator === 'FIND_IN_SET') {
            return "FIND_IN_SET('{$value}', {$field})";
        }
        if ($operator === 'NOT FIND_IN_SET') {
            return "NOT FIND_IN_SET('{$value}', {$field})";
        }

        if (in_array($operator, ['IN', 'NOT IN'])) {
            return "{$field} {$operator} {$value}";
        }

        if (in_array($operator, ['IS NULL', 'IS NOT NULL'])) {
            return "{$field} {$operator}";
        }

        if ($operator === 'BETWEEN') {
            return "{$field} {$operator} {$value}";
        }

        $paramKey = $isOr ? ":or_where_{$index}" : ":where_{$index}";
        return "{$field} {$operator} {$paramKey}";
    }

    /**
     * 绑定 WHERE 条件的参数值
     * @param PDOStatement $stmt PDO 预处理对象
     */
    private function bindWhereValues($stmt)
    {
        foreach ($this->where as $index => $item) {
            $isOr = isset($item[0]) && $item[0] === 'OR';
            
            // 检查是否是 RAW 条件
            if (!$isOr && isset($item[0]) && $item[0] === 'RAW') {
                $rawParams = $item[3] ?? [];
                foreach ($rawParams as $key => $val) {
                    if (is_int($key)) {
                        $stmt->bindValue(":raw_{$index}_{$key}", $val);
                    } else {
                        $stmt->bindValue($key, $val);
                    }
                }
                continue;
            } else if ($isOr && isset($item[1]) && $item[1] === 'RAW') {
                $rawParams = $item[4] ?? [];
                foreach ($rawParams as $key => $val) {
                    if (is_int($key)) {
                        $stmt->bindValue(":or_raw_{$index}_{$key}", $val);
                    } else {
                        $stmt->bindValue($key, $val);
                    }
                }
                continue;
            }
            
            if ($isOr) {
                $operator = $item[2] ?? '=';
                $value = $item[3] ?? null;
                $paramKey = ":or_where_{$index}";
            } else {
                $operator = $item[1] ?? '=';
                $value = $item[2] ?? null;
                $paramKey = ":where_{$index}";
            }

            // 跳过不需要绑定的操作符
            if (in_array($operator, ['IN', 'NOT IN', 'BETWEEN', 'IS NULL', 'IS NOT NULL', 'FIND_IN_SET', 'NOT FIND_IN_SET'])) {
                continue;
            }

            if (is_array($value)) {
                continue;
            }

            $stmt->bindValue($paramKey, $value);
        }

        foreach ($this->whereParams as $key => $val) {
            $stmt->bindValue($key, $val);
        }
    }

    /**
     * 重置查询状态
     */
    private function reset()
    {
        $this->where = [];
        $this->whereParams = [];
        $this->order = [];
        $this->limit = '';
        $this->field = '*';
        $this->group = '';
        $this->having = '';
        $this->join = [];
        $this->distinct = false;
        $this->lock = '';
    }

    /**
     * 析构时重置
     */
    public function __destruct()
    {
        $this->reset();
    }
}