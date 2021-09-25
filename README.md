
# ESP (Efficient Simple PHP)
- PHP >7
- 这是一个高效简洁的PHP框架
- 框架已应用于实际项目多年，但仍处于持续完善阶段，升级时请注意查看更新说明。

# 一、框架安装

1. `composer`直接安装：
```
composer create-project laocc/esp-install website
```
其中`website`为项目目录，可任意自定义


2. 在`composer.json`中引入
```json
{
  "name": "website/1.1",
  "require": {
    "laocc/esp": "*"
  }
}
```

在生产环境中，建议执行加载优化
```
composer dump-autoload --optimize
```

# 二、程序说明
- 主要基本变量：
    - `_VIRTUAL`：虚拟机(子项目)，例如大项目下有`www``admin``api`等应用，则这些都是相对独立的子项目
    - `_ROOT`：项目根目录，此变量可以在入口处自已定义
    - `_RUNTIME`：临时文件目录，即`/runtime/`，此目录要加到`.gitignore`中，也可以指定到项目目录以外的任意目录
    - `_CLI`：当前实例是否运行在cli环境下
    - `_DEBUG`：当前服务器是否debug(开发)环境，添加`runtime/debug.lock`即为debug环境
    
- 程序结构为` 虚拟机 > 模块 > 控制器 - 数据模型 > 动作 > 视图 `，数据模型、视图，均由控制器派生出来。
- 程序可以有任意多个虚拟机，一般情况下一个虚拟机对应一个子站；
- 网站入口`/public/www/index.php`，其中`www`建议对应虚拟机名称，但实际虚拟机名称由`_VIRTUAL`决定。

## 3.4 网页展示内容方式：
1. `默认`：网页默认为HTML方式展示，也就是按正常方式显示视图中的内容，控制器中没有`return`或`return null;`，此时视图文件要必须存在；
2. `html`：与默认有所不同的是，这种方式下显示的内容为`$this->html(...)`中的内容，而不是视图内容；
3. `json`：json/jsonp格式显示`$this->json(Array)`的内容，`Content-type:application/json`；
4. `xml`：xml格式显示`$this->xml('root',Array)`的内容，注意：这里的参数是数组，不是转换过的xml代码，root是根节点名称，`Content-type:text/xml`
5. `text`：纯文本格式显示`$this->text(String)`的内容，`Content-type:text/plain`。用`print_r()`显示，也就是说如果传入的是数组，则会显示为数组形式（不是json格式）。

上述5种方式中，只有在`默认`方式时，才会产生`view`对象，但在控制中调用`view()`方法时则会提前自动创建，包括`layout()`对象。



## 3.5 关于视图 view
默认情况下，视图文件与控制器动作相对应，视图文件被包含在框架视图中，视图文件中的标签，可以直接用原生PHP方式显示，也可以用`{@var}`的标签方式，这种情况下，需要第三方标签解析器进行转换。

注意：如果在控制器中调用过上面`3.4 网页展示内容方式`中除默认之外的四种方式，都不会产生视图，在这种情况下，如果还需要视图，则需要在控制器动作最后的位置执行`$this->html();`*不带参数*即可清除之前四种方式设置的内容，也就是以最后执行的为准。

若`config::view=>autoRun`=`false`，而在控制器中没有调用过`view()`，则不会创建视图对象，此时如果不是json等方式，网页什么都不会显示。

控制器中可以用`$this->view(FILE PATH);`指定视图文件，但这不会改变查找layout的路径方式。

### 3.5.1 框架视图：
每个控制器可以有一个默认框架`layout.php`，该控制下所有`动作`>`视图`都使用此框架视图，如果控制器目录没有框架文件`layout.php`，则调用模块级的`layout.php`，若也不存在模块级的layout，则会出错。
当然可以在动作中关闭`$this->layout(false)`，或直接指定layout文件。

若`config::layout=>autoRun`=`false`，而在控制器中没有调用过`layout()`，则也不会创建layout，自然也不会去查找`layout.php`。

控制器中可以用`$this->layout(FILE PATH);`指定框架文件。

框架视图里有8个固定变量，这些变量在layout中可读，但是如果当前设置没有layout，则会被释放到子视图中。


### 3.5.2 标签解析器
至于用什么格式的标签，可任意，现以smarty为例，实现标签解析器注册：
index.php
```
$option = include_once('../config.php');
$dis = new \esp\core\Dispatcher($option, 'www');
$dis->setPlugin(new \library\Plugs());
$dis->run();
```
在这个`Plugs`中实现`$response->registerAdapter(new Adapter());`；

其他不用管了，在最后渲染视图时自动会调用此插件解析。但是须注意：标签解析器只对子视图有效，对于`layout`不起作用。
控制器中可以用`$this->adapter(false);`关闭这个已注册的解析器。

### 3.5.3 视图变量
在控制器中向视图传送变量的几种方式：
```
$this->assign($name, $value);               #送至子视图
$this->view()->assign($name, $value);       #送至子视图，优先级没有上面高，同时送了相同的$name，以上面的为准
$this->adapter()->assign($name, $value);    #送至标签解析器，即便之后关闭了解析器，这些值也不会跑到子视图中
$this->layout()->assign($name, $value);     #送至框架视图，与上面8个变量不同，即便关闭了框架，也不会送到子视图
```
前三种方式在传送相同$name的情况下，优先级为：`$this` > `view()` > `adapter()`，与先后顺序无关。

## 四、系统插件
- 系统插件须在`bootstrap`内注册，注册方法请参看`3.1`节例程。插件类都须继承自`\esp\core\Plugin`。
- 插件类里可以实现6个系统HOOK，分别在系统不同时机被自动调用。
- 插件本身可以完成自己相应的工作，同时在控制器里可以通过下面的方法实现调用插件方法：



# END

