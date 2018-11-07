<?php

namespace esp\core;


use esp\core\db\Redis;

final class Route
{
    /**
     * 路由中心
     * Route constructor.
     * @param Redis $redis
     * @param Request $request
     * @throws \Exception
     */
    public static function _init(array $conf)
    {
        $default = [
            '_default' => ['match' => '/^\/(?:[a-z][a-z0-9\-]+\/?)*/i', 'route' => []],
        ];

        $modRoute = Buffer::get('_ROUTES_' . _MODULE);
        if (empty($modRoute)) {
            $file = rtrim($conf['path'], '/') . '/' . _MODULE . '.php';
            if (is_readable($file)) {
                $modRoute = load($file);
                if (!empty($modRoute)) {
                    foreach ($modRoute as $r => $route) {
                        if (isset($route['match']) and !is_match($route['match']))
                            throw new \Exception("Route[Match]：{$route['match']} 不是有效正则表达式", 505);
                        if (isset($route['uri']) and !is_uri($route['uri']))
                            throw new \Exception("Route[uri]：{$route['uri']} 不是合法的URI格式", 505);
                        if (!isset($route['route'])) $route['route'] = [];
                    }
                    $saveRoute = $modRoute;
                    if (is_array($saveRoute)) $saveRoute = serialize($saveRoute);
                    Buffer::set('_ROUTES_' . _MODULE, $saveRoute);
                } else {//写入一个值，否则每次经过这里还要读取一次
                    Buffer::set('_ROUTES_' . _MODULE, 'empty');
                }
            } else {
                Buffer::set('_ROUTES_' . _MODULE, 'null');
            }
        } else {
            if (in_array($modRoute, ['null', 'empty'])) $modRoute = array();
            else if (!is_array($modRoute)) $modRoute = unserialize($modRoute);
        }
        if (empty($modRoute) or !is_array($modRoute)) $modRoute = Array();
        $uri = Request::getUri();

        foreach (array_merge($modRoute, $default) as $key => &$route) {

            if ((isset($route['uri']) and stripos($uri, $route['uri']) === 0) or
                (isset($route['match']) and preg_match($route['match'], $uri, $matches))) {

                if (isset($route['method']) and !self::method_check($route['method'], Request::getMethod(), Request::isAjax()))
                    throw new \Exception('非法Method请求', 404);

                if ($key === '_default') {
                    $matches = explode('/', $uri);
                    $matches[0] = $uri;
                }

                if (isset($route['directory'])) Request::$directory = root($route['directory']);

                //分别获取模块、控制器、动作的实际值
                list($module, $controller, $action, $param) = self::fill_route(Request::$directory, $matches, $route['route']);
                if (!$controller) $controller = 'index';
                if (!$action) $action = 'index';

                //分别获取各个指定参数
                $params = Array();
                if (isset($route['map'])) {
                    foreach ($route['map'] as $mi => $mk) {
                        $params[$mi] = isset($matches[$mk]) ? $matches[$mk] : null;
                    }
                } else {
                    $params = $param;
                }

                Request::$router = $key;
                Request::$module = ($module ?: _MODULE);
                Request::$controller = $controller;
                Request::$action = $action;
                Request::$params = $params + array_fill(0, 10, null);

                if (isset($route['static'])) {
                    Request::set('_disable_static', !$route['static']);
                }

                //缓存设置，结果可能为：true/false，或array(参与cache的$_GET参数)
                //将结果放入request，供Cache类读取
                if (isset($route['cache'])) {
                    Request::set('_cache_set', $route['cache']);
                }

                unset($modRoute, $default);
                return;
            }
        }
        throw new \Exception('系统路由没有获取到相应内容', 404);
    }


    /**
     * 分别获取模块、控制器、动作的实际值
     * @param string $directory
     * @param array $matches 正则匹配结果
     * @param array $route
     * @return array
     * @throws \Exception
     */
    private static function fill_route(string $directory, array $matches, array $route): array
    {
        $module = $controller = $action = null;
        $param = Array();

        //正则结果中没有指定结果集
        if (empty($matches) or !isset($matches[1])) return [null, null, null, null];

        //未指定MCA
        if (empty($route)) {
            //在有三个以上匹配的情况下，第一个是模块名称，且不存在该名称的控制器文件
            if (isset($matches[3]) and
                is_dir($directory . $matches[1]) and
                !is_file($directory . $matches[1] . '/controllers/' . ucfirst($matches[1]) . '.php')) {
                $module = strval($matches[1]);
                $controller = strval($matches[2]);
                $action = strval($matches[3]);
                $param = array_slice($matches, 4);
            } else {//否则第一模块指的就是控制器
                $module = null;
                $controller = strval($matches[1]);
                if (isset($matches[2])) {
                    $action = strval($matches[2]);
                    $param = array_slice($matches, 3);
                }
            }
        } else {
            if (!isset($route['module'])) $route['module'] = _MODULE;
            foreach (['module', 'controller', 'action'] as $key) {
                ${$key} = isset($route[$key]) ? $route[$key] : null;
                if (is_numeric(${$key})) {
                    if (!isset($matches[${$key}])) throw new \Exception("自定义路由规则中需要第{${$key}}个正则结果，实际无此数据。", 500);
                    ${$key} = $matches[${$key}];
                }
            }
        }

        auto:
        return [$module, $controller, $action, $param];
    }

    /**
     * 访问请求类型判断
     * @param string $mode 路由中指定的类型
     * @param string $method 当前请求的实际类型，get,post,put,head,delete之一
     * @return bool
     *
     * $mode格式 ：
     * ALL,HTTP,AJAX,CLI，这四项只能选一个，且必须是第一项
     * ALL  =   仅指get,post,cli这三种模式
     * HTTP/AJAX两项后可以跟具体的method类型，如：HTTP,GET,POST
     * CLI  =   只能单独出现
     */
    private static function method_check(string $mode, string $method, bool $ajax)
    {
        if (!$mode) return true;
        list($mode, $method) = [strtoupper($mode), strtoupper($method)];
        if ($mode === $method) return true;//正好相同
        $modes = explode(',', $mode);

        if ($modes[0] === 'ALL') {
            if (count($modes) === 1) $modes = ['GET', 'POST'];
            $check = in_array($method, $modes) or _CLI;

        } elseif ($modes[0] === 'HTTP') {//限HTTP时，不是_AJAX
            if (count($modes) === 1) $modes = ['GET', 'POST'];
            $check = !$ajax and in_array($method, $modes);

        } elseif ($modes[0] === 'AJAX') {//限AJAX状态
            if (count($modes) === 1) $modes = ['GET', 'POST'];
            $check = $ajax and in_array($method, $modes);

        } elseif ($modes[0] === 'CLI') {//限CLI
            $check = _CLI;

        } else {
            $check = in_array($method, $modes);
        }
        return $check;
    }
}