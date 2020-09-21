<?php

$route = array();


/**
 * 小工具
 */
$route['tools'] = [];
$route['tools']['match'] = '/\/(tools)\/(.+)\/?/i';
$route['tools']['route'] = [];
$route['tools']['route']['module'] = 'www';
$route['tools']['route']['controller'] = 1;
$route['tools']['route']['action'] = 'index';
$route['tools']['map'][] = 2;


$route['article'] = [];

/**
 * 允许的请求方法，可选值：All,get,post,cli
 */
$route['article']['method'] = 'get';

/**
 * application位置，非必填
 */
//$route['article']['directory'] = '/application';

/**
 * 匹配规则
 */
$route['article']['match'] = '/\/(article)\/view\/(.+)\/?/i';

/**
 * 符合上面match的URL分配到下列控制器及方法
 */
$route['article']['route'] = [];
$route['article']['route']['module'] = 'www';
$route['article']['route']['controller'] = 'article';
$route['article']['route']['action'] = 'index';

/**
 * 对于上面match匹配到的结果，分别按下列规则指定为方法的参数
 * 下列数组的排列顺序即为传入Action方法参数的顺序
 */
$route['article']['map'][] = 2;
$route['article']['map'][] = 1;

/**
 * 指定缓存方法，此设置会覆盖入口处cache.run的值
 */
$route['article']['cache'] = false;


$route['test'] = [];

/**
 * 允许的请求方法，可选值：All,get,post,cli
 */
$route['test']['method'] = 'get';

/**
 * application位置，非必填
 */
//$route['article']['directory'] = '/application';

/**
 * 匹配规则
 */
$route['test']['uri'] = '/abc/adf?';

/**
 * 符合上面match的URL分配到下列控制器及方法
 */
$route['test']['route'] = [];
$route['test']['route']['module'] = 'www';
$route['test']['route']['controller'] = 'article';
$route['test']['route']['action'] = 'index';


/**
 * 指定缓存方法，此设置会覆盖入口处cache.run的值
 */
$route['test']['cache'] = false;


return $route;