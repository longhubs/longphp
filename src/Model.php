<?php
// src/Model.php
// LongPHP Framework - 模型基类（完整版）
// 龙行天下 🐉

namespace Long;

use Long\App;  // ⬅️ 添加引用

class Model
{
    /**
     * 表名
     * @var string
     */
    protected $table = '';

    /**
     * 主键字段名
     * @var string
     */
    protected $pk = 'id';

    /**
     * 当前数据
     * @var array
     */
    protected $data = [];

    /**
     * 数据库查询构造器实例
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

    // ==================== 时间戳和软删除属性 ====================

    /**
     * 是否自动写入时间戳
     * @var bool
     */
    protected $autoWriteTimestamp = true;

    /**
     * 创建时间字段名
     * @var string
     */
    protected $createTime = 'create_time';

    /**
     * 更新时间字段名
     * @var string
     */
    protected $updateTime = 'update_time';

    /**
     * 软删除字段名（false 表示不启用软删除）
     * @var string|false
     */
    protected $deleteTime = 'deleted';

    /**
     * 软删除默认值（未删除状态的值）
     * @var int
     */
    protected $defaultSoftDelete = 0;

    /**
     * 验证器类名
     * @var string
     */
    protected $validateClass = '';

    /**
     * 允许批量赋值的字段
     * @var array
     */
    protected $field = [];

    /**
     * App 实例
     * @var App
     */
    protected $app;  // ⬅️ 添加 App 实例

    // ─────────────────────────────────────────────────────────────
    // 构造函数
    // ─────────────────────────────────────────────────────────────

    /**
     * 构造函数：初始化模型，自动获取表名和表前缀
     * @example
     * $user = new UserModel();
     * // 自动生成表名：user（根据类名 UserModel 转换）
     * // 自动添加表前缀：如 pre_user
     */
    public function __construct()
    {
        // ⬅️ 获取 App 实例
        $this->app = App::getInstance();
        
        $this->db = new Db();

        // ✅ 使用框架的 getConfigPath() 方法
        $configFile = $this->app->getConfigPath('database.php');
        $config = file_exists($configFile) ? require $configFile : [];
        $prefix = $config['connections'][$config['default']]['prefix'] ?? '';

        if (empty($this->table)) {
            $className = (new \ReflectionClass($this))->getShortName();
            $this->table = strtolower(preg_replace('/(?<=[a-z])([A-Z])/', '_$1', $className));
            $this->table = str_replace('_model', '', $this->table);
        }

        if (!empty($prefix) && strpos($this->table, $prefix) !== 0) {
            $this->table = $prefix . $this->table;
        }
    }

    // ... 其余所有方法保持不变（从 __callStatic 到最后的 __unset）
    // 因为只有构造函数使用了 ROOT_PATH
    
    // ─────────────────────────────────────────────────────────────
    // 静态调用转发
    // ─────────────────────────────────────────────────────────────

    /**
     * 静态调用转发到实例方法
     * @param string $method 方法名
     * @param array $args 参数
     * @return mixed
     * @example
     * // 静态调用
     * $user = UserModel::findById(1);
     * // 等价于
     * $user = (new UserModel())->findById(1);
     */
    public static function __callStatic($method, $args)
    {
        $model = new static();
        return $model->$method(...$args);
    }

    // ─────────────────────────────────────────────────────────────
    // 实例调用转发
    // ─────────────────────────────────────────────────────────────

    /**
     * 实例调用转发到 Db 查询构造器
     * @param string $method 方法名
     * @param array $args 参数
     * @return mixed
     * @example
     * // 链式调用
     * $list = UserModel::where('status', 1)
     *     ->order('id DESC')
     *     ->select();
     */
    public function __call($method, $args)
    {
        // 特殊处理 as 和 alias 方法，更新模型的表名（支持别名）
        if ($method === 'as' || $method === 'alias') {
            $this->db->setTable($this->table);
            $result = $this->db->$method(...$args);
            if ($result instanceof Db) {
                $this->table = $this->db->getTable();
                return $this;
            }
            return $result;
        }

        $this->db->setTable($this->table);
        $result = $this->db->$method(...$args);

        if ($result instanceof Db) {
            return $this;
        }

        // find 方法返回数组而不是对象（便于直接使用）
        if ($method === 'find') {
            if ($result) {
                $this->data = $result;
                return $this->data;
            }
            return null;
        }

        // select 方法返回模型数据数组
        if ($method === 'select') {
            $list = [];
            foreach ($result as $data) {
                $m = new static();
                $m->data = $data;
                $list[] = $m->toArray();
            }
            return $list;
        }

        // 聚合方法直接返回结果
        if (in_array($method, ['count', 'sum', 'avg', 'max', 'min'])) {
            return $result;
        }

        // paginate 方法处理分页数据
        if ($method === 'paginate') {
            $list = [];
            $dataKey = isset($result['data']) ? 'data' : 'list';
            foreach ($result[$dataKey] as $data) {
                $m = new static();
                $m->data = $data;
                $list[] = $m->toArray();
            }
            $result[$dataKey] = $list;
            return $result;
        }

        return $result;
    }

    // ─────────────────────────────────────────────────────────────
    // 缓存
    // ─────────────────────────────────────────────────────────────

    /**
     * 启用查询缓存
     * @param int $ttl 缓存时间（秒），默认 60
     * @param string|null $key 缓存键，不传则自动生成
     * @return $this
     * @example
     * // 缓存 5 分钟
     * $list = UserModel::cache(300)->where('status', 1)->select();
     *
     * // 自定义缓存键
     * $list = UserModel::cache(60, 'user_list_vip')->select();
     */
    public function cache($ttl = 60, $key = null)
    {
        $this->cacheEnabled = true;
        $this->cacheTtl = $ttl;
        $this->cacheKey = $key ?: 'model_' . md5($this->table . '_' . get_class($this));
        $this->db->cache($ttl, $this->cacheKey);
        return $this;
    }

    // ─────────────────────────────────────────────────────────────
    // 数据操作
    // ─────────────────────────────────────────────────────────────

    /**
     * 获取数据
     * @param string|null $key 字段名，不传则返回所有数据
     * @return mixed|array|null
     * @example
     * $user = UserModel::findById(1);
     * $all = $user->getData();        // 获取所有字段
     * $name = $user->getData('name'); // 获取 name 字段
     */
    public function getData($key = null)
    {
        return $key === null ? $this->data : ($this->data[$key] ?? null);
    }

    /**
     * 设置数据
     * @param string|array $key 字段名或数据数组
     * @param mixed|null $value 值
     * @return $this
     * @example
     * $user = new UserModel();
     * $user->setData('name', '张三');
     * $user->setData(['name' => '张三', 'age' => 18]);
     */
    public function setData($key, $value = null)
    {
        if (is_array($key)) {
            $this->data = array_merge($this->data, $key);
        } else {
            $this->data[$key] = $value;
        }
        return $this;
    }

    /**
     * 将数据转换为数组
     * @return array
     * @example
     * $user = UserModel::findById(1);
     * $userArray = $user->toArray();
     * echo $userArray['name'];
     */
    public function toArray()
    {
        return $this->data;
    }

    /**
     * 获取主键值
     * @return mixed|null
     * @example
     * $user = UserModel::addData(['name' => '张三']);
     * $id = $user->getKey(); // 获取插入后的自增ID
     */
    public function getKey()
    {
        $pk = $this->pk;
        return $this->data[$pk] ?? null;
    }

    // ─────────────────────────────────────────────────────────────
    // 魔术方法
    // ─────────────────────────────────────────────────────────────

    /**
     * 魔术方法：获取属性
     * @param string $name 属性名
     * @return mixed|null
     * @example
     * $user = UserModel::findById(1);
     * echo $user->name; // 直接访问字段
     */
    public function __get($name)
    {
        return $this->data[$name] ?? null;
    }

    /**
     * 魔术方法：设置属性
     * @param string $name 属性名
     * @param mixed $value 值
     * @example
     * $user = new UserModel();
     * $user->name = '张三';
     */
    public function __set($name, $value)
    {
        $this->data[$name] = $value;
    }

    /**
     * 魔术方法：检查属性是否存在
     * @param string $name 属性名
     * @return bool
     */
    public function __isset($name)
    {
        return isset($this->data[$name]);
    }

    /**
     * 魔术方法：删除属性
     * @param string $name 属性名
     */
    public function __unset($name)
    {
        unset($this->data[$name]);
    }

    // ─────────────────────────────────────────────────────────────
    // ==================== 扩展方法 ====================
    // ─────────────────────────────────────────────────────────────

    // ─── 新增数据 ──────────────────────────────────────────────

    /**
     * 新增数据并返回模型对象
     * @param array $data 数据
     * @param bool $runValidate 是否验证
     * @return static
     * @throws \Exception
     * @example
     * try {
     *     $user = UserModel::addData([
     *         'name' => '张三',
     *         'email' => 'zhangsan@test.com',
     *         'age' => 18
     *     ]);
     *     echo '添加成功，ID：' . $user->getKey();
     * } catch (\Exception $e) {
     *     echo '添加失败：' . $e->getMessage();
     * }
     */
    public static function addData($data, $runValidate = true)
    {
        if ($runValidate && !static::validateData($data)) {
            $error = static::getValidateErrorMsg($data);
            throw new \Exception('数据验证失败：' . $error);
        }

        try {
            $instance = new static();
            $timestamp = date('Y-m-d H:i:s');

            if ($instance->autoWriteTimestamp) {
                if ($instance->createTime && !isset($data[$instance->createTime])) {
                    $data[$instance->createTime] = $timestamp;
                }
                if ($instance->updateTime && !isset($data[$instance->updateTime])) {
                    $data[$instance->updateTime] = $timestamp;
                }
            }

            if ($instance->deleteTime !== false && !isset($data[$instance->deleteTime])) {
                $data[$instance->deleteTime] = $instance->defaultSoftDelete;
            }

            $id = Db::table($instance->table)->insert($data);
            if ($id) {
                $instance->data = $data;
                $instance->data[$instance->pk] = $id;
                return $instance;
            }
            throw new \Exception('插入数据失败，未返回ID');
        } catch (\Exception $e) {
            throw new \Exception('添加数据失败：' . $e->getMessage());
        }
    }

    /**
     * 新增数据并返回主键ID
     * @param array $data 数据
     * @param bool $runValidate 是否验证
     * @return int
     * @throws \Exception
     * @example
     * try {
     *     $id = UserModel::addGetId(['name' => '张三', 'email' => 'zhangsan@test.com']);
     *     echo '添加成功，ID：' . $id;
     * } catch (\Exception $e) {
     *     echo '添加失败：' . $e->getMessage();
     * }
     */
    public static function addGetId($data, $runValidate = true)
    {
        $model = self::addData($data, $runValidate);
        return $model->getKey();
    }

    /**
     * 批量新增数据
     * @param array $dataList 数据列表
     * @param bool $runValidate 是否验证
     * @return array
     * @throws \Exception
     * @example
     * $dataList = [
     *     ['name' => '张三', 'email' => 'zs@test.com'],
     *     ['name' => '李四', 'email' => 'ls@test.com'],
     * ];
     * $results = UserModel::addBatch($dataList);
     * foreach ($results as $data) {
     *     echo 'ID：' . $data['id'] . '，姓名：' . $data['name'] . "\n";
     * }
     */
    public static function addBatch($dataList, $runValidate = true)
    {
        if (empty($dataList)) {
            return [];
        }

        if ($runValidate) {
            foreach ($dataList as $data) {
                if (!static::validateData($data)) {
                    $error = static::getValidateErrorMsg($data);
                    throw new \Exception('数据验证失败：' . $error);
                }
            }
        }

        try {
            $instance = new static();
            $timestamp = date('Y-m-d H:i:s');
            $insertDataList = [];

            // 先准备好所有数据，不直接用引用
            foreach ($dataList as $data) {
                // 移除主键
                if (isset($data[$instance->pk])) {
                    unset($data[$instance->pk]);
                }

                if ($instance->autoWriteTimestamp) {
                    if ($instance->createTime && !isset($data[$instance->createTime])) {
                        $data[$instance->createTime] = $timestamp;
                    }
                    if ($instance->updateTime && !isset($data[$instance->updateTime])) {
                        $data[$instance->updateTime] = $timestamp;
                    }
                }
                if ($instance->deleteTime !== false && !isset($data[$instance->deleteTime])) {
                    $data[$instance->deleteTime] = $instance->defaultSoftDelete;
                }

                $insertDataList[] = $data;
            }

            $results = [];
            foreach ($insertDataList as $data) {
                $id = Db::table($instance->table)->insert($data);
                if ($id) {
                    $data[$instance->pk] = $id;
                    $results[] = $data;
                }
            }
            return $results;
        } catch (\Exception $e) {
            throw new \Exception('批量添加数据失败：' . $e->getMessage());
        }
    }

    /**
     * 批量新增并返回所有ID
     * @param array $dataList 数据列表
     * @param bool $runValidate 是否验证
     * @return array
     * @throws \Exception
     * @example
     * $ids = UserModel::addBatchGetIds([
     *     ['name' => '张三'],
     *     ['name' => '李四'],
     *     ['name' => '王五']
     * ]);
     * print_r($ids); // [1, 2, 3]
     */
    public static function addBatchGetIds($dataList, $runValidate = true)
    {
        $results = self::addBatch($dataList, $runValidate);
        $ids = [];
        $pk = (new static())->pk;
        foreach ($results as $data) {
            $ids[] = $data[$pk] ?? 0;
        }
        return $ids;
    }

    /**
     * 获取或创建（存在返回，不存在创建）
     * @param array $where 查询条件
     * @param array $data 创建数据
     * @return static
     * @throws \Exception
     * @example
     * // 查找用户，不存在则创建
     * $user = UserModel::firstOrNewData(
     *     ['email' => 'test@test.com'],
     *     ['name' => '测试用户', 'age' => 18]
     * );
     * echo $user->id . ' - ' . $user->name;
     */
    public static function firstOrNewData($where, $data = [])
    {
        $model = static::findOne($where);
        if (!$model) {
            $model = static::addData(array_merge($where, $data));
        }
        return $model;
    }

    /**
     * 获取或创建（返回主键ID）
     * @param array $where 查询条件
     * @param array $data 创建数据
     * @return int
     * @throws \Exception
     * @example
     * $id = UserModel::firstOrNewGetId(
     *     ['email' => 'test@test.com'],
     *     ['name' => '测试用户']
     * );
     * echo '用户ID：' . $id;
     */
    public static function firstOrNewGetId($where, $data = [])
    {
        $model = static::firstOrNewData($where, $data);
        return $model->getKey();
    }

    // ─── 删除数据 ──────────────────────────────────────────────

    /**
     * 转换 ID 为数组（支持字符串、数组、单个ID）
     * @param int|array|string $ids
     * @return array
     * @example
     * $ids = normalizeIds('1,2,3'); // [1,2,3]
     * $ids = normalizeIds([1,2,3]);  // [1,2,3]
     * $ids = normalizeIds(1);        // [1]
     */
    private static function normalizeIds($ids)
    {
        // 如果是字符串，按逗号分隔转成数组
        if (is_string($ids)) {
            $ids = array_map('trim', explode(',', $ids));
            $ids = array_filter($ids, function($v) {
                return $v !== '' && $v !== null;
            });
            $ids = array_values($ids);
        }
        
        // 如果是单个数字，转成数组
        if (is_numeric($ids)) {
            $ids = [$ids];
        }
        
        if (!is_array($ids) || empty($ids)) {
            return [];
        }
        
        return $ids;
    }

    /**
     * 根据主键删除（真删除）
     * @param int|array|string $ids 主键值、数组 或 逗号分隔的字符串（如 '1,2,3'）
     * @return int 影响的行数
     * @throws \Exception
     * @example
     * // 删除单个
     * $affected = UserModel::deleteById(1);
     *
     * // 删除多个（数组）
     * $affected = UserModel::deleteById([1, 2, 3]);
     *
     * // 删除多个（字符串）
     * $affected = UserModel::deleteById('1,2,3,4,5');
     *
     * // 从请求参数获取
     * $ids = $_POST['ids'] ?? '';
     * $affected = UserModel::deleteById($ids);
     */
    public static function deleteById($ids)
    {
        $ids = self::normalizeIds($ids);
        if (empty($ids)) {
            return false;
        }
        
        try {
            $instance = new static();
            $db = Db::table($instance->table);
            if (count($ids) === 1) {
                $db->where($instance->pk, $ids[0]);
            } else {
                $db->whereIn($instance->pk, $ids);
            }
            return $db->delete();
        } catch (\Exception $e) {
            throw new \Exception('删除数据失败：' . $e->getMessage());
        }
    }

    /**
     * 条件删除（真删除）
     * @param array $where 条件
     * @return int 影响行数
     * @throws \Exception
     * @example
     * // 删除所有状态为 0 的用户
     * $affected = UserModel::deleteWhere(['status' => 0]);
     *
     * // 删除年龄大于 50 的用户
     * $affected = UserModel::deleteWhere([['age', '>', 50]]);
     */
    public static function deleteWhere($where)
    {
        try {
            $instance = new static();
            $db = Db::table($instance->table);
            self::applyWhereConditions($db, $where);
            return $db->delete();
        } catch (\Exception $e) {
            throw new \Exception('删除数据失败：' . $e->getMessage());
        }
    }

    /**
     * 软删除（根据主键）
     * @param int|array|string $ids 主键值、数组 或 逗号分隔的字符串（如 '1,2,3'）
     * @return int 影响的行数
     * @throws \Exception
     * @example
     * // 软删除单个
     * $affected = UserModel::softDeleteById(1);
     *
     * // 软删除多个
     * $affected = UserModel::softDeleteById('1,2,3');
     *
     * // 只有启用了软删除的模型才能使用
     * // 需要在模型中设置 protected $deleteTime = 'deleted';
     */
    public static function softDeleteById($ids)
    {
        $instance = new static();
        if ($instance->deleteTime === false) {
            throw new \Exception('当前模型未开启软删除');
        }
        
        $ids = self::normalizeIds($ids);
        if (empty($ids)) {
            return false;
        }
        
        try {
            $pk = $instance->pk;
            $data = [$instance->deleteTime => time()];
            if ($instance->updateTime) {
                $data[$instance->updateTime] = date('Y-m-d H:i:s');
            }
            
            $db = Db::table($instance->table);
            if (count($ids) === 1) {
                $db->where($pk, $ids[0]);
            } else {
                $db->whereIn($pk, $ids);
            }
            $db->where($instance->deleteTime, $instance->defaultSoftDelete);
            
            return $db->update($data);
        } catch (\Exception $e) {
            throw new \Exception('软删除失败：' . $e->getMessage());
        }
    }

    /**
     * 条件软删除
     * @param array $where 条件
     * @return int 影响的行数
     * @throws \Exception
     * @example
     * // 软删除所有状态为 0 的用户
     * $affected = UserModel::softDeleteWhere(['status' => 0]);
     */
    public static function softDeleteWhere($where)
    {
        $instance = new static();
        if ($instance->deleteTime === false) {
            throw new \Exception('当前模型未开启软删除');
        }

        try {
            $data = [$instance->deleteTime => time()];
            if ($instance->updateTime) {
                $data[$instance->updateTime] = date('Y-m-d H:i:s');
            }

            $db = Db::table($instance->table);
            self::applyWhereConditions($db, $where);
            $db->where($instance->deleteTime, $instance->defaultSoftDelete);

            return $db->update($data);
        } catch (\Exception $e) {
            throw new \Exception('软删除失败：' . $e->getMessage());
        }
    }

    /**
     * 恢复软删除的数据
     * @param int|array|string $ids 主键值、数组 或 逗号分隔的字符串（如 '1,2,3'）
     * @return int 影响的行数
     * @throws \Exception
     * @example
     * // 恢复单个
     * $affected = DiaryModel::restoreData(1);
     *
     * // 恢复多个
     * $affected = DiaryModel::restoreData('1,2,3,4,5');
     *
     * // 从请求参数获取
     * $ids = $_POST['ids'] ?? '';
     * $affected = DiaryModel::restoreData($ids);
     */
    public static function restoreData($ids)
    {
        $instance = new static();
        if ($instance->deleteTime === false) {
            throw new \Exception('当前模型未开启软删除');
        }
        
        $ids = self::normalizeIds($ids);
        if (empty($ids)) {
            return false;
        }
        
        try {
            $pk = $instance->pk;
            $data = [$instance->deleteTime => $instance->defaultSoftDelete];
            if ($instance->updateTime) {
                $data[$instance->updateTime] = date('Y-m-d H:i:s');
            }
            
            $db = Db::table($instance->table);
            if (count($ids) === 1) {
                $db->where($pk, $ids[0]);
            } else {
                $db->whereIn($pk, $ids);
            }
            $db->where($instance->deleteTime, '>', $instance->defaultSoftDelete);
            
            return $db->update($data);
        } catch (\Exception $e) {
            throw new \Exception('恢复数据失败：' . $e->getMessage());
        }
    }

    // ─── 修改数据 ──────────────────────────────────────────────

    /**
     * 根据主键更新
     * @param int $id 主键
     * @param array $data 数据
     * @param bool $runValidate 是否验证
     * @return int 影响的行数
     * @throws \Exception
     * @example
     * // 更新用户信息
     * $affected = UserModel::updateById(1, ['name' => '新名字', 'age' => 20]);
     * echo '更新了 ' . $affected . ' 条记录';
     */
    public static function updateById($id, $data, $runValidate = true)
    {
        if ($runValidate && !static::validateData($data)) {
            $error = static::getValidateErrorMsg($data);
            throw new \Exception('数据验证失败：' . $error);
        }

        try {
            $instance = new static();
            if ($instance->autoWriteTimestamp && $instance->updateTime) {
                $data[$instance->updateTime] = date('Y-m-d H:i:s');
            }
            return Db::table($instance->table)->where($instance->pk, $id)->update($data);
        } catch (\Exception $e) {
            throw new \Exception('更新数据失败：' . $e->getMessage());
        }
    }

    /**
     * 条件更新
     * @param array $where 条件
     * @param array $data 数据
     * @param bool $runValidate 是否验证
     * @return int 影响的行数
     * @throws \Exception
     * @example
     * // 批量更新状态
     * $affected = UserModel::updateWhere(
     *     [['age', '<', 18]],
     *     ['status' => 0]
     * );
     */
    public static function updateWhere($where, $data, $runValidate = true)
    {
        if ($runValidate && !static::validateData($data)) {
            $error = static::getValidateErrorMsg($data);
            throw new \Exception('数据验证失败：' . $error);
        }

        try {
            $instance = new static();
            if ($instance->autoWriteTimestamp && $instance->updateTime) {
                $data[$instance->updateTime] = date('Y-m-d H:i:s');
            }

            $db = Db::table($instance->table);
            self::applyWhereConditions($db, $where);
            return $db->update($data);
        } catch (\Exception $e) {
            throw new \Exception('更新数据失败：' . $e->getMessage());
        }
    }

    /**
     * 字段自增
     * @param int $id 主键
     * @param string $field 字段名
     * @param int $step 步长
     * @return int 影响的行数
     * @throws \Exception
     * @example
     * // 用户访问量 +1
     * $affected = UserModel::increment(1, 'views', 1);
     *
     * // 积分 +10
     * $affected = UserModel::increment(1, 'score', 10);
     */
    public static function increment($id, $field, $step = 1)
    {
        try {
            $instance = new static();
            $sql = "UPDATE {$instance->table} SET {$field} = {$field} + {$step} WHERE {$instance->pk} = ?";
            return Db::execute($sql, [$id]);
        } catch (\Exception $e) {
            throw new \Exception('自增操作失败：' . $e->getMessage());
        }
    }

    /**
     * 字段自减
     * @param int $id 主键
     * @param string $field 字段名
     * @param int $step 步长
     * @return int 影响的行数
     * @throws \Exception
     * @example
     * // 库存 -1
     * $affected = ProductModel::decrement(1, 'stock', 1);
     *
     * // 积分 -5
     * $affected = UserModel::decrement(1, 'score', 5);
     */
    public static function decrement($id, $field, $step = 1)
    {
        try {
            $instance = new static();
            $sql = "UPDATE {$instance->table} SET {$field} = {$field} - {$step} WHERE {$instance->pk} = ?";
            return Db::execute($sql, [$id]);
        } catch (\Exception $e) {
            throw new \Exception('自减操作失败：' . $e->getMessage());
        }
    }

    /**
     * 条件字段自增
     * @param array $where 条件
     * @param string $field 字段名
     * @param int $step 步长
     * @return int 影响的行数
     * @throws \Exception
     * @example
     * // 所有状态为 1 的用户积分 +10
     * $affected = UserModel::incrementWhere(['status' => 1], 'score', 10);
     */
    public static function incrementWhere($where, $field, $step = 1)
    {
        try {
            $instance = new static();
            $db = Db::table($instance->table);
            self::applyWhereConditions($db, $where);

            // 构建 WHERE 条件字符串和参数
            $whereSql = '';
            $params = [];
            if (!empty($db->where)) {
                $conditions = [];
                foreach ($db->where as $index => $item) {
                    $fieldName = $item[0];
                    $operator = $item[1];
                    $value = $item[2] ?? '';

                    if (in_array($operator, ['IN', 'NOT IN'])) {
                        $conditions[] = "{$fieldName} {$operator} {$value}";
                    } elseif (in_array($operator, ['IS NULL', 'IS NOT NULL'])) {
                        $conditions[] = "{$fieldName} {$operator}";
                    } elseif ($operator === 'BETWEEN') {
                        $conditions[] = "{$fieldName} {$operator} {$value}";
                    } else {
                        $paramKey = ":where_{$index}";
                        $conditions[] = "{$fieldName} {$operator} {$paramKey}";
                        $params[$paramKey] = $value;
                    }
                }

                foreach ($db->whereParams as $key => $val) {
                    $params[$key] = $val;
                }

                $whereSql = " WHERE " . implode(' AND ', $conditions);
            }

            $sql = "UPDATE {$instance->table} SET {$field} = {$field} + {$step}{$whereSql}";

            return $db->execute($sql, $params);
        } catch (\Exception $e) {
            throw new \Exception('自增操作失败：' . $e->getMessage());
        }
    }

    /**
     * 条件字段自减
     * @param array $where 条件
     * @param string $field 字段名
     * @param int $step 步长
     * @return int 影响的行数
     * @throws \Exception
     * @example
     * // 所有状态为 0 的用户积分 -5
     * $affected = UserModel::decrementWhere(['status' => 0], 'score', 5);
     */
    public static function decrementWhere($where, $field, $step = 1)
    {
        try {
            $instance = new static();
            $db = Db::table($instance->table);
            self::applyWhereConditions($db, $where);

            // 构建 WHERE 条件字符串和参数
            $whereSql = '';
            $params = [];
            if (!empty($db->where)) {
                $conditions = [];
                foreach ($db->where as $index => $item) {
                    $fieldName = $item[0];
                    $operator = $item[1];
                    $value = $item[2] ?? '';

                    if (in_array($operator, ['IN', 'NOT IN'])) {
                        $conditions[] = "{$fieldName} {$operator} {$value}";
                    } elseif (in_array($operator, ['IS NULL', 'IS NOT NULL'])) {
                        $conditions[] = "{$fieldName} {$operator}";
                    } elseif ($operator === 'BETWEEN') {
                        $conditions[] = "{$fieldName} {$operator} {$value}";
                    } else {
                        $paramKey = ":where_{$index}";
                        $conditions[] = "{$fieldName} {$operator} {$paramKey}";
                        $params[$paramKey] = $value;
                    }
                }

                foreach ($db->whereParams as $key => $val) {
                    $params[$key] = $val;
                }

                $whereSql = " WHERE " . implode(' AND ', $conditions);
            }

            $sql = "UPDATE {$instance->table} SET {$field} = {$field} - {$step}{$whereSql}";

            return $db->execute($sql, $params);
        } catch (\Exception $e) {
            throw new \Exception('自减操作失败：' . $e->getMessage());
        }
    }

    // ─── 查询数据 ──────────────────────────────────────────────

    /**
     * 解析并应用 WHERE 条件到 Db 实例
     * @param Db $db Db 实例
     * @param array $where 条件数组
     * @example
     * // 支持多种格式
     * $where = ['status' => 1, ['age', '>', 18]];
     * // 或
     * $where = [['status', '=', 1], ['age', '>', 18]];
     */
    private static function applyWhereConditions($db, $where)
    {
        if (!is_array($where)) {
            return;
        }

        foreach ($where as $key => $value) {
            // 格式1: [0 => ['field', 'operator', 'value']] 索引数组
            if (is_int($key) && is_array($value) && count($value) === 3) {
                $db->where($value[0], $value[1], $value[2]);
            }
            // 格式2: ['field' => 'value'] 关联数组
            else if (!is_int($key)) {
                $db->where($key, $value);
            }
            // 格式3: 嵌套数组（可能是多个条件）
            else if (is_array($value)) {
                $allConditions = true;
                foreach ($value as $sub) {
                    if (!is_array($sub) || count($sub) !== 3) {
                        $allConditions = false;
                        break;
                    }
                }
                if ($allConditions) {
                    foreach ($value as $sub) {
                        $db->where($sub[0], $sub[1], $sub[2]);
                    }
                } else {
                    self::applyWhereConditions($db, $value);
                }
            }
        }
    }

    /**
     * 根据主键查询单条
     * @param int $id 主键
     * @param string $fields 字段
     * @return static|null
     * @example
     * $user = UserModel::findById(1);
     * if ($user) {
     *     echo '用户名：' . $user->name;
     * }
     *
     * // 只查询部分字段
     * $user = UserModel::findById(1, 'id,name,email');
     */
    public static function findById($id, $fields = '*')
    {
        $instance = new static();
        $db = Db::table($instance->table)->field($fields)->where($instance->pk, $id);

        if ($instance->deleteTime !== false) {
            $db->where($instance->deleteTime, $instance->defaultSoftDelete);
        }

        $result = $db->find();
        if ($result) {
            $instance->data = $result;
            return $instance;
        }
        return null;
    }

    /**
     * 条件查询单条
     * @param array $where 条件
     * @param string $fields 字段
     * @param string $order 排序
     * @return static|null
     * @example
     * // 查询邮箱为 test@test.com 的用户
     * $user = UserModel::findOne(['email' => 'test@test.com']);
     *
     * // 查询年龄最大的用户
     * $user = UserModel::findOne([], '*', 'age DESC');
     *
     * // 查询状态为 1 且年龄大于 18 的用户
     * $user = UserModel::findOne([
     *     'status' => 1,
     *     ['age', '>', 18]
     * ]);
     */
    public static function findOne($where, $fields = '*', $order = '')
    {
        $instance = new static();
        $db = Db::table($instance->table)->field($fields);

        if ($instance->deleteTime !== false) {
            $db->where($instance->deleteTime, $instance->defaultSoftDelete);
        }

        self::applyWhereConditions($db, $where);

        if ($order) {
            $db->order($order);
        }

        $result = $db->find();
        if ($result) {
            $instance->data = $result;
            return $instance;
        }
        return null;
    }

    /**
     * 条件查询多条
     * @param array $where 条件
     * @param string $fields 字段
     * @param string $order 排序
     * @param int $limit 限制条数
     * @return array
     * @example
     * // 查询所有状态为 1 的用户
     * $list = UserModel::findAll(['status' => 1]);
     *
     * // 查询年龄大于 18 的用户，按年龄倒序，取前 10 条
     * $list = UserModel::findAll(
     *     [['age', '>', 18]],
     *     'id,name,age',
     *     'age DESC',
     *     10
     * );
     */
    public static function findAll($where, $fields = '*', $order = '', $limit = 0)
    {
        $instance = new static();
        $db = Db::table($instance->table)->field($fields);

        if ($instance->deleteTime !== false) {
            $db->where($instance->deleteTime, $instance->defaultSoftDelete);
        }

        self::applyWhereConditions($db, $where);

        if ($order) {
            $db->order($order);
        }

        if ($limit > 0) {
            $db->limit($limit);
        }

        $results = $db->select();
        $list = [];
        foreach ($results as $data) {
            $m = new static();
            $m->data = $data;
            $list[] = $m->toArray();
        }
        return $list;
    }

    /**
     * 查询全部（支持条件）
     * @param array $where 条件
     * @param string $fields 字段
     * @param string $order 排序
     * @return array
     * @example
     * // 获取所有用户
     * $list = UserModel::getAllList();
     *
     * // 获取状态为 1 的用户
     * $list = UserModel::getAllList(['status' => 1]);
     *
     * // 获取状态为 1 的用户，按创建时间倒序
     * $list = UserModel::getAllList(['status' => 1], '*', 'create_time DESC');
     */
    public static function getAllList($where = [], $fields = '*', $order = 'id DESC')
    {
        return static::findAll($where, $fields, $order);
    }

    /**
     * 分页查询
     * @param array $where 条件
     * @param int $page 页码
     * @param int $limit 每页条数
     * @param string $fields 字段
     * @param string $order 排序
     * @return array
     * @example
     * // 获取第 2 页，每页 10 条
     * $result = UserModel::paginateList(['status' => 1], 2, 10);
     * echo '总记录数：' . $result['total'];
     * echo '当前页数据：' . print_r($result['data'], true);
     */
    public static function paginateList($where = [], $page = 1, $limit = 10, $fields = '*', $order = 'id DESC')
    {
        $instance = new static();
        $db = Db::table($instance->table)->field($fields);

        if ($instance->deleteTime !== false) {
            $db->where($instance->deleteTime, $instance->defaultSoftDelete);
        }

        self::applyWhereConditions($db, $where);

        if ($order) {
            $db->order($order);
        }

        return $db->paginate($page, $limit);
    }

    /**
     * 获取某个字段值
     * @param array $where 条件
     * @param string $field 字段
     * @return mixed|null
     * @example
     * // 获取用户名为 'admin' 的邮箱
     * $email = UserModel::fetchValue(['name' => 'admin'], 'email');
     *
     * // 获取最大年龄
     * $maxAge = UserModel::fetchValue([], 'MAX(age)');
     */
    public static function fetchValue($where, $field)
    {
        $instance = new static();
        $db = Db::table($instance->table);

        if ($instance->deleteTime !== false) {
            $db->where($instance->deleteTime, $instance->defaultSoftDelete);
        }

        self::applyWhereConditions($db, $where);

        $result = $db->field($field)->find();
        return $result ? $result[$field] : null;
    }

    /**
     * 获取一列数据
     * @param array $where 条件
     * @param string $field 值字段
     * @param string $key 键字段
     * @return array
     * @example
     * // 获取所有用户的 id => name 映射
     * $map = UserModel::fetchColumn(['status' => 1], 'name', 'id');
     * // 结果：['1' => '张三', '2' => '李四']
     */
    public static function fetchColumn($where, $field, $key = '')
    {
        $instance = new static();
        $db = Db::table($instance->table);

        if ($instance->deleteTime !== false) {
            $db->where($instance->deleteTime, $instance->defaultSoftDelete);
        }

        self::applyWhereConditions($db, $where);

        $results = $db->field($field . ($key ? ",{$key}" : ''))->select();
        $column = [];
        foreach ($results as $row) {
            if ($key && isset($row[$key])) {
                $column[$row[$key]] = $row[$field];
            } else {
                $column[] = $row[$field];
            }
        }
        return $column;
    }

    /**
     * 统计数量
     * @param array $where 条件
     * @return int
     * @example
     * // 统计总用户数
     * $count = UserModel::totalCount();
     *
     * // 统计状态为 1 的用户数
     * $count = UserModel::totalCount(['status' => 1]);
     */
    public static function totalCount($where = [])
    {
        $instance = new static();
        $db = Db::table($instance->table);

        if ($instance->deleteTime !== false) {
            $db->where($instance->deleteTime, $instance->defaultSoftDelete);
        }

        self::applyWhereConditions($db, $where);

        return $db->count();
    }

    /**
     * 求和
     * @param string $field 字段
     * @param array $where 条件
     * @return float
     * @example
     * // 计算所有用户的年龄总和
     * $sum = UserModel::sumField('age');
     *
     * // 计算状态为 1 的用户的积分总和
     * $sum = UserModel::sumField('score', ['status' => 1]);
     */
    public static function sumField($field, $where = [])
    {
        $instance = new static();
        $db = Db::table($instance->table);

        if ($instance->deleteTime !== false) {
            $db->where($instance->deleteTime, $instance->defaultSoftDelete);
        }

        self::applyWhereConditions($db, $where);

        return $db->sum($field);
    }

    /**
     * 求平均值
     * @param string $field 字段
     * @param array $where 条件
     * @return float
     * @example
     * // 计算所有用户的平均年龄
     * $avg = UserModel::avgField('age');
     *
     * // 计算状态为 1 的用户的平均积分
     * $avg = UserModel::avgField('score', ['status' => 1]);
     */
    public static function avgField($field, $where = [])
    {
        $instance = new static();
        $db = Db::table($instance->table);

        if ($instance->deleteTime !== false) {
            $db->where($instance->deleteTime, $instance->defaultSoftDelete);
        }

        self::applyWhereConditions($db, $where);

        return $db->avg($field);
    }

    /**
     * 最大值
     * @param string $field 字段
     * @param array $where 条件
     * @return mixed
     * @example
     * // 获取最大年龄
     * $max = UserModel::maxField('age');
     *
     * // 获取状态为 1 的用户中的最大积分
     * $max = UserModel::maxField('score', ['status' => 1]);
     */
    public static function maxField($field, $where = [])
    {
        $instance = new static();
        $db = Db::table($instance->table);

        if ($instance->deleteTime !== false) {
            $db->where($instance->deleteTime, $instance->defaultSoftDelete);
        }

        self::applyWhereConditions($db, $where);

        return $db->max($field);
    }

    /**
     * 最小值
     * @param string $field 字段
     * @param array $where 条件
     * @return mixed
     * @example
     * // 获取最小年龄
     * $min = UserModel::minField('age');
     *
     * // 获取状态为 1 的用户中的最小积分
     * $min = UserModel::minField('score', ['status' => 1]);
     */
    public static function minField($field, $where = [])
    {
        $instance = new static();
        $db = Db::table($instance->table);

        if ($instance->deleteTime !== false) {
            $db->where($instance->deleteTime, $instance->defaultSoftDelete);
        }

        self::applyWhereConditions($db, $where);

        return $db->min($field);
    }

    /**
     * 判断记录是否存在
     * @param array $where 条件
     * @return bool
     * @example
     * // 判断邮箱是否已被注册
     * if (UserModel::recordExists(['email' => 'test@test.com'])) {
     *     echo '邮箱已被注册';
     * }
     */
    public static function recordExists($where)
    {
        return static::totalCount($where) > 0;
    }

    /**
     * 批量获取数据（支持主键数组）
     * @param int|array|string $ids 主键值、数组 或 逗号分隔的字符串
     * @param string $fields 字段
     * @return array
     * @example
     * // 获取多个用户
     * $list = UserModel::findByIds([1, 2, 3]);
     *
     * // 使用逗号分隔字符串
     * $list = UserModel::findByIds('1,2,3,4,5');
     *
     * // 只查询部分字段
     * $list = UserModel::findByIds('1,2,3', 'id,name,email');
     */
    public static function findByIds($ids, $fields = '*')
    {
        $ids = self::normalizeIds($ids);
        if (empty($ids)) {
            return [];
        }

        $instance = new static();
        $db = Db::table($instance->table)->field($fields);
        if (count($ids) === 1) {
            $db->where($instance->pk, $ids[0]);
        } else {
            $db->whereIn($instance->pk, $ids);
        }

        if ($instance->deleteTime !== false) {
            $db->where($instance->deleteTime, $instance->defaultSoftDelete);
        }

        $results = $db->select();
        $list = [];
        foreach ($results as $data) {
            $m = new static();
            $m->data = $data;
            $list[] = $m->toArray();
        }
        return $list;
    }

    // ─── 验证 ──────────────────────────────────────────────────

    /**
     * 数据验证
     * @param array $data 数据
     * @return bool
     */
    protected static function validateData($data)
    {
        $instance = new static();
        $validateClass = $instance->validateClass;
        if (empty($validateClass) || !class_exists($validateClass)) {
            return true;
        }

        try {
            $validator = new $validateClass();
            if (method_exists($validator, 'check')) {
                return $validator->check($data);
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 获取验证错误信息
     * @param array $data 数据
     * @return string
     */
    protected static function getValidateErrorMsg($data)
    {
        $instance = new static();
        $validateClass = $instance->validateClass;
        if (empty($validateClass) || !class_exists($validateClass)) {
            return '';
        }

        try {
            $validator = new $validateClass();
            if (method_exists($validator, 'check')) {
                $validator->check($data);
            }
            return '';
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }
}