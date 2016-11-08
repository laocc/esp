
# ESP (Efficient Simple PHP)
- PHP >5.6
- 这是一个比较高效且简洁的PHP框架。本框架的部分结构思路参考YAF框架（http://yaf.laruence.com/）。

# 一、安装
- 目前处于测试版阶段，下载()解压即可使用。
- 网站核心文件加载机制为利用composer的autoload，另外程序所需要的一些插件也依赖于composer，所以关于composer部分，请参看`composer.md`文件。
- 下载本系统源码，且也安装好composer后，在根目录中运行`composer install`。
- Nginx（配置文件见nginx.conf），Apache，IIS下均可正常运行，PHP要求5.6以上，建议7.0以上。

# 二、文件结构：
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

# 三、程序说明：
- 程序结构为` M模块 > c控制器(m模型) > a动作 > v视图 `，数据模型、视图，均由控制器派生出来；本人觉得这种MVC模式应该称之为`Mcmav`模式。
- 程序可以有任意多个模块，一般情况下一个模块对应一个子站，路由中可以任意指定模块，在控制器内也可以在`模块>控制器>动作`间切换；
- 网站入口`/public/www/index.php`，其中`www`为对应的模块名，实际模块名由该文件中`_MODULE`决定。
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

## 3.1 bootstrap
bootstrap用于在程序正式执行之前所运行的程序。
该程序文件位于`/helper/Bootstrap.php`，文件中只有ootstrap类，其中可以添加任意多个执行函数，所有以`_init`开头的函数都会被顺序执行。

在bootstrap中可以执行一些系统准备工作，也可以注册一些系统插件。每个函数都有默认参数`Kernel`对象。
```
<?php
use \esp\core\Kernel;
use \esp\plugins\Adapter;

final class Bootstrap
{
    public function _initAdapter(Kernel $kernel)
    {
        $kernel->setPlugin('adapter', new Adapter());
    }
}
```

## 3.2 shutdown
shutdown用于在程序完全执行结束后运行的程序，即便在程序中`exit()`shutdown也会在exit之后执行。
对于一些与程序没有直接关系的部分，可以放在这里面执行，如记录日志等。
```
function shutdown(Kernel $kernel)
{
    if (!$kernel->shutdown()) return;
    
    //在这加要执行的内容
    
}
```
`Kernel->shutdown()`是一个双重任务的函数，第一次运行执行注册shutdown方法，第二次及以后返回是否执行已注册的shutdown，在控制器中用`$this->shutdown(false)`可关闭shutdown。

bootstrap()和shutdown()，都需要在`run()`之前调用，但不是必须要运行。


## 3.3 程序流程
1. 创建`Kernel`实例，同时创建`Request`和`Response`实例 
2. `Kernel->bootstrap()`执行bootstrap，在此期间可注册一些插件
3. `Kernel->shutdown()`注册shutdown
4. `Kernel->run()`启动
4. 触发`routeBefore`事件
5. 路由筛选，并返回路由结果到`Request`
4. 触发`routeAfter`事件，查询缓存，若存在有效缓存，则发送至`Response`，并跳过从此处到`dispatchAfter`之间的工作
6. 根据路由结果，分发到相应控制器，并执行控制器动作，在此期间`控制器`中创建`模型`获取数据，并将数据发送至`Response`
4. 触发`dispatchAfter`事件，标签解析器插件可在此处注册。
5. 启动`Response->display()`读取相关视图，并显示最终结果，若有必要同时缓存
4. 触发`kernelEnd`事件
4. 触发`shutdown`

## 3.4 网页展示内容方式：
1. `默认`：网页默认为HTML方式展示，也就是按正常方式显示视图中的内容
2. `html`：与默认有所不同的是，这种方式下显示的内容为`$this->html(...)`中的内容，而不是视图内容
3. `json`：json/jsonp格式显示`$this->json(ARRAY)`的内容，`Content-type:application/json`
4. `xml`：xml格式显示`$this->xml(ARRAY)`的内容，`Content-type:text/xml`
5. `text`：纯文本格式显示`$this->text(STRING)`的内容，`Content-type:text/plain`。用`print_r()`显示，也就是说如果传入的是数组，则会显示为数组形式（不是json格式）。

上述5种方式中，只有在`默认`方式时，才会产生`view`对象，但在控制中调用`view()`方法时则会提前自动创建，包括`layout()`对象。



## 3.5 关于视图 view
默认情况下，视图文件与控制器动作一一对应，视图文件被包含在框架视图中，视图文件中的标签，可以直接用原生PHP方式显示，也可以用`{@var}`的标签方式，这种情况下，需要第三方标签解析器进行转换。

注意：如果在控制器中调用过上面`3.4 网页展示内容方式`中除默认之外的四种方式，都不会产生视图，在这种情况下，如果还需要视图，则需要在控制器动作最后的位置执行`$this->html();`*不带参数*即可清除之前四种方式设置的内容，也就是以最后执行的为准。


### 3.5.1 框架视图：
每个控制器可以有一个默认框架`layout.php`，如果控制器目录没有框架文件`layout.php`，则调用模块级的`layout.php`，若也不存在模块级的layout，则会出错。
当然可以在动作中关闭`$this->layout(false)`，或直接指定layout文件。

框架视图里有8个固定变量：
```
 * @var $_title ;   网页标题
 * @var $_meta ;    网页META，包括keywords、description以及其他注册的meta标签
 * @var $_css ;     网页CSS
 * @var $_js_head ; 网页JS，用在head中显示
 * @var $_js_body ; 网页JS，用在body中开始的位置显示
 * @var $_js_foot ; 网页JS，用在网页body之后显示
 * @var $_js_defer ;网页JS，显示位置同foot，但是加了defer属性，也就是延迟加载
 * @var $_body_html;子视图内容，也就是与控制器动作对应的视图文件解析结果
```
这些变量在layout中可读，但是如果当前设置没有layout，则会被释放到子视图中。


### 3.5.2 标签解析器
至于用什么格式的标签，可任意，现以smarty为例，实现标签解析器注册：

前文bootstrap中为注册了一个`new Adapter()`插件，在此类`dispatchAfter()`中：
```
public function dispatchAfter(Request $request, Response $response)
{
    if ($response->getType()) return; ##如果网页格式不是默认方式，则不需要注册
    $_adapter = new \Smarty();
    $_adapter->setCompileDir(root('smarty/cache'));
    $response->registerAdapter($_adapter);
}
```
其他不用管了，在最后渲染视图时自动会调用此插件解析。但是须注意：标签解析器只对子视图有效，对于`layout`不起作用。控制器中可以用`$this->adapter(false);`关闭这个已注册的解析器。

### 3.5.3 视图变量
在控制器中向视图传送变量的几种方式：
```
$this->assign($name, $value);               #送至子视图
$this->view()->assign($name, $value);       #送至子视图，但优先级没有上面的高，比如同时送了相同的$name，则以上面的方式为准
$this->adapter()->assign($name, $value);    #送至标签解析器，只会在标签解析器内存在，即便之后关闭了解析器，这些值也不会跑到子视图中
$this->layout()->assign($name, $value);     #送至框架视图，与上面8个变量不同，即便后面关闭了框架，这些变量也不会送到子视图
```
前三种方式在传送相同$name的情况下，优先级为：`$this` > `view()` > `adapter()`，与先后顺序无关。



## 四、函数表 

下表中函数的参数，`[$filename]`表示可有缺省值，调用时可以不用指定，没加方括号的都不可省略，

|函数|概要|
|---|---|
|`Kernel::class`|Kernel是esp框架系统核心调用中心，只可以在网站入口处进行实例化<br>下面前三个函数在入口处运行，后三个在程序中获取到kernel对象实例后调用
|`Kernel->bootstrap()`|启动bootstrap，对应`/helper/Bootstrap.php`文件
|`Kernel->shutdown()`|第一次运行注册shutdown，第二次及以后返回是否需要继续执行shutdown
|`Kernel->run()`|系统起动，只能在网站入口处调用，前两个同样
|`Kernel->setPlugin(string $name,object $obj)`|注册插件，一般运行在bootstrap中
|`Kernel->getRequest()`|返回request实例
|`Kernel->getResponse()`|返回response实例
||
|`Config::class`|config类可以在整个系统中任何处调用，该类对应数据取自`/config/config.php`，编辑该文件须注意键名中不能含符号
|`Config::get(string $keys)`|返回一个config值，可以用`Config::get('esp.directory')`方式读取多维数组子键
|`Config::has(string $keys)`|判断一个config键是否存在，$key方式同上
|`Config::set(string $key,$value)`|设置一个config键值，注意：这儿$key只能是根键，不可以象get中的那样用
|`Config::mime(string $type)`|返回一个mime类型值，如`Config::mime('gif');`=`image/gif`
|`Config::states(int $code)`|返回一个网页状态码的描述，如`Config::states(304)`=`Not Modified`
||
|`Controller::class`|控制器基类，网站所有实际控制器必须继承此类，控制器所在路径下可以含有一个`Base.php`含有`BaseController`类，继承顺序：<br>`IndexController extends BaseController extends esp\core\Controller`<br>以下所有方法均指在网站业务控制器中直接以`$this->getRequest();`方式调用
|`Controller::class`|
|`Request::class`|
|`Response::class`|
|`View::class`|

# END

