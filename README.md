
# ESP (Efficient Simple PHP)
- PHP >5.6
- 这是一个比较高效且简洁的PHP框架。本框架的部分结构思路参考YAF框架（http://yaf.laruence.com/）。

# 安装
- 目前处于测试版阶段，下载()解压即可使用。
- 网站核心文件加载机制为利用composer的autoload，另外程序所需要的一些插件也依赖于composer，所以关于composer部分，请参看`composer.md`文件。
- 下载了本系统源码，且也安装好composer后，在根目录中运行`composer install`。
- Nginx（配置文件见nginx.conf），Apache，IIS下均可正常运行，PHP要求5.6以上，建议7.0以上。

# 文件结构：
```
├── application     网站业务程序部分
│   ├── admin       admin模块
│   │   ├── controllers     控制器中心    
│   │   ├── models          数据模数中心
│   │   └── views           视图中心
│   └── www         www模块
├── config          系统定义
├── core            *系统核心程序
├── extend          网站自定义增加的扩展程序          
├── helper          辅助程序
├── library         *系统自带扩展程序
├── plugins         系统插件
├── public      
│   ├── admin       admin子站入口
│   └── www         www子站入口
└── vendor          composer创建的文件
```

# 程序说明：
- 程序结构为` 模块 > 控制器(模型) > 动作 > 视图 `，数据模型、视图，均由控制器派生出来，具体后面详述；
- 程序可以有任意多个模块，一般情况下一个模块对应一个子站，路由中可以任意指定模块，在控制器内也可以在`模块>控制器>动作`间切换；
- 网站入口`/public/www`，其中`www`为对应的模块名，如果有多个子站，则建一个`/public/admin/index.php`即可。
- 关于文件名大小写：所有路径均为小写，表示为一个类的文件名须为首字母大写，如：`Article.php`，其他文件均为小写。虽然在win系统中大小写不敏感，但建议也严格按此约定命名。

index.php:
```
<?php

#当前子站的模块名
#须知晓：关于模块只取自此值，至于前面的/public/www中的www和系统运行无关，为了便于管理，建议取和模块名相同的路径名称。
define("_MODULE", 'www');       

define("_ROOT", realpath(__DIR__ . '/../../') . '/');
if (!@include __DIR__ . "/../../vendor/autoload.php") exit('请先运行[composer install]');


#调用系统核心部分
(new esp\core\Kernel())
    ->bootstrap()
    ->shutdown()
    ->run();
```

## bootstrap
bootstrap用于在程序正式执行之前所运行的程序。
该程序文件位于`/helper/Bootstrap.php`，文件中只有ootstrap类，其中可以添加任意多个执行函数，所有以`_init`开头的函数都会被顺序执行。

在bootstrap中可以执行一些系统准备工作，也可以注册一些系统插件。每个函数都有默认参数`Kernel`对象。
```
<?php
use \esp\core\Kernel;
use \esp\plugins\Smarty;

final class Bootstrap
{
    public function _initSmarty(Kernel $kernel)
    {
        $kernel->setPlugin('smarty', new Smarty());
    }
}
```

## shutdown
shutdown用于在程序完全执行结束后运行的程序，即便在程序中`exit()`shutdown也会在exit之后执行。
对于一些与程序没有直接关系的部分，可以放在这里面执行，如记录日志等。
```
function shutdown(Kernel $kernel)
{
    if (!$kernel->shutdown()) return;
    
    //在这加要执行的内容
    
}
```
`Kernel->shutdown()`是一个双重任务的函数，第一次运行执行注册shutdown方法，第二次以后返回是否执行已注册的shutdown，在控制器中用`$this->shutdown(false)`可关闭shutdown。

bootstrap()和shutdown()，都需要在`run()`之前调用，但不是必须要运行。

## Core中各类函数 

下表中函数的参数，`[$filename]`表示可有缺省值，调用时可以不用指定，没加方括号的都不可省略，

|函数|概要|
|---|---|
|`Kernel::class`|Kernel是esp框架系统核心调用中心，只可以在网站入口处进行实例化，下面前三个函数在入口入运行，后三个参数可在以程序中获取到kernel对象实例后调用
|`Kernel->bootstrap()`|启动bootstrap，对应`/helper/Bootstrap.php`文件
|`Kernel->shutdown()`|第一次运行注册shutdown，第二次以后为返回是否需要继续执行shutdown，对应`/helper/shutdown.php`文件
|`Kernel->run()`|系统起动，只能在网站入口处调用，前两个同样
|`Kernel->setPlugin(string $name,object $obj)`|注册插件，一般运行在bootstrap中
|`Kernel->getRequest()`|返回request实例
|`Kernel->getResponse()`|返回response实例
||
|`Config::class`|config类可以在整个系统中任何处调用，该类对应数据取自`/config/config.php`，编辑该文件须注意键名中不能含符号
|`Config::get(string $keys)`|返回一个config值，可以用`Config::get('esp.directory')`的方式读取多维数组的子键
|`Config::has(string $keys)`|判断一个config键是否存在，$key方式同上
|`Config::set(string $key,$value)`|设置一个config键值，注意：这儿$key只能是根键，不可以象get中的那样用
|`Config::mime(string $type)`|返回一个mime类型值，如`Config::mime('gif');`=`image/gif`
|`Config::states(int $code)`|返回一个网页状态码的描述，如`Config::states(304)`=`Not Modified`
||
|`Controller::class`|控制器基类，所有网站实际控制器必须继承此类，网站`controllers`路径下可以含有一个`Base.php`含有`BaseController`类<br>继承顺序：`IndexController extends BaseController extends esp\core\Controller`<br>以下所有方法均指在网站业务控制器中直接以`$this->getRequest();`方式调用
|`Controller::class`|






