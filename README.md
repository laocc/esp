
# Efficient Simple PHP
- PHP >5.6
- 这是一个比较高效且简洁的PHP框架。本框架的结构思想取自YAF框架(http://yaf.laruence.com/)，用纯PHP实现，当然和YAF没可比性。

# 安装
- 目前处于测试版阶段，下载()解压即可使用。
- 网站核心文件加载机制为利用composer的autoload，另外程序所需要的一些插件也依赖于composer，所以关于composer部分，请参看`composer.md`文件。
- 下载了本系统源码，且也安装好composer后，在根目录中运行`composer install`。
- Nginx（配置文件见nginx.conf），Apache，IIS下均可正常运行，PHP要求5.6以上，建议7.0以上。



# 程序结构说明：
- 程序结构为` 模块 > 控制器(模型) > 动作 > 视图 `，数据模型、视图，均由控制器派生出来，具体后面详述；
- 程序可以有任意多个模块，一般情况下一个模块对应一个子站，路由中可以任意指定模块，在控制器内也可以在`模块>控制器>动作`间切换；
- 网站入口`/public/www`，其中`www`为对应的模块名，如果有多个子站，则建一个`/public/admin/index.php`即可。

index.php:
```
<?php

#当前子站的模块名，须知晓：网站关于模块，只取自此值，至于前面说的/public/www中的模块名不影响系统，只是为了便于管理，建议取和模块名相同的路径名称。
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
该程序文件位于`/helper/bootstrap.php`，文件中只有bootstrap类，其中可以添加任意多个执行函数，所有以`_init`开头的函数都会被顺序执行。

在bootstrap中可以执行一些系统准备工作，也可以注册一些系统插件。每个函数都有默认参数`Kernel`对象。


## shutdown
shutdown用于在程序完全执行结束后运行的程序，即便在程序中`exit`，这不影响shutdown的执行。函数原型`function shutdown(Kernel $kernel)`。
对于一些与程序没有直接关系的部分，可以放在这里面执行，如记录日志等。

bootstrap和shutdown，都需要在`run()`之前调用，但不是必须要运行的。


