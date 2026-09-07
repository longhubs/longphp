# 🐉 LongPHP Framework

> 轻量级 PHP 框架 | 简单 · 高效 · 安全 | 龙行天下

[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D7.1-blue)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)
[![GitHub stars](https://img.shields.io/github/stars/longhubs/longphp)](https://github.com/longhubs/longphp/stargazers)
[![GitHub forks](https://img.shields.io/github/forks/longhubs/longphp)](https://github.com/longhubs/longphp/network)

LongPHP 是一个功能完整的轻量级 PHP 框架，致力于提供简单、高效、安全的 Web 开发体验。

---

## ✨ 核心特性

- 🚀 轻量高效 - 核心代码精简，性能优异，开箱即用
- 📦 功能完整 - 路由、ORM、模板引擎、缓存、JWT、CLI 全支持
- 🔒 安全可靠 - 内置 CSRF 防护、加密 Cookie、JWT 认证
- 🛠️ 开发友好 - 代码生成器、调试工具、丰富的命令行支持
- 📝 Blade 模板 - 集成 Laravel 风格的 BladeOne 模板引擎
- 🔄 数据库特性 - 支持事务、悲观锁(FOR UPDATE)、共享锁(LOCK IN SHARE MODE)

---

## 📦 环境要求

- PHP >= 7.1
- Composer
- MySQL / PostgreSQL / SQLite (PDO 扩展)
- Redis (可选)

---

## 🚀 安装

通过 Composer 创建项目:

composer create-project longphp/longphp my-project
cd my-project

配置环境:

cp .env.example .env

编辑 .env 文件配置数据库信息:

DB_HOST=127.0.0.1
DB_DATABASE=longphp
DB_USERNAME=root
DB_PASSWORD=

生成密钥并启动:

php long key:generate
chmod -R 755 storage/ runlogs/
php long server

访问 http://localhost:8081

---

## 📁 目录结构

my-project/
├── app/
│   ├── controller/         控制器
│   ├── model/              模型
│   ├── service/            业务逻辑层
│   ├── middleware/         中间件
│   ├── validate/           验证器
│   └── views/              视图模板
├── config/                 配置文件
├── public/                 公共入口
├── route/                  路由定义
├── runlogs/                日志目录
├── storage/                存储目录
├── vendor/                 Composer 依赖
├── long                    CLI 命令行入口
└── .env                    环境变量

---

## 🛣️ 路由定义

use Long\Route;

// 基本路由
Route::get('/', 'IndexController@index');
Route::get('/user/{id}', 'UserController@show');
Route::post('/user/login', 'UserController@login');

// 路由分组 + 中间件
Route::group('/admin', function() {
    Route::get('/users', 'AdminController@users');
    Route::get('/posts', 'AdminController@posts');
}, ['middleware' => ['AuthMiddleware']]);

// 资源路由
Route::resource('/posts', 'PostController');

// 404 处理
Route::miss(function() {
    return error('页面不存在', 404);
});

---

## 🎮 控制器

app/controller/UserController.php

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
        if (!$user) return error('用户不存在', 404);
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

依赖注入示例:

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

---

## 📦 模型

创建模型:

php long make:model User

基础模型 app/model/UserModel.php:

namespace app\model;

use Long\Model;

class UserModel extends Model
{
    protected $table = 'users';
    protected $pk = 'id';
    protected $autoWriteTimestamp = true;
    protected $deleteTime = 'deleted';
}

常用方法:

// 查询
$user = UserModel::findById(1);
$list = UserModel::getAllList(['status' => 1]);
$list = UserModel::where('status', 1)->order('id DESC')->limit(10)->select();

// 新增
$id = UserModel::addGetId(['name' => '张三', 'email' => 'test@test.com']);

// 更新
UserModel::updateById(1, ['name' => '李四']);

// 删除
UserModel::deleteById(1);
UserModel::softDeleteById(1);
UserModel::restoreData(1);

链式查询:

$list = UserModel::where('status', 1)
    ->where('age', '>', 18)
    ->whereIn('id', '1,2,3,4,5')
    ->orWhere('status', 0)
    ->order('create_time DESC')
    ->page(1, 10)
    ->select();

---

## 🗄️ 数据库查询构造器

use Long\Db;

// 查询
$list = Db::table('users')
    ->where('status', 1)
    ->where('age', '>', 18)
    ->order('id DESC')
    ->limit(10)
    ->select();

// 单条
$user = Db::table('users')->where('id', 1)->find();

// 统计
$count = Db::table('users')->where('status', 1)->count();

// 插入
$id = Db::table('users')->insert(['name' => '张三']);

// 更新
Db::table('users')->where('id', 1)->update(['name' => '李四']);

// 删除
Db::table('users')->where('id', 1)->delete();

高级查询:

// IN 查询
Db::table('users')->whereIn('id', '1,2,3,4,5')->select();

// LIKE 模糊查询
Db::table('users')->whereLike('name', '%张三%')->select();

// FIND_IN_SET
Db::table('products')->whereFindInSet('tags', 'php')->select();

// BETWEEN 区间查询
Db::table('users')->whereBetween('age', 18, 30)->select();

// 原生 SQL 条件
Db::table('users')->whereRaw('age > ? AND status = ?', [18, 1])->select();

// JOIN 关联查询
Db::table('users as u')
    ->join('profiles p', 'u.id = p.user_id')
    ->where('u.status', 1)
    ->select();

// 分页查询
$result = Db::table('users')->paginate(1, 15);

// 原生查询
$list = Db::query("SELECT * FROM users WHERE status = ?", [1]);
Db::execute("UPDATE users SET status = ? WHERE id = ?", [1, 1]);

事务与行锁:

use Long\Db;

try {
    Db::beginTransaction();

    // 排他锁 FOR UPDATE
    $user = Db::table('users')
        ->where('id', 1)
        ->lockForUpdate()
        ->find();

    // 共享锁 LOCK IN SHARE MODE
    $profile = Db::table('profiles')
        ->where('user_id', 1)
        ->sharedLock()
        ->find();

    Db::commit();
} catch (Exception $e) {
    Db::rollback();
}

查看 SQL 日志:

use Long\Db;

Db::clearLogs();
$list = Db::table('users')->select();
echo Db::getLastFullSql();

---

## 📝 模板引擎

控制器中渲染视图:

return view('user.profile', ['user' => $user]);

Blade 模板示例 app/views/user/profile.blade.php:

@extends('layouts.app')

@section('title', '用户资料')

@section('content')
    <h1>{{ $user['name'] }}</h1>
    <p>邮箱：{{ $user['email'] }}</p>

    @auth
        <p>欢迎回来</p>
    @endauth

    @guest
        <a href="{{ url('login') }}">请登录</a>
    @endguest
@endsection

内置 Blade 指令:

@datetime($timestamp)     格式化时间
@config('app.name')       获取配置
@auth / @endauth          登录判断
@guest / @endguest        未登录判断
@url('user/index')        生成 URL

---

## 🔐 JWT 认证

use Long\Token;

$token = new Token();

// 生成 Token
$pair = $token->generatePair([
    'user_id' => 1,
    'username' => 'admin'
]);

// 验证 Token
$result = $token->verify($accessToken);
if ($result['success']) {
    $user = $result['data'];
}

// 刷新 Token
$newPair = $token->refresh($refreshToken);

// 获取当前用户
$user = $token->currentUser();
$userId = $token->currentUserId();

助手函数:

$pair = set_token(['user_id' => 1]);
$result = verify_token($token);
$new = refresh_token($refreshToken);
$token = header_token();
$user = token_user();
$userId = token_user_id();

---

## 📤 文件上传

use Long\File;

$file = File::upload('avatar');

$result = $file->allowTypes(['jpg', 'png'])
    ->maxSize(2 * 1024 * 1024)
    ->saveImage('uploads/avatars', [
        'thumb' => ['width' => 100, 'height' => 100]
    ]);

if ($result) {
    echo '上传成功';
} else {
    echo $file->getError();
}

---

## 🖥️ 命令行工具

开发服务器:

php long server          启动服务器(默认 8081 端口)
php long server 8080     指定端口

代码生成器:

php long make:controller UserController     生成控制器
php long make:model User                    生成模型
php long make:middleware Auth               生成中间件
php long make:validate UserValidate         生成验证器

定时任务:

php long list       列出所有任务
php long run        执行到期任务
php long work       常驻运行模式(调试)
php long start      后台启动调度器(生产)
php long stop       停止调度器
php long status     查看状态

---

## 🔧 助手函数

调试:
dd($data)           打印并退出
dump($data)         打印不退出
p($data)            美化打印
logs('信息', 'info')  记录日志

JSON 响应:
success('获取成功', $data)    成功响应
error('参数错误', 400)        错误响应
json($data, 200)             自定义 JSON
api_result(0, '成功', $data)  API 响应

请求/输入:
input('id', 0)      获取参数
request()           请求对象
I('post.name')      ThinkPHP 风格

缓存:
cache('key', 'value', 3600)           设置缓存
$value = cache('key')                 获取缓存
remember('key', function(){}, 3600)   缓存回调

数据库快捷操作:
db('users')->where('id', 1)->find()
M('users')->where('id', 1)->find()
D('User')->where('id', 1)->find()

其他:
get_ip()             获取客户端 IP
uuid()               生成 UUID
order_no('ORD')      生成订单号
is_mobile()          判断移动端
current_url()        获取当前 URL
redirect('/home')    重定向
hash_password('123456')  密码加密
rand_code(6)         生成验证码

---

## ❓ 常见问题

如何开启调试模式？
config/app.php 中设置 'debug' => true

如何查看 SQL 日志？
use Long\Db;
Db::clearLogs();
$list = Db::table('users')->select();
echo Db::getLastFullSql();

如何定义 404 页面？
Route::miss(function() {
    return view('errors.404');
});

如何运行定时任务？
* * * * * cd /path/to/project && php long run >> runlogs/logs/cron.log 2>&1

---

## 📄 开源协议

MIT License

---

## 🤝 贡献

欢迎提交 Issue 和 Pull Request！

---

## 📬 联系

GitHub: https://github.com/longhubs/longphp

---

🐉 龙行天下，LongPHP 伴你同行！