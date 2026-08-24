<?php
// src/Db.php
// LongPHP Framework - 数据库查询构造器
// 龙行天下 🐉

namespace Long;

use PDO;
use PDOException;
use Long\Cache\CacheManager;

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
     * SQL 执行日志
     * @var array
     */
    private static $logs = [];

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
        $this->config = $config ?: require ROOT_PATH . '/config/database.php';
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
            $config = require ROOT_PATH . '/config/database.php';
            if (isset($config['connections'][$name])) {
                $instance->config['default'] = $name;
                self::$pdo = null;
                $instance->connect();
            }
        }
        return $instance;
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
        return strpos($table, $this->prefix) === 0;
    }
    /**
     * 自动添加表前缀
     * @param string $table 表名
     * @return string
     */
    private function addPrefix($table)
    {
        if (empty($this->prefix) || $this->hasPrefix($table)) {
            return $table;
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
     * 指定查询字段
     * @param string $field 字段列表，如 'id, name, email' 或 'id,name'
     * @return self 当前实例（支持链式调用）
     * @example ->field('id, name, email')
     */
    public function field($field)
    {
        $this->field = $field;
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
     * @param string|array $field 字段名，或 ['name' => '张三'] 数组
     * @param string|null  $operator 操作符（=, >, <, >=, <=, <> 等）
     * @param mixed|null   $value 值
     * @return self 当前实例（支持链式调用）
     * @example
     * ->where('id', 1)
     * ->where('age', '>', 18)
     * ->where(['name' => '张三', 'status' => 1])
     */
    public function where($field, $operator = null, $value = null)
    {
        if (is_string($field) && !preg_match('/^[a-zA-Z0-9_\.]+$/', $field)) {
            throw new \Exception('非法的字段名: ' . $field);
        }
        if (is_array($field)) {
            foreach ($field as $key => $val) {
                $this->where[] = [$key, '=', $val];
            }
        } else {
            if ($value === null) {
                $value = $operator;
                $operator = '=';
            }
            $this->where[] = [$field, $operator, $value];
        }
        return $this;
    }

    /**
     * WHERE IN 条件
     * @param string $field 字段名
     * @param array  $values 值数组
     * @return self 当前实例（支持链式调用）
     * @example ->whereIn('id', [1, 2, 3])
     */
    public function whereIn($field, $values)
    {
        if (!is_array($values) || empty($values)) {
            return $this;
        }

        $placeholders = [];
        foreach ($values as $i => $val) {
            $key = ":in_{$field}_{$i}";
            $placeholders[] = $key;
            $this->whereParams[$key] = $val;
        }

        $this->where[] = [$field, 'IN', '(' . implode(', ', $placeholders) . ')'];
        return $this;
    }

    /**
     * WHERE NOT IN 条件
     * @param string $field 字段名
     * @param array  $values 值数组
     * @return self 当前实例（支持链式调用）
     * @example ->whereNotIn('id', [1, 2, 3])
     */
    public function whereNotIn($field, $values)
    {
        if (!is_array($values) || empty($values)) {
            return $this;
        }

        $placeholders = [];
        foreach ($values as $i => $val) {
            $key = ":nin_{$field}_{$i}";
            $placeholders[] = $key;
            $this->whereParams[$key] = $val;
        }

        $this->where[] = [$field, 'NOT IN', '(' . implode(', ', $placeholders) . ')'];
        return $this;
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
     * ORDER BY 排序
     * @param string $field 字段名
     * @param string $direction 排序方向（ASC / DESC）
     * @return self 当前实例（支持链式调用）
     * @example ->order('id', 'DESC')
     */
    public function order($field, $direction = 'ASC')
    {
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
     * JOIN 关联查询
     * @param string $table 关联表名
     * @param string $on 关联条件
     * @param string $type 连接类型（INNER / LEFT / RIGHT）
     * @return self 当前实例（支持链式调用）
     * @example ->join('profile', 'users.id = profile.user_id')
     */
    public function join($table, $on, $type = 'INNER')
    {
        $table = $this->addPrefix($table);
        $this->join[] = strtoupper($type) . " JOIN {$table} ON {$on}";
        return $this;
    }

    /**
     * LEFT JOIN 左关联
     * @param string $table 关联表名
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
     * @param string $table 关联表名
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
        $this->logSql($sql);

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
        $this->logSql($sql);

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

        $this->field = "COUNT({$field}) as total";
        $this->order = [];
        $this->limit = '';

        $sql = $this->buildSelectSql();
        $this->logSql($sql);

        $stmt = self::$pdo->prepare($sql);
        $this->bindWhereValues($stmt);
        $stmt->execute();

        $result = $stmt->fetch();

        $this->field = $tempField;
        $this->order = $tempOrder;
        $this->limit = $tempLimit;
        $this->reset();

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

        $this->field = "SUM({$field})" . ($alias ? " AS {$alias}" : '');
        $this->order = [];
        $this->limit = '';

        $sql = $this->buildSelectSql();
        $this->logSql($sql);

        $stmt = self::$pdo->prepare($sql);
        $this->bindWhereValues($stmt);
        $stmt->execute();

        $result = $stmt->fetch();

        $this->field = $tempField;
        $this->order = $tempOrder;
        $this->limit = $tempLimit;
        $this->reset();

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

        $this->field = "AVG({$field})" . ($alias ? " AS {$alias}" : '');
        $this->order = [];
        $this->limit = '';

        $sql = $this->buildSelectSql();
        $this->logSql($sql);

        $stmt = self::$pdo->prepare($sql);
        $this->bindWhereValues($stmt);
        $stmt->execute();

        $result = $stmt->fetch();

        $this->field = $tempField;
        $this->order = $tempOrder;
        $this->limit = $tempLimit;
        $this->reset();

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

        $this->field = "MAX({$field})" . ($alias ? " AS {$alias}" : '');
        $this->order = [];
        $this->limit = '';

        $sql = $this->buildSelectSql();
        $this->logSql($sql);

        $stmt = self::$pdo->prepare($sql);
        $this->bindWhereValues($stmt);
        $stmt->execute();

        $result = $stmt->fetch();

        $this->field = $tempField;
        $this->order = $tempOrder;
        $this->limit = $tempLimit;
        $this->reset();

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

        $this->field = "MIN({$field})" . ($alias ? " AS {$alias}" : '');
        $this->order = [];
        $this->limit = '';

        $sql = $this->buildSelectSql();
        $this->logSql($sql);

        $stmt = self::$pdo->prepare($sql);
        $this->bindWhereValues($stmt);
        $stmt->execute();

        $result = $stmt->fetch();

        $this->field = $tempField;
        $this->order = $tempOrder;
        $this->limit = $tempLimit;
        $this->reset();

        return $result[$alias ?: $field] ?? null;
    }

    /**
     * 分页查询
     * @param int $page 当前页码
     * @param int $limit 每页条数
     * @return array 分页数据（含 list, total, page, limit, last_page, has_more）
     * @example $result = Db::table('users')->paginate(2, 10)
     */
    public function paginate($page = 1, $limit = 15)
    {
        $page = max(1, (int)$page);
        $limit = max(1, (int)$limit);
        $offset = ($page - 1) * $limit;

        $total = $this->count();
        $data = $this->limit($offset, $limit)->select();

        return [
            'list'      => $data,
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

        $this->logSql($sql);

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
     * @return bool 是否执行成功
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
        $this->logSql($sql);

        $stmt = self::$pdo->prepare($sql);
        foreach ($bindValues as $key => $val) {
            $stmt->bindValue(":{$key}", $val);
        }
        $this->bindWhereValues($stmt);

        $result = $stmt->execute();
        $this->reset();
        return $result;
    }

    /**
     * 删除记录
     * @return bool 是否执行成功
     * @example $result = Db::table('users')->where('id', 1)->delete()
     */
    public function delete()
    {
        $this->clearCache();
        $sql = "DELETE FROM {$this->table}";
        $sql .= $this->buildWhereSql();
        $this->logSql($sql);

        $stmt = self::$pdo->prepare($sql);
        $this->bindWhereValues($stmt);

        $result = $stmt->execute();
        $this->reset();
        return $result;
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
    public function query($sql, $params = [])
    {
        $this->logSql($sql);
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
    public function execute($sql, $params = [])
    {
        $this->logSql($sql);
        $stmt = self::$pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    // ─────────────────────────────────────────────────────────────
    // 日志
    // ─────────────────────────────────────────────────────────────

    /**
     * 获取所有 SQL 日志
     * @return array SQL 列表
     * @example $logs = Db::getLogs()
     */
    public static function getLogs()
    {
        return self::$logs;
    }

    /**
     * 清空 SQL 日志
     * @example Db::clearLogs()
     */
    public static function clearLogs()
    {
        self::$logs = [];
    }

    /**
     * 记录 SQL 日志（内部方法）
     * @param string $sql 要记录的 SQL
     */
    private function logSql($sql)
    {
        self::$logs[] = $sql;
        if (function_exists('trace')) {
            trace($sql, 'sql');
        }
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
    private function buildWhereSql()
    {
        if (empty($this->where)) {
            return '';
        }

        $conditions = [];
        foreach ($this->where as $index => $item) {
            $field = $item[0];
            $operator = $item[1];
            $value = $item[2] ?? '';

            if (in_array($operator, ['IN', 'NOT IN'])) {
                $conditions[] = "{$field} {$operator} {$value}";
                continue;
            }

            if (in_array($operator, ['IS NULL', 'IS NOT NULL'])) {
                $conditions[] = "{$field} {$operator}";
                continue;
            }

            if ($operator === 'BETWEEN') {
                $conditions[] = "{$field} {$operator} {$value}";
                continue;
            }

            $conditions[] = "{$field} {$operator} :where_{$index}";
        }

        return " WHERE " . implode(' AND ', $conditions);
    }

    /**
     * 绑定 WHERE 条件的参数值
     * @param PDOStatement $stmt PDO 预处理对象
     */
    private function bindWhereValues($stmt)
    {
        foreach ($this->where as $index => $item) {
            $operator = $item[1] ?? '=';

            if (in_array($operator, ['IN', 'NOT IN', 'BETWEEN', 'IS NULL', 'IS NOT NULL'])) {
                continue;
            }

            $stmt->bindValue(":where_{$index}", $item[2] ?? null);
        }

        foreach ($this->whereParams as $key => $val) {
            $stmt->bindValue($key, $val);
        }
    }

    /**
     * 重置查询状态（释放当前查询构造器状态）
     * 每次查询执行后自动调用，避免状态污染
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