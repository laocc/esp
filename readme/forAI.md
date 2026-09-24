# ESP 框架 · AI 速查手册

> 用途：给 AI（及新接手者）提供**写码时可直接依循的最小知识集**。
> 原则：只列结论与易错点，展开部分标注 `详见 xxx.md`。
> 所有结论均来自本仓库 `core/`、`help/`、`face/`、`common/` 源码，与代码冲突时**以代码为准**。

## 0. 一句话定位

PHP >= 8.1 的自研 MVC 框架 `laocc/esp`。结构：

```
虚拟机 _VIRTUAL > 模块 _MODULE > 控制器 Controller > 动作 Action > 视图 View
                                     └ 数据模型 Model（由 Controller 派生）
```

依赖：`laocc/helper`、`laocc/dbs`、`laocc/error`；扩展：redis、json、xml、mbstring、zlib、pdo。
详见 [0.aboutme.md](./0.aboutme.md)。

## 1. 目录与入口

```
application/{virtual}/controllers|models|views   ;directory 由 request.ini 指定，默认 /application
application/{virtual}/{module}/controllers|views ;模块与虚拟机结构相同
common/config/*.ini        ;配置，路径由 $option['config']['path'] 指定
common/routes/{virtual}.ini;路由表，default.ini 为通用
public/{virtual}/index.php ;web 入口
public/cli/index.php       ;cli 入口
runtime/                   ;临时目录，入库忽略
```

入口最小形态：

```php
$option = include_once(dirname(__DIR__) . '/option.php');
(new \esp\core\Dispatcher($option, 'www'))->run();     //web
(new \esp\core\Dispatcher($option, 'cli'))->simple();  //cli，或 ->run(true)
```

`Dispatcher::__construct(array $option, string $virtual)`，第二参数决定 `_VIRTUAL`。

## 2. 常量（写码时常用）

| 常量                        | 含义                         | 备注                                            |
|---------------------------|----------------------------|-----------------------------------------------|
| `_ROOT` `_RUNTIME`        | 根 / 临时目录                   | 可在 `new Dispatcher` 前手工 define                |
| `_VIRTUAL` `_MODULE`      | 虚拟机 / 模块                   | `_MODULE` 在路由完成后才定义                           |
| `_CLI` `_DEBUG` `_MASTER` | 是否 CLI / 是否开发 / 是否主服务器     | `runtime/debug.lock`、`runtime/master.lock` 控制 |
| `_DOMAIN` `_HOST`         | 完整域名 / 根域名                 | 三级域要在入口先 `define('_HOST', ...)`               |
| `_URI`                    | 路由依据（REQUEST_URI 的 path）   |                                               |
| `_CIP` `_URL` `_HTTPS`    | IP / 完整 URL / 是否 https     |                                               |
| `_UNIQUE_KEY`             | `md5(core/Dispatcher.php)` | **redis 中所有系统键的前缀**                           |

业务常量（`_PUBLISH_KEY`、`_TASK_KEY`、`_QUEUE_TABLE`、`_RPC`、`_CONFIG_LOAD`）应在 `option.php` 或 bootstrap 中定义。

## 3. 配置

- 文件：`common/config/*.ini`，支持 `.ini/.json/.php/.yaml`，键名与值统一转小写；
- 键名带 `.` 会自动展开成多维（最多 6 级）；值形如 `[a,b,c]` 自动转数组；
- 值中 `{_ROOT} {_RUNTIME} {_DOMAIN} {_HOST} {_NOW} {_DATE} {_TIME}` 会被替换；
- 合并顺序：`default` > `_VIRTUAL` > `_HOST` > `_DOMAIN`（**后者覆盖前者**）；
- 读取：`$this->config('database.redis.host')`，第 2 参传 `int/float/bool` 可转类型；
- 默认缓存到 redis 的 `{_UNIQUE_KEY}_CONFIG_`；`_DEBUG` 或 `_CONFIG_LOAD` 为真时每次从文件重载。

详见 [4.databases.md](./4.databases.md)（含 flush 的 level 位表）。

## 4. 路由

默认规则：URI 第 1 段在 `{directory}/{virtual}/` 下是目录 → 该段为 module，否则为 controller；随后是 action，余下为 params。
`/` → `index/index`。

路由表（文件内**按顺序匹配，命中即停**）：

```ini
[规则名]                    ;键名以 # 开头视为注释
path = /robot.txt          ;完全相等  ┐
uri = /debugs             ;前缀匹配  ├ 单条规则内检查顺序
like = /abc                ;包含     │
match = '#/(.+)\.txt$#i'    ;正则     ┘
method[] = get              ;get|post|ajax|all|cli
route[virtual|module|controller|action|directory|namespace] = 1   ;数字=取匹配结果第 n 项
map[] = 5                   ;位置参数（取匹配结果下标），非数字则原样传
map[name] = 2               ;命名参数（方法必须完全对应）
view[path|file|layout] =
return = ...                ;有此项时其余（除 method）全部失效
```

`return` 语义：`http(s)://`→301 跳转；`redis:KEY`→读 redis；`/` 或 `files:`→包含 `_ROOT` 内的文件；`{`→json；其它→纯文本（`\r`
`\n` 转换行）。`${n}` 会被替换。

要点：**没有命名参数时，实参会用 null 补齐到 10 个**。

详见 [5.routes.md](./5.routes.md)（含 URI 安全校验、alias、allow/disallow、缓存清理）。

## 5. 控制器

```php
namespace application\www\controllers;
class IndexController extends Controller   //必须继承 \esp\core\Controller
{
    public function indexAction() {}       //方法名 = strtolower(action) + 后缀
}
```

**方法名后缀**（`request.ini` 的 `suffix`）：

| 请求                  | 后缀     | 例           |
|---------------------|--------|-------------|
| 普通 GET              | `Get`  | `indexGet`  |
| POST（含 ajax 的 POST） | `Post` | `indexPost` |
| GET 方式的 ajax        | `Ajax` | `indexAjax` |
| CLI                 | `Cli`  | `indexCli`  |

找不到时降级：`indexGet` → `indexAction` → `defaultGet` → `defaultAction` → 报错。

**生命周期**：`_init{Ext}`/`_init` → `_main{Ext}`/`_main` → Action → `_close{Ext}`/`_close`。
`_init`/`_main` 返回**任何非 null 值（含 false）即中断**，不再执行 Action。

**返回值决定输出**（这是最容易写错的地方，务必 return 或明确不 return）：

| return            | 输出                        |
|-------------------|---------------------------|
| `null` / 不 return | 渲染视图（**视图文件必须存在**）        |
| `array`           | json，`?callback=` 时 jsonp |
| `string`          | 首字符 `<` → html，否则 text    |
| `int`             | 按状态码页                     |
| `bool`            | 不输出                       |
| 对象                | 有 `display()` 调它，否则转字符串   |

或用 `$this->json()/html()/text()/xml()/php()/image()/md()` 指定——**此时 Action 必须返回 null**。
`$this->html()` 不带参可清除前面的设置回到视图模式。

⚠️ **ajax 请求且返回 null 时，不渲染任何视图**（`Response::display()` 直接 return）。纯 ajax 接口请返回数组。

详见 [1.controllers.md](./1.controllers.md)（含全部可用方法表）、[7.response.md](./7.response.md)。

## 6. 视图

- 目录 `{directory}/{virtual}[/{module}]/views`，文件名 `{controller}/{action}.php`；`.php` 不存在时自动试 `.phtml`；
- 改目录：`$this->setViewPath()`（`@`=绝对，`/`=相对 `_ROOT`）或 `response.ini` 的 `views`；改文件：`$this->setView('index/list')`；
- layout 查找：`{views}/layout.php` → `{子视图所在目录}/layout.php` → `{views 上级}/layout.php`，**三步都没有会报错**；关闭用`setLayout(false)`；
- layout 中用 `$_view_html` 输出子视图；
- layout 固定变量 **7 个**：`_title` `_meta` `_css` `_js_head` `_js_body` `_js_foot` `_js_defer`；关闭 layout 时它们进子视图；
- 视图内可直接写原生 PHP（`extract($value, EXTR_SKIP); include $file;`）。

送变量优先级：`$this->assign()` > `$this->getView()->assign()`（与顺序无关）。
⚠️ 正确方法名是 `getView()` / `getLayout()` / `getAdapter()`，**没有 `view()` / `layout()` / `adapter()`**。

详见 [2.views.md](./2.views.md)、[9.adapter.md](./9.adapter.md)。

## 7. Model / 数据库

```
esp\core\Controller → esp\core\Library（工作类基类）→ esp\dbs\DbModel（数据模型基类）
```

- Model 一般继承 `esp\dbs\DbModel` 或项目 `_BaseModel`，放 `/models`；纯逻辑工作类可直接继承 `esp\core\Library`；
- **无需手工传控制器**：构造时用 `debug_backtrace()` 向上找 Controller；
- 但在 **CLI / swoole tick·task 回调中创建时必须把 `$this` 作第一个参数**：`new UserModel($this, ...$args)`，否则报`Library中无法获取Controller`；
- 类中定义 `_init` `_main` 会被构造时自动调用，`_close` 析构时调用；
- `$this->_redis` 直接用；`$this->getPool()` 取 `esp\dbs\Pool`；
- MySQL 具体操作（查询构造、事务）由 `laocc/dbs` 提供，见其自身文档。

**where 组合规则**：键名为字段 → and；整型下标单元内，键值对之间 or，列表元素之间 and。

```php
$where['expUserID'] = 1;                                  //and
$where[] = ['expUserID' => 1, 'expSignUserID' => 1];      //这两项 or
$where[] = [['expCreateTime>' => time()],                 //and
            [['expSupplement>' => 0, 'expPackingFee>' => 0], 'expSupID' => 0]]; //内 or，外 and
```

详见 [3.models.md](./3.models.md)、[4.databases.md](./4.databases.md)。

## 8. 缓存

`cache.ini`：`run`、`ttl`（**默认 -1，必须显式设置才生效**）、`medium`(file|redis)、`compress`(位: 1空行 2空格 4注释 8标签间空格
16合并一行)、`isolation`(0|1host|2domain)、`path[cache]`、`path[static]`、`static[...]`(正则)、`params[]`(参与 key 的 GET参数)。

- 读取在分发前，命中则不执行控制器；保存在客户端断开后；
- 只缓存含 `<html>...</html>` 的输出；`ttl>=5` 才写动态缓存，1~4 只生成静态；
- 控制器中 `$this->cache(false)` 可临时禁用**保存**；
- URL 带 `?_CACHE_DISABLE=1` 或定义 `_CACHE_DISABLE` 时完全跳过；
- 静态文件名 = URI，需 nginx `location ^~ /xxx/ { root ...; }` 配合。

详见 [10.cache.md](./10.cache.md)。

## 9. Cookies / Session

- `$this->_cookies->get/set/del/disable`，键名统一小写，固定 `path=/ httponly samesite=Lax`；
- `set()` 的 ttl 支持 `'30d' '12h' '1y' '1m' '1w'` 或时间戳；**不传 ttl 时取 `time()-1`（立即过期）**，务必显式传；
- `domain = host` → 写在根域（子域共享）；
- **Session 只有在 `cookies.run = true` 时才创建**；`driver` 支持 files/redis；
- `$this->cid()` 取客户端唯一标识，需启用 Cookies。

详见 [8.cookies.md](./8.cookies.md)。

## 10. 插件与 bootstrap

```php
$dis = new \esp\core\Dispatcher($option, 'www');
$dis->bootstrap(\library\Bootstrap::class);   //执行类中所有 _init* 方法，任一个返回 false 则终止 run
$dis->setPlugin(new \library\Plugs());        //注册插件，名为类名去命名空间后首字母大写
$dis->run();
```

插件继承 `esp\core\Plugin`，可实现的 HOOK（按触发顺序）：

| # | 方法 | 时机 | 返回值 |
|---|---|---|---|
| 1 | `router(Request, Response)` | 路由**之前** | 非 null → 直接 display 并结束 |
| 2 | `dispatch(Request, Response)` | 路由之后、控制器**之前**（缓存命中则不进） | 非 null → 直接 display 并结束 |
| 3 | `display(Request, Response, &$value)` | 控制器之后、输出之前 | 非 null → 直接 display 并结束（跳过 finish） |
| 4 | `finish(Request, Response, &$value)` | 输出之后、`fastcgi_finish_request` 之前 | 忽略 |
| 5 | `shutdown(Request, Response, &$value)` | `end:` 处，`register_shutdown_function` 之前 | 忽略 |
| 6 | `end(Request, Response, &$value)` | `run()` 最末尾 | 忽略 |

⚠️ 一个易踩的坑：`run(true)` 与 `simple()`（即 CLI）下所有 hook 都被 `!$simple` 挡住，**一个都不执行**。

（已修复：多插件时所有插件会依次调用，任一个返回非 null 才中断；`end` 也已保证一定执行。）

详见 [11.plugs.md](./11.plugs.md)。

## 11. CLI

入口 `public/cli/index.php`，框架自带 `esp.sh` 可在项目任意目录调用：

```
esp -h
esp -s config [key] [toJson]   ;查看 config
esp -s flush [level] [safe]    ;level: 1config 2mysqlCache 4resource 8hash 32route 256清库 1024清全部(需 safe=flushAll)
esp -s resource                ;重置资源版本号
esp -s model [path] [base]     ;按表生成 Model，默认 /models 与 _BaseModel
esp -s tables                  ;显示所有表
esp -s table [table] [key]     ;打印表结构
```

CLI 下以 `-` 开头的 controller 走框架内置 `esp\help\Helps`（如 `-s` → `Helps::flush()`）。
定义了 `_RPC` 且非主服务器时，`-s` 命令会被拒绝。

## 12. 异步

| 方法                             | 介质                 | 备注                                                                        |
|--------------------------------|--------------------|---------------------------------------------------------------------------|
| `publish($action, $msg)`       | redis 管道           | 频道取 `_PUBLISH_KEY`（默认 `REDIS_ORDER`）；`$action` 含 `.` 时点号前为频道名；**CLI 不可用** |
| `queue($action, $data)`        | redis 队列           | 键取 `_QUEUE_TABLE`（默认 `REDIS_QUEUE`）；web 环境易堵塞，慎用                          |
| `task($key, $args, $after)`    | redis 管道           | `$key` 的 `->`/`::` 转 `.`，含 `.` 拆为 class+action；`_taskPlan_` 是保留词          |
| `async($key, $args, $runTime)` | `{_RUNTIME}/async` | 仅单机；`$runTime < 1e9` 视为"多少秒后"，否则为时间戳                                      |
| `shutdown($callable, ...$p)`   | 同步                 | 客户端断开后执行；**CLI 下立即同步执行**                                                  |

读取：`asyncIterator(callable $fun, bool $unlink)`——回调 `($key, $args, $file)`，返回 `=== true` 才删文件（建议靠返回值控制，不要一律
`$unlink=true`）。

详见 [22.task.md](./22.task.md)、[23.async.md](./23.async.md)。

## 13. 并发锁

```php
$val = $this->locked('orderPay', fn($id) => ..., $id);
if (is_string($val)) { /* 'locked' / 'locked: Running' / 'locked error' / 锁名非法 */ }
```

- 锁名 1-50 字符，仅 `[\w\-\.]`；
- 后缀决定实现：`redis` 结尾→redis 锁；`go` 结尾→`/tmp/locked_pipe` 的 go 服务；否则文件锁 `/tmp/flock_{key}.flock`；
- **锁名首字符是数字时被当作 option 位**：`1`=非阻塞，`2`/`4`=延长等待（文件锁时 `2` 还表示结束后不删锁文件），`8`=redis 释放用
  lua 校验；
- 出错返回字符串 ⇒ **锁内业务不要返回字符串**。

## 14. 调试

- `runtime/debug.lock` 存在即 `_DEBUG`；还需 `debug.ini` 的 `run = true`；
- `$this->debug($data)` / `error()` / `debug_mysql()` / `debug()->folder()`；
- 保存时机：`mode='cgi'` 或 `?_debug=1` 立即保存，否则 `register_shutdown_function` 结束时保存；`mode='none'` 不保存；
- `$this->ignoreError(__FILE__, __LINE__ + 1)` 屏蔽下一行错误；
- `$option` 中含 `timer` 键会启用 `esp\debug\Timer` 分阶段打点。

详见 [20.debug.md](./20.debug.md)。

## 15. 群集

- 主服务器 `runtime/master.lock`，只有主服务器从文件加载 config 并写入 redis；
- 从服务器读不到时向主服务器请求 `http://{host}/_esp_config_awaken_`（UA `espConfigAwaken`）唤醒重载；
- 需定义 `_RPC`（字符串 IP 或 `['host','port','ip']`），并为此建一个 nginx 虚拟机；
- `esp\core\Cluster` 是空类，逻辑在 `Configure` 中。

详见 [12.cluster.md](./12.cluster.md)。

## 16. AI 写码清单（Checklist）

1. 控制器类继承 `esp\core\Controller`，命名空间 `\application\{virtual}[\{module}]\controllers`；
2. 方法名 = `小写动作名 + Action/Post/Ajax/Cli`，不确定就用 `Action`；
3. 输出内容要么 `return array`（json），要么 return null 走视图——**不要想在 ajax 里 return null 渲染视图**；
4. 送视图变量用 `$this->assign()`；拿视图对象用 `getView()`；
5. 新页面必须存在对应的视图文件，否则报错终止；
6. Model 在 CLI/回调里创建要传 `$this` 作第一参；
7. Cookie `set()` 一定传 ttl；
8. 锁内业务不要 return 字符串；
9. 改了 config 或路由表不生效 → 清缓存（debug 环境自动，生产环境 `esp -s flush`）；
10. 插件类建议写在命名空间里；注意 CLI 下插件钩子不执行（第 10 节）。

## 17. 源码级已知问题汇总

| # | 位置                                                  | 问题                                                                                                    |
|---|-----------------------------------------------------|-------------------------------------------------------------------------------------------------------|
| 1 | `Dispatcher::plugsHook()`                           | 所有钩子都被 `!$simple` 挡着，`run(true)`/`simple()`（CLI 入口）下**一个都不会执行**                                          |
| 2 | `Router::forceCache()` vs `Configure::forceCache()` | `_flush_key` 判定方向相反（路由是"不一致才刷新"）                                                                        |
| 3 | `Cookies::set()`                                    | 不传 ttl 时取 `time() - 1`，等于立即过期                                                                              |
| 4 | `Dispatcher` / `Configure`                          | Dispatcher 给 config 补的默认键是 `driver`，而 Configure 读的是 `type`，写 `['driver'=>'file']` 无效                        |
| 5 | `esp\core\Cluster`                                  | 空类，群集逻辑实际由 Configure 的 RPC 唤醒完成                                                                              |

> 2026-09-24 已修复：`setPlugin()` 的 `strrpos` 砍首字母、`PlugFace` 接口旧方法名、`end` 钩子被 debug 的 `return` 跳过、
> `plugsHook()` 只执行第一个插件——这四项均已改掉，不必再规避。

## 18. 文档索引

| 文档                                     | 内容                                            |
|----------------------------------------|-----------------------------------------------|
| [0.aboutme.md](./0.aboutme.md)         | 安装、入口、目录结构、常量、`_MODULE`、`_HOST`               |
| [1.controllers.md](./1.controllers.md) | 控制器命名、生命周期、全部可用方法                             |
| [2.views.md](./2.views.md)             | 视图目录/文件名、layout 查找与固定变量、md、ajax               |
| [3.models.md](./3.models.md)           | Model/Library 继承关系、创建、CLI 生成                  |
| [4.databases.md](./4.databases.md)     | database.ini、redis 系统键、flush level 表、where 组合 |
| [5.routes.md](./5.routes.md)           | 路由表全部字段、URI 校验、alias、缓存                       |
| [6.request.md](./6.request.md)         | request.ini、Request 属性与方法、客户端判断               |
| [7.response.md](./7.response.md)       | 8 种输出方式、返回语义、title/js/css、Resources           |
| [8.cookies.md](./8.cookies.md)         | cookies.ini、session.ini、用法                    |
| [9.adapter.md](./9.adapter.md)         | 标签解析器接口、两种注册方式、变量传递                           |
| [10.cache.md](./10.cache.md)           | cache.ini 全项、工作过程、静态 HTML、多域名                 |
| [11.plugs.md](./11.plugs.md)           | bootstrap、Plugin HOOK                         |
| [12.cluster.md](./12.cluster.md)       | 主从配置、唤醒机制、nginx                               |
| [20.debug.md](./20.debug.md)           | debug.ini、记录方法、保存时机                           |
| [22.task.md](./22.task.md)             | publish/queue/task/shutdown、swoole 示例         |
| [23.async.md](./23.async.md)           | 文件方式异步任务                                      |
