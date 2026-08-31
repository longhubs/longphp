# LongPHP Framework 开发说明

> 轻量级 PHP 框架 | 简单 · 高效 · 安全 | 龙行天下 🐉

## 目录

1. 框架概述
2. 安装与环境配置
3. 项目目录结构
4. 路由系统
5. 控制器
6. 模型与数据库
7. 数据库查询构造器（Db）
8. 模板引擎
9. JWT 认证
10. 文件上传
11. 命令行工具
12. 助手函数（Helper）
13. Session 与 Cookie
14. 常见问题


## 1. 框架概述

LongPHP 是一个功能完整的轻量级 PHP 框架，致力于提供简单、高效、安全的 Web 开发体验。

### 核心特性

- 轻量高效 - 核心代码简洁，性能优异
- 功能完整 - 路由、ORM、模板、认证、缓存、CLI 全支持
- 安全可靠 - CSRF、加密 Cookie、JWT 认证
- 开发友好 - 代码生成器、调试工具、命令行支持
- Blade 模板 - 集成 Laravel 风格 Blade 模板引擎

### 环境要求

- PHP >= 7.4
- Composer
- MySQL / PostgreSQL / SQLite


## 2. 安装与环境配置

### 2.1 创建项目

composer create-project longphp/longphp my-project
cd my-project

### 2.2 配置环境变量

cp .env.example .env

编辑 .env 文件，配置数据库连接信息：

DB_HOST=127.0.0.1
DB_DATABASE=longphp
DB_USERNAME=root
DB_PASSWORD=

### 2.3 生成应用密钥

php long key:generate

### 2.4 设置目录权限

chmod -R 755 storage/ runlogs/

### 2.5 启动开发服务器

php long server

访问 http://localhost:8081


## 3. 项目目录结构

my-project/
├── app/
│   ├── controller/              控制器
│   │   ├── IndexController.php
│   │   └── UserController.php
│   ├── model/                   模型
│   │   ├── BaseModel.php
│   │   └── UserModel.php
│   ├── service/                 业务逻辑层
│   │   └── UserService.php
│   ├── middleware/              中间件
│   │   └── AuthMiddleware.php
│   ├── validate/                验证器
│   │   └── UserValidate.php
│   └── views/                   视图模板
│       ├── layouts/
│       │   └── app.blade.php
│       └── user/
│           └── profile.blade.php
├── config/                      配置文件
│   ├── app.php                  应用配置
│   ├── database.php             数据库配置
│   ├── middleware.php           中间件配置
│   └── provider.php             服务提供者配置
├── public/                      公共入口目录
│   ├── index.php                入口文件
│   ├── .htaccess
│   └── uploads/                 上传文件目录
├── route/                       路由定义
│   ├── web.php                  Web 路由
│   └── api.php                  API 路由
├── runlogs/                     运行时日志目录
│   └── logs/                    日志文件
├── storage/                     存储目录
│   ├── keys/                    密钥文件
│   └── uploads/                 文件存储
├── vendor/                      Composer 依赖
├── long                         CLI 命令行入口
├── .env                         环境变量
└── composer.json


## 4. 路由系统

路由定义在 route/web.php 和 route/api.php 文件中。

### 4.1 基本路由

use Long\Route;

// GET 请求
Route::get('/', 'IndexController@index');
Route::get('/user/{id}', 'UserController@show');

// POST 请求
Route::post('/user/login', 'UserController@login');

// PUT 请求
Route::put('/user/{id}', 'UserController@update');

// DELETE 请求
Route::delete('/user/{id}', 'UserController@delete');

// 支持所有请求方法
Route::any('/test', 'TestController@index');

// 闭包路由
Route::get('/hello', function() {
    return 'Hello LongPHP!';
});

### 4.2 路由参数

// 必填参数
Route::get('/user/{id}', function($id) {
    return "User ID: {$id}";
});

// 带正则约束
Route::get('/user/{id}', 'UserController@show')->where('id', '\d+');

### 4.3 路由分组

Route::group('/admin', function() {
    Route::get('/users', 'AdminController@users');
    Route::get('/posts', 'AdminController@posts');
}, ['middleware' => ['app\\middleware\\AuthMiddleware']]);

### 4.4 资源路由

Route::resource('/posts', 'PostController');

自动生成：

GET    /posts          -> index
GET    /posts/{id}     -> show
POST   /posts          -> store
PUT    /posts/{id}     -> update
DELETE /posts/{id}     -> delete

### 4.5 404 处理

Route::miss(function() {
    return error('页面不存在', 404);
});


## 5. 控制器

控制器存放在 app/controller/ 目录下。

### 5.1 基础控制器

<?php
// app/controller/UserController.php
namespace app\controller;

use Long\Controller;
use app\model\UserModel;

class UserController extends Controller
{
    public function index()
    {
        $users = UserModel::getAllList(['status' => 1]);
        return success('获取成功', $users);
    }

    public function show($id)
    {
        $user = UserModel::findById($id);
        if (!$user) {
            return error('用户不存在', 404);
        }
        return success('获取成功', $user->toArray());
    }

    public function create()
    {
        $data = input();
        $id = UserModel::addGetId($data);
        return success('创建成功', ['id' => $id]);
    }

    public function update($id)
    {
        $data = input();
        UserModel::updateById($id, $data);
        return success('更新成功');
    }

    public function delete($id)
    {
        UserModel::deleteById($id);
        return success('删除成功');
    }
}

### 5.2 依赖注入

<?php
// app/controller/UserController.php
namespace app\controller;

use Long\Controller;
use app\service\UserService;

class UserController extends Controller
{
    protected $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function index()
    {
        return success('获取成功', $this->userService->getList());
    }
}

### 5.3 方法参数注入

use Long\Request;

public function profile(Request $request)
{
    $userId = $request->get('user_id');
    // ...
}


## 6. 模型与数据库

### 6.1 创建模型

php long make:model User

### 6.2 基础模型

<?php
// app/model/UserModel.php
namespace app\model;

use Long\Model;

class UserModel extends Model
{
    // 表名（默认根据类名自动生成）
    protected $table = 'users';

    // 主键
    protected $pk = 'id';

    // 自动写入时间戳
    protected $autoWriteTimestamp = true;
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';

    // 软删除
    protected $deleteTime = 'deleted';
    protected $defaultSoftDelete = 0;

    // 验证器
    protected $validateClass = 'app\\validate\\UserValidate';

    // 允许批量赋值的字段
    protected $field = ['username', 'email', 'password', 'status'];
}

### 6.3 查询方法

// 根据主键查询
$user = UserModel::findById(1);
$user = UserModel::findById(1, 'id,name,email');

// 条件查询单条
$user = UserModel::findOne(['status' => 1]);
$user = UserModel::findOne(['status' => 1], '*', 'id DESC');

// 条件查询多条
$list = UserModel::findAll(['status' => 1]);
$list = UserModel::findAll(['status' => 1], 'id,name', 'id DESC', 10);

// 获取全部
$list = UserModel::getAllList(['status' => 1]);
$list = UserModel::getAllList(['status' => 1], '*', 'id DESC');

// 批量获取
$list = UserModel::findByIds([1, 2, 3]);
$list = UserModel::findByIds('1,2,3,4,5');

// 分页
$result = UserModel::paginateList(['status' => 1], 1, 15);

// 获取字段值
$email = UserModel::fetchValue(['id' => 1], 'email');

// 获取一列数据
$list = UserModel::fetchColumn(['status' => 1], 'name', 'id');
// 返回 ['1' => '张三', '2' => '李四']

### 6.4 新增方法

// 新增返回模型对象
$user = UserModel::addData(['name' => '张三', 'email' => 'test@test.com']);

// 新增返回主键ID
$id = UserModel::addGetId(['name' => '张三']);

// 批量新增
$results = UserModel::addBatch([
    ['name' => '张三'],
    ['name' => '李四']
]);

// 批量新增返回所有ID
$ids = UserModel::addBatchGetIds([
    ['name' => '张三'],
    ['name' => '李四']
]);

// 获取或创建
$user = UserModel::firstOrNewData(
    ['email' => 'test@test.com'],
    ['name' => '测试用户']
);
$id = UserModel::firstOrNewGetId(
    ['email' => 'test@test.com'],
    ['name' => '测试用户']
);

### 6.5 更新方法

// 根据主键更新
UserModel::updateById(1, ['name' => '李四']);

// 条件更新
UserModel::updateWhere(['status' => 0], ['status' => 1]);

// 字段自增
UserModel::increment(1, 'views', 1);

// 字段自减
UserModel::decrement(1, 'stock', 1);

// 条件自增
UserModel::incrementWhere(['status' => 1], 'score', 10);

// 条件自减
UserModel::decrementWhere(['status' => 0], 'score', 5);

### 6.6 删除方法

// 真删除（支持单个、数组、逗号分隔字符串）
UserModel::deleteById(1);
UserModel::deleteById([1, 2, 3]);
UserModel::deleteById('1,2,3,4,5');

// 条件真删除
UserModel::deleteWhere(['status' => 0]);

// 软删除
UserModel::softDeleteById(1);
UserModel::softDeleteById('1,2,3,4,5');

// 条件软删除
UserModel::softDeleteWhere(['status' => 0]);

// 恢复
UserModel::restoreData(1);
UserModel::restoreData('1,2,3,4,5');

### 6.7 统计方法

$count = UserModel::totalCount(['status' => 1]);
$sum = UserModel::sumField('age', ['status' => 1]);
$avg = UserModel::avgField('age', ['status' => 1]);
$max = UserModel::maxField('age', ['status' => 1]);
$min = UserModel::minField('age', ['status' => 1]);

if (UserModel::recordExists(['email' => 'test@test.com'])) {
    // 已存在
}

### 6.8 链式查询

$list = UserModel::where('status', 1)
    ->where('age', '>', 18)
    ->whereIn('id', '1,2,3,4,5')
    ->orWhere('status', 0)
    ->order('create_time DESC')
    ->page(1, 10)
    ->select();


## 7. 数据库查询构造器（Db）

### 7.1 基本查询

use Long\Db;

// 查询多条
$list = Db::table('users')
    ->where('status', 1)
    ->where('age', '>', 18)
    ->order('id DESC')
    ->limit(10)
    ->select();

// 查询单条
$user = Db::table('users')->where('id', 1)->find();

// 统计
$count = Db::table('users')->where('status', 1)->count();

// 求和/平均/最大/最小
$sum = Db::table('users')->sum('age');
$avg = Db::table('users')->avg('age');
$max = Db::table('users')->max('age');
$min = Db::table('users')->min('age');

// 插入
$id = Db::table('users')->insert(['name' => '张三']);

// 批量插入
Db::table('users')->insertAll([
    ['name' => '张三'],
    ['name' => '李四']
]);

// 更新
Db::table('users')->where('id', 1)->update(['name' => '李四']);

// 删除
Db::table('users')->where('id', 1)->delete();

### 7.2 WHERE 条件

// 基础 WHERE
Db::table('users')->where('status', 1)->select();

// 多条件
Db::table('users')
    ->where('status', 1)
    ->where('age', '>', 18)
    ->select();

// 数组方式
Db::table('users')->where([
    'status' => 1,
    ['age', '>', 18]
])->select();

// OR 条件
Db::table('users')
    ->where('status', 1)
    ->orWhere('status', 2)
    ->select();

// 多个 OR 条件
Db::table('users')
    ->where('status', 1)
    ->orWhere('age', '<', 18)
    ->orWhere('age', '>', 60)
    ->select();

### 7.3 IN / NOT IN 查询

// 数组方式
Db::table('users')->whereIn('id', [1,2,3,4,5])->select();

// 逗号分隔字符串（自动转数组）
Db::table('users')->whereIn('id', '1,2,3,4,5')->select();

// NOT IN
Db::table('users')->whereNotIn('id', '1,2,3')->select();

### 7.4 LIKE 模糊查询

Db::table('users')->whereLike('name', '%张三%')->select();
Db::table('users')->whereLike('name', '张%')->select();

// OR LIKE
Db::table('users')
    ->whereLike('name', '%张%')
    ->orWhereLike('name', '%李%')
    ->select();

### 7.5 FIND_IN_SET 查询（逗号分隔字段）

// 查询 tags 字段中包含 'php' 的记录
Db::table('products')->whereFindInSet('tags', 'php')->select();

// NOT FIND_IN_SET
Db::table('products')->whereNotFindInSet('tags', 'php')->select();

// OR FIND_IN_SET
Db::table('products')
    ->whereFindInSet('tags', 'php')
    ->orWhereFindInSet('tags', 'mysql')
    ->select();

### 7.6 BETWEEN 区间查询

Db::table('users')->whereBetween('age', 18, 30)->select();

// OR BETWEEN
Db::table('users')
    ->whereBetween('age', 18, 30)
    ->orWhereBetween('age', 50, 60)
    ->select();

### 7.7 NULL 查询

Db::table('users')->whereNull('deleted_at')->select();
Db::table('users')->whereNotNull('name')->select();

### 7.8 原生 SQL 条件

// 简单原生条件
Db::table('users')->whereRaw('status = 1')->select();

// 带参数绑定
Db::table('users')->whereRaw('age > ? AND status = ?', [18, 1])->select();

// 命名参数
Db::table('users')->whereRaw('age > :age AND status = :status', [
    ':age' => 18,
    ':status' => 1
])->select();

// OR 原生条件
Db::table('users')
    ->where('status', 1)
    ->orWhereRaw('age > 60')
    ->select();

### 7.9 JOIN 关联查询

// INNER JOIN
Db::table('users as u')
    ->join('profiles p', 'u.id = p.user_id')
    ->where('u.status', 1)
    ->select();

// LEFT JOIN
Db::table('users as u')
    ->leftJoin('profiles p', 'u.id = p.user_id')
    ->select();

// 多表 JOIN
Db::table('users as u')
    ->join('profiles p', 'u.id = p.user_id')
    ->join('orders o', 'u.id = o.user_id')
    ->where('u.status', 1)
    ->select();

### 7.10 排序与分页

// 单字段排序
Db::table('users')->order('id', 'DESC')->select();

// 多字段排序（数组）
Db::table('users')->order(['sort' => 'DESC', 'id' => 'DESC'])->select();

// 多字段排序（字符串）
Db::table('users')->order('sort DESC, id DESC')->select();

// 链式多次调用
Db::table('users')
    ->order('sort', 'DESC')
    ->order('id', 'DESC')
    ->select();

// LIMIT
Db::table('users')->limit(10)->select();
Db::table('users')->limit(0, 10)->select();

// 分页辅助
Db::table('users')->page(1, 15)->select();

// 完整分页（含总数）
$result = Db::table('users')->paginate(1, 15);
// 返回: ['data' => [...], 'total' => 100, 'page' => 1, 'limit' => 15, 'last_page' => 7, 'has_more' => true]

### 7.11 字段选择

// 指定字段
Db::table('users')->field('id,name,email')->select();

// 数组方式
Db::table('users')->field(['id', 'name', 'email'])->select();

// 别名
Db::table('users')->field('id, name as username, age')->select();

// 别名 + 关联表
Db::table('users as u')
    ->field('u.id, u.name, p.phone')
    ->join('profiles p', 'u.id = p.user_id')
    ->select();

### 7.12 聚合函数

// COUNT
$count = Db::table('users')->count();
$count = Db::table('users')->where('status', 1)->count('id');

// SUM
$sum = Db::table('users')->sum('age');
$sum = Db::table('users')->where('status', 1)->sum('score');

// AVG
$avg = Db::table('users')->avg('age');

// MAX
$max = Db::table('users')->max('age');

// MIN
$min = Db::table('users')->min('age');

### 7.13 原生查询

// 查询（返回结果集）
$list = Db::query("SELECT * FROM users WHERE status = ?", [1]);

// 写入（返回影响行数）
$affected = Db::execute("UPDATE users SET status = ? WHERE id = ?", [1, 1]);

### 7.14 查看 SQL 日志

use Long\Db;

Db::clearLogs();
$list = Db::table('users')->where('status', 1)->select();

// 查看原始 SQL（带占位符）
$rawLogs = Db::getLogs();

// 查看完整 SQL（参数已替换）
$fullLogs = Db::getFullLogs();
$lastSql = Db::getLastFullSql();

echo $lastSql;


## 8. 模板引擎

LongPHP 集成了 BladeOne 模板引擎（Laravel Blade 风格）。

### 8.1 基础使用

// 控制器中渲染模板
return view('user.profile', ['user' => $user]);

### 8.2 模板文件

模板文件存放在 app/views/ 目录下，使用 .blade.php 扩展名。

<!-- app/views/user/profile.blade.php -->
@extends('layouts.app')

@section('title', '用户资料')

@section('content')
    <h1>{{ $user['name'] }}</h1>
    <p>邮箱：{{ $user['email'] }}</p>
    <p>注册时间：@datetime($user['create_time'])</p>

    @auth
        <p>欢迎回来，{{ auth()['name'] }}</p>
    @endauth

    @guest
        <a href="{{ url('login') }}">请登录</a>
    @endguest

    @if($user['age'] >= 18)
        <p>已成年</p>
    @else
        <p>未成年</p>
    @endif

    @foreach($user['tags'] as $tag)
        <span class="tag">{{ $tag }}</span>
    @endforeach
@endsection

### 8.3 布局模板

<!-- app/views/layouts/app.blade.php -->
<!DOCTYPE html>
<html>
<head>
    <title>@yield('title', 'LongPHP')</title>
</head>
<body>
    <div class="container">
        @yield('content')
    </div>
</body>
</html>

### 8.4 内置 Blade 指令

@datetime($timestamp)     // 格式化时间
@config('app.name')       // 获取配置
@auth                     // 登录判断
@endauth
@guest                    // 未登录判断
@endguest
@url('user/index')        // 生成 URL


## 9. JWT 认证

### 9.1 配置

在 config/app.php 中配置：

'token' => [
    'secret_key' => 'your-secret-key',
    'algorithm' => 'HS256',
    'access_ttl' => 3600,
    'refresh_ttl' => 604800,
    'issuer' => 'longphp-api',
],

### 9.2 基础使用

use Long\Token;

$token = new Token();

// 生成 Token
$pair = $token->generatePair([
    'user_id' => 1,
    'username' => 'admin',
    'role' => 'admin'
]);

// 验证 Token
$result = $token->verify($accessToken);
if ($result['success']) {
    $user = $result['data'];
} else {
    echo $result['error'];
}

// 刷新 Token
$newPair = $token->refresh($refreshToken);

// 获取当前用户
$user = $token->currentUser();
$userId = $token->currentUserId();

// 从请求头获取 Token
$tokenString = $token->extractFromRequest();

### 9.3 助手函数

// 生成 Token
$pair = set_token(['user_id' => 1]);

// 验证 Token
$result = verify_token($token);

// 刷新 Token
$new = refresh_token($refreshToken);

// 从请求头获取 Token
$token = header_token();

// 获取当前用户
$user = token_user();
$userId = token_user_id();

### 9.4 中间件认证

<?php
// app/middleware/AuthMiddleware.php
namespace app\middleware;

class AuthMiddleware
{
    public function handle($request, $next)
    {
        $token = header_token();
        if (!$token) {
            return error('请先登录', 401);
        }

        $result = verify_token($token);
        if (!$result['success']) {
            return error($result['error'], 401);
        }

        $request->setUser($result['data']);
        return $next($request);
    }
}


## 10. 文件上传

### 10.1 基础上传

use Long\File;

// 获取上传文件
$file = File::upload('avatar');
if (!$file) {
    echo '没有文件上传';
}

// 保存文件
$result = $file->save('uploads/files');
if ($result) {
    echo '保存成功：' . $result['name'];
} else {
    echo $file->getError();
}

### 10.2 图片上传（含缩略图）

use Long\File;

$file = File::upload('avatar');

$result = $file->allowTypes(['jpg', 'jpeg', 'png', 'gif', 'webp'])
    ->maxSize(2 * 1024 * 1024)
    ->saveImage('uploads/avatars', [
        'thumb' => ['width' => 100, 'height' => 100],
        'medium' => ['width' => 300, 'height' => 300]
    ]);

if ($result) {
    echo '原图：' . $result['name'];
    echo '缩略图：' . $result['thumbs']['thumb']['name'];
    echo '中图：' . $result['thumbs']['medium']['name'];
} else {
    echo $file->getError();
}

### 10.3 批量上传

use Long\File;

$files = File::all();
foreach ($files as $file) {
    $result = $file->allowTypes(['jpg', 'png'])
        ->maxSize(5 * 1024 * 1024)
        ->save('uploads/images');

    if ($result) {
        echo '上传成功：' . $result['name'];
    } else {
        echo '上传失败：' . $file->getError();
    }
}

### 10.4 验证方法

use Long\File;

$file = File::upload('avatar');
$file->allowTypes(['jpg', 'png'])
     ->maxSize(2 * 1024 * 1024);

if ($file->validate()) {
    // 验证通过
} else {
    echo $file->getError();
}


## 11. 命令行工具

### 11.1 开发服务器

php long server          # 启动服务器（默认 8081 端口）
php long server 8080     # 指定端口

### 11.2 代码生成器

php long make:controller UserController      # 生成控制器
php long make:model User                     # 生成模型
php long make:middleware Auth                # 生成中间件
php long make:validate UserValidate          # 生成验证器

### 11.3 定时任务

php long list        # 列出所有任务
php long run         # 执行到期任务
php long work        # 常驻运行模式（调试）
php long start       # 后台启动调度器（生产）
php long stop        # 停止调度器
php long status      # 查看状态

### 11.4 帮助

php long help


## 12. 助手函数（Helper）

### 12.1 事件系统

// 触发事件
event('user.login', ['user_id' => 1, 'username' => '张三']);

// 注册事件监听器
listen('user.login', function($payload) {
    logs('用户登录：' . $payload['username'], 'info');
});

listen('user.login', 'App\Listener\UserListener@onLogin', 10);

### 12.2 服务容器

// 获取容器实例
$container = app();

// 解析服务
$userService = app('UserService');

// 绑定服务
app()->bind('Interface', 'Impl');
app()->singleton('Cache', function() {
    return new Cache();
});

### 12.3 调试函数

dd($data);          // 打印并退出
dump($data);        // 打印不退出
p($data);           // 美化打印
logs('信息', 'info'); // 记录日志
trace('信息', 'sql'); // logs 别名

### 12.4 JSON 响应

// 成功响应
success('获取成功', $data);
// 返回：{"code":0,"msg":"获取成功","data":{...}}

// 错误响应
error('参数错误', 400);
// 返回：{"code":400,"msg":"参数错误","data":null}

// 自定义 JSON
json($data, 200);

// API 响应
api_result(0, '成功', $data);

### 12.5 请求和输入

// 获取参数（自动从 GET/POST/JSON 查找）
$id = input('id', 0);

// 获取所有参数
$all = input();

// 获取请求对象
$request = request();
$userId = $request->get('user_id');
$name = $request->post('name');

// ThinkPHP 风格
I('get.id', 0);      // 从 GET 获取
I('post.name', '');  // 从 POST 获取
I('id', 0);          // 自动查找

### 12.6 数据库快捷操作

// 快捷查询
db('users')->where('id', 1)->find();

// ThinkPHP 风格
M('users')->where('id', 1)->find();
D('User')->where('id', 1)->find();

### 12.7 配置和环境

// 获取环境变量
$debug = env('APP_DEBUG', false);

// 获取配置
$name = config('app.name');
$debug = config('app.debug', false);
$all = config(); // 获取所有配置

// ThinkPHP 风格
$debug = C('app.debug');

### 12.8 缓存操作

// 设置缓存（秒）
cache('user_info_1', $user, 3600);

// 获取缓存
$user = cache('user_info_1');

// 缓存回调结果
$users = remember('user_list', function() {
    return db('users')->select();
}, 600);

// 清空所有缓存
cache_clear();

// 删除单个缓存
cache()->delete('user_info_1');

### 12.9 字符串操作

// 生成随机字符串
$token = str_random(32);

// URL 友好格式
$slug = str_slug('Hello World!'); // hello-world

// 截断文本
$short = str_limit('这是一段很长的文字', 10, '...');

// XSS 过滤
$safe = xss_clean('<script>alert(1)</script>');

// 获取指定字符前/后部分
$before = str_before('user@example.com', '@'); // user
$after = str_after('user@example.com', '@');  // example.com

// 驼峰转下划线
$snake = str_snake('UserModel'); // user_model

// 下划线转驼峰
$camel = str_camel('user_model'); // UserModel
$camel = str_camel('user_model', false); // userModel

### 12.10 数组操作

// 只保留指定字段
$result = array_only($user, ['id', 'name', 'email']);

// 排除指定字段
$result = array_except($user, ['password', 'token']);

// 点号获取嵌套值
$name = data_get($user, 'profile.name', '默认值');

// 点号设置嵌套值
data_set($data, 'user.profile.name', '张三');

// 按字段分组
$grouped = array_group($list, 'status');

// 提取字段值列表
$names = array_pluck($list, 'name');
$rows = array_pluck($list, ['id', 'name']);

// 按字段排序
array_sort($list, 'age', 'ASC');

// 按字段去重
$unique = array_unique_by($list, 'name');

// 按字段索引
$indexed = array_index_by($list, 'id');

// 按字段统计
$counts = array_count_by($list, 'status'); // [1 => 10, 2 => 5]

// 按字段分组求和
$sums = array_sum_by($list, 'user_id', 'amount');

// 按字段分组求平均
$avgs = array_avg_by($list, 'class', 'score');

// 数组转 JSON
$json = array_to_json($list);

### 12.11 时间操作

// 当前时间
echo now(); // 2026-08-31 14:30:00
echo now('Y-m-d'); // 2026-08-31

// 相对时间
echo time_ago(time() - 180); // 3分钟前
echo time_ago(time() - 7200, true); // 2h前

// 今天开始/结束时间戳
$todayStart = today_start();
$todayEnd = today_end();

// 计算天数差
$days = days_between('2026-01-01', '2026-01-10'); // 9

### 12.12 重定向

// 页面重定向
redirect('/home');
redirect('/login', 301);

### 12.13 CSRF 防护

// 生成 CSRF Token
$token = csrf_token();

// 生成 CSRF 隐藏域 HTML
echo csrf_field();
// 输出: <input type="hidden" name="_csrf" value="xxx" />

// 从请求中获取 CSRF Token
$token = get_csrf_token();

### 12.14 常用工具

// 获取客户端 IP
$ip = get_ip();

// 获取浏览器信息
$info = get_browser_info(); // ['browser' => 'Chrome', 'platform' => 'Windows 10', 'user_agent' => '...']

// 判断移动端
if (is_mobile()) {
    // 移动端访问
}

// 判断 HTTPS
if (is_https()) {
    // HTTPS 请求
}

// 获取当前 URL
$url = current_url();

// 生成 UUID
$id = uuid(); // 550e8400-e29b-41d4-a716-446655440000

// 生成订单号
$orderNo = order_no('ORD'); // ORD202608311430001234

// 生成验证码
$code = rand_code(6); // 482936

// 密码加密
$hash = hash_password('123456');

// 验证密码
if (verify_password('123456', $hash)) {
    // 密码正确
}

// 获取来源域名
$domain = referer_domain();

// 过滤符号（保留中文、英文、数字、日期分隔符）
$str = filterSymbolsKeepDate('Hello 世界 2026-01-01!@#'); // Hello世界2026-01-01

### 12.15 Cookie 操作

// 设置（秒）
set_cookie('user_id', 123, 3600);
cookie('user_id', 123, 3600);

// 获取
$id = get_cookie('user_id');
$id = cookie('user_id');

// 删除
delete_cookie('user_id');
cookie('user_id', null);

// 检查是否存在
if (has_cookie('user_id')) {
    // 存在
}

// 获取所有 Cookie
$all = all_cookie();

// 清空所有 Cookie
clear_cookie();

### 12.16 HTTP 远程请求

// GET 请求
$result = http_get('https://api.example.com/user/1');
// 返回: ['code' => 200, 'body' => '...', 'error' => null]

// POST 请求（表单）
$result = http_post('https://api.example.com/user', ['name' => '张三']);

// POST 请求（JSON）
$result = http_post_json('https://api.example.com/user', ['name' => '张三']);

// PUT 请求
$result = http_put('https://api.example.com/user/1', ['name' => '李四']);

// DELETE 请求
$result = http_delete('https://api.example.com/user/1');

// 下载文件
$result = http_download('https://example.com/file.zip', '/path/to/save/file.zip');

// 通用请求
$result = http_request('PATCH', 'https://api.example.com/user/1', ['status' => 1]);

### 12.17 集合操作

// 创建集合
$users = collect($data)
    ->where('status', 1)
    ->pluck('name')
    ->all();

// 集合链式操作
$result = collect($data)
    ->whereGt('age', 18)
    ->sortBy('age', 'DESC')
    ->take(10)
    ->toArray();

### 12.18 Session 操作

// 设置
session('user_id', 123);

// 获取
$id = session('user_id');

// 删除
session('user_id', null);

// 检查是否存在
if (session_has('user_id')) {
    // 存在
}

// 删除单个
session_delete('user_id');

// 清空
session_clear();

// 销毁
session_destroy();

// 闪存（一次性）
flash('success', '操作成功');
$msg = flash('success'); // 获取并删除

if (flash_has('success')) {
    // 存在闪存
}

### 12.19 控制器信息

// 获取当前请求的方法名
$action = action();
$actionFull = action(true);

// 获取当前请求的控制器名
$controller = controller();
$controllerFull = controller(true);

### 12.20 控制器快捷操作

// 实例化控制器
$user = A('User');
$result = A('User')->index();

// 实例化模型
$user = D('User');        // 业务层
$user = M('user');        // 基础层

// Session 快捷操作
S('user_id', 123);        // 设置
$id = S('user_id');       // 获取

// 语言变量
L('welcome', '欢迎');      // 设置
echo L('welcome');        // 获取

// 抛出异常
E('用户不存在', 404);

// 生成 URL
$url = U('user/index');
$url = U('user/detail', ['id' => 1]);
$url = U('user/index', [], true); // 完整 URL

### 12.21 Token 认证

// 生成 Token
$pair = set_token(['user_id' => 1]);

// 验证 Token
$result = verify_token($token);

// 刷新 Token
try {
    $new = refresh_token($refreshToken);
} catch (Exception $e) {
    echo '刷新失败：' . $e->getMessage();
}

// 从请求头获取 Token
$token = header_token();

// 获取当前用户
$user = token_user();
$userId = token_user_id();


## 13. Session 与 Cookie

### 13.1 Session 操作

// 设置
session('user_id', 123);

// 获取
$id = session('user_id');

// 删除
session('user_id', null);

// 检查是否存在
if (session_has('user_id')) {
    // 存在
}

// 闪存（一次性）
flash('success', '操作成功');
$msg = flash('success');

// 清空
session_clear();

// 销毁
session_destroy();

### 13.2 Cookie 操作

// 设置（秒）
set_cookie('user_id', 123, 3600);

// 获取
$id = get_cookie('user_id');

// 删除
delete_cookie('user_id');

// 检查是否存在
if (has_cookie('user_id')) {
    // 存在
}


## 14. 常见问题

### 14.1 如何开启调试模式？

// config/app.php
'debug' => true,
'enable_sql_log' => true,

### 14.2 如何查看 SQL 日志？

use Long\Db;

Db::clearLogs();
$list = Db::table('users')->select();
echo Db::getLastFullSql();

### 14.3 如何定义 404 页面？

// route/web.php
Route::miss(function() {
    return view('errors.404');
});

### 14.4 如何运行定时任务？

# 添加系统 Cron（每分钟执行）
* * * * * cd /path/to/project && php long run >> runlogs/logs/cron.log 2>&1

### 14.5 如何生成应用密钥？

php long key:generate

### 14.6 如何处理跨域请求？

在 config/middleware.php 中添加跨域中间件，或在控制器中添加响应头：

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

### 14.7 如何获取当前用户信息？

// 从 Token 获取
$user = token_user();
$userId = token_user_id();

// 从请求对象获取（中间件注入后）
$user = request()->getUser();
$userId = request()->getUserId();

### 14.8 如何记录日志？

logs('用户登录成功', 'info');
logs('数据库查询失败', 'error');
logs('SQL执行', 'sql');
trace('调试信息', 'debug');


## 开源协议

MIT License


## 联系

- GitHub: https://github.com/longhubs/longphp


**🐉 龙行天下，LongPHP 伴你同行！**