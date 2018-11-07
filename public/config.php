<?php

$option = array();


/**
 * 系统框架基本设置
 */
$option['request']['directory'] = '/application';//程序主目录

/**
 * 路由设置目录
 */
$option['router']['path'] = '/config/routes';

/**
 * 控制方法函数后缀
 */
$option['request']['suffix'] = array();
$option['request']['get'] = 'Action';
$option['request']['ajax'] = 'Ajax';
$option['request']['post'] = 'Post';


/**
 *
 */
$option['response']['auto'] = true;


/**
 * 系统缓存设置
 * 此处设置的配置内容，在程序中用Config::get('File.Key');读取
 */
//$option['config'][] = '/config/system.ini';
//$option['config'][] = '/config/app.ini';
$option['config'][] = '/config/database.ini';
$option['config'][] = '/config/resource.ini';

/**
 * 系统保存缓存的介质，保存的内容：
 * 1，config内容，也就是上面config内容，string方式保存
 * 2，cache内容，也就是最后面cache
 * 3，数据库缓存，就是Model中的缓存
 * 4，路由设置，也就是上面$option['request']['router']指向的目录中的内容
 *
 */
$option['buffer']['flush'] = true;       //清空缓存，实际应用中请删除此或，或设为false
$option['buffer']['key'] = 'Esp';       //多站点标识，当同一Redis服务器要保存不同站点数据时，此key用于区分不同站点
$option['buffer']['driver'] = 'Redis';
$option['buffer']['host'] = '/tmp/redis.sock';
$option['buffer']['db'] = 0;


/**
 * 调试Debug开启，若不定义，则不启用
 */
$option['debug']['run'] = true;  //是否自动启动记录，若=false，则需要在控制器中手动star()打开
$option['debug']['path'] = '/cache';  //正常日志文件保存目录，保存在此目录下debug中

$option['debug']['rules']['folder'] = 'Y-m-d';  //正常日志文件保存目录名的规则，用于date()函数
$option['debug']['rules']['filename'] = 'H_i_s';  //正常日志文件名规则，用于date()函数
$option['debug']['rules']['error'] = 'E_ymdHis_';  //记录错误的文件命名规则，这里指date()函数的参数

$option['debug']['print']['mysql'] = true;   //是否记录mysql所有语句，以下四项默认均为false
$option['debug']['print']['post'] = true;    //是否记录接收到的POST内容
//$option['debug']['print']['html'] = true;    //是否记录最后打印html结果
//$option['debug']['print']['server'] = true;  //是否记录_server内容


/**
 * SESSION有关设置，可以和上面buffer保存到相同的地方，但这两者之间没关系，且，不要用0库
 */
$option['session']['run'] = true;             //是否启用
$option['session']['key'] = 'CS';             //COOKIES的键名
$option['session']['prefix'] = 'CS-';         //COOKIES键值的前缀
$option['session']['driver'] = 'redis';       //存储介质，可选：files=即PHP原生用文件的方式保存，或:redis,hash
$option['session']['host'] = '/tmp/redis.sock';//session保存位置，如果是files则是指目录，且该目录要有读取权限
$option['session']['db'] = 10;               //session保存库
$option['session']['ttl'] = 86400;          //session有效时间


/**
 * 页面缓存
 */
$option['cache']['run'] = false;        //是否启用
$option['cache']['type'] = 'string';    //存储方式：string或hash
$option['cache']['ttl'] = 86400;        //缓存保存时间
$option['cache']['zip'] = true;         //是否去除所有空行
$option['cache']['space'] = true;       //两个以上的空格合为一个
$option['cache']['notes'] = true;       //删除所有注释
$option['cache']['tags'] = true;        //删除HTML之间的空格


/**
 * HTML静态化的正则规则
 * 静态化不同于保存到缓存，而是真实保存为文本文件
 * 注意：如果想实现/article/123456.do，而这实际是一个HTML格式文件，须在nginx中设置相应mime，在第一行text/html后加上do,否则会显示下载文件
 */
$option['cache']['static'] = Array();
//$option['cache']['static'][] = '/^\/\w+\/.+\.(html)([\?\#].*)?$/i';
//$option['cache']['static'][] = '/^\/tmp.+$/i';
//$option['cache']['static'][] = '/^\/article.+$/i';


/**
 * 程序出错的时候，页面显示什么内容，以下仅在非DEUBG状态下有效
 * DEBUG环境中均为2，即显示详情
 * 0：不显示任何内容，页面显示空白
 * 1：简单显示
 * 2：详细显示
 * 3：仅throw有效，显示为throw时指定的错误码
 * N：显示为一个错误信息，N=Config::state()中的某个值
 * T：显示此文本，T=要显示的信息内容
 */
$option['error']['run'] = 505;  //运行时出错
$option['error']['throw'] = 3;  //手动throw，若throw时没指定错误代码，则以run为准
$option['error']['filename'] = 'Y-m-d/H.i.s.';  //错误日志文件名


return $option;