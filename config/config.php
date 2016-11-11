<?php
/**
 * 系统参数定义，键名尽量不要含任何符号，主键名无论何情况都不可以含有符号，否则可能无法读取到。
 *
 * 或者可以保证这个键值不会用Config::get('esp.view_ext')这样的方式取值
 */
return [

    /**
     * ESP框架的一些定义，若非必要，不建议修改这部分
     */
    'esp' => [
        'directory' => 'application',   //网站主程序所在路径，不含模块名
        'controlExt' => 'Controller',   //控制器名后缀，注意：文件名不需要含这部分
        'modelExt' => 'Model',          //模型名后缀，注意：文件名不需要含这部分
        'actionExt' => 'Action',        //动作名后缀
        'defaultModule' => 'www',       //默认模块
        'defaultControl' => 'index',    //默认控制器
        'defaultAction' => 'index',     //默认动作
        'maxLoop' => 3,                 //控制器间最多跳转次数，无论跳转是否成功
    ],

    //另外附加的config文件名，若文件不存在或不可读，则不加载
    //遇到相同键时，以最后加载的为准，也就是说：
    //  1.  在模块级config里可以覆盖当前文件的相关定义
    //  2.  下面定义的文件先后顺序要安排好
    //  3.  如果在下面的文件中也有include，也会被加载，所以建议各模块的更多配置文件可以在各自config中定义加载
    //用_MODULE，可灵活加载不同模块的配置文件
    //Config::get('include')读不到值
    'include' => [
        '/config/' . _MODULE . '/config.php',
    ],

    'cache' => [
        'autoRun' => false,
        'expire' => 10,
        'param' => [],
        'driver' => 'redis',
        'redis' => [
            'db' => 2
        ],

        //只要URI符合下列规则，就进行静态化，而不缓存
        //注意：如果想实现/article/123456.do，而这实际是一个HTML格式文件，
        //须在nginx中设置相应mime，在第一行text/html后加上do
        //否则再次打开，会显示下载这个文件
        //Nginx中mime设置文件：/usr/local/nginx/conf/mime.types

        //若不需要静态化，就把下面清空
        'static' => [
            '/^\/\w+\/.+\.(html)([\?\#].*)?$/i',
            '/^\/tmp.+$/i',
        ],
    ],

    'session' => [
        'autoRun' => true,
        'urlKey' => 'SSID', //若客户端禁用Cookies，SessionID将被附在URL中
        'cokKey' => 'SSID', //Cookies中SessionID名称
        'driver' => 'redis',
        'expire' => 20 * 60,//秒
        'redis' => [
            'db' => 2
        ],
        'memcache' => [
            'table' => 'Session'
        ],
    ],

    //定义一些网站错误提示内容，可以是文本内容，也可以设为400/404等错误代码方式
    'error' => [
        'host' => 403,
        'method' => 403,
    ],

    /**
     * 静态资源的相关定义
     */
    'resource' => [
        'rand' => true,         //是否给js/css后加随机数，以便不被缓存
        'concat' => false,       //是否使用nginx concat插件
        'domain' => 'http://' . _DOMAIN,        //加载js/css的域名
        'jquery' => 'js/jquery-2.1.4.min.js',   //jquery所用的文件名

        //网站默认标题，$this->title('about')添加的内容被加在此处设置值之前，或$this->title('about',false)则不会带上这儿设置的内容
        'title' => 'Efficient Simple PHP',

        //$this->keywords('about');$this->description('about');会覆盖此处设置
        'keywords' => 'Efficient Simple PHP',   //默认关键词
        'description' => 'Efficient Simple PHP',//默认描述
    ],

    'view' => [
        'autoRun' => true,
        'ext' => 'php',
    ],

    'layout' => [
        'autoRun' => true,
        'filename' => 'layout.php',
    ],

];