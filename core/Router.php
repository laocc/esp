<?php
declare(strict_types=1);

namespace esp\core;

use function esp\helper\is_match;
use function esp\helper\is_uri;
use function esp\helper\load;
use function esp\helper\root;

final class Router
{
    public function __construct()
    {
    }

    /**
     * 路由中心
     * @param Configure $configure
     * @param Request $request
     * @return bool|string
     */
    public function run(Configure $configure, Request $request)
    {
        $default = [
            '_default' => ['match' => '/^\/(?:[a-z][a-z0-9\-]+\/?)*/i', 'route' => []],
        ];
        $rdsKey = $configure->_token . '_ROUTES_' . _VIRTUAL;
        $redis = $configure->_Redis;
        $modRoute = (!_CLI and (!defined('_CONFIG_LOAD') or !_CONFIG_LOAD) and $redis) ? $redis->get($rdsKey) : null;

        if (empty($modRoute) or $modRoute === 'null') {
            $file = $request->router_path . '/' . _VIRTUAL . '.php';
            if (is_readable($file)) {
                $modRoute = load($file);
                if (!empty($modRoute)) {
                    foreach ($modRoute as $r => $route) {
                        if (isset($route['match']) and !is_match($route['match']))
                            return ("Route[Match]：{$route['match']} 不是有效正则表达式");

                        if (isset($route['uri']) and !is_uri($route['uri']))
                            return ("Route[uri]：{$route['uri']} 不是合法的URI格式");

                        if (!isset($route['route'])) $route['route'] = [];
                    }
                    $saveRoute = $modRoute;
                    if (is_array($saveRoute)) $saveRoute = json_encode($saveRoute, 256);
                    !is_null($redis) and $redis->set($rdsKey, $saveRoute);
                } else {//写入一个值，否则每次经过这里还要读取一次
                    !is_null($redis) and $redis->set($rdsKey, 'empty');
                }
            } else {
                !is_null($redis) and $redis->set($rdsKey, 'null');
            }
        }
        if (is_string($modRoute) and !empty($modRoute)) $modRoute = json_decode($modRoute, true);
        if (empty($modRoute) or !is_array($modRoute)) $modRoute = array();
        foreach (array_merge($modRoute, $default) as $key => $route) {
            $matches = [];
            if ((isset($route['uri']) and stripos(_URI, $route['uri']) === 0) or
                (isset($route['match']) and preg_match($route['match'], _URI, $matches))) {

                if (isset($route['method']) and !$this->method_check($route['method'], $request->method, $request->isAjax())) {
                    return ('非法Method请求');
                }

                if ($key === '_default') {
                    $matches = explode('/', _URI);
                    $matches[0] = _URI;
                }

                if (isset($route['route']['virtual'])) $request->virtual = $route['route']['virtual'];
                else if (isset($route['virtual'])) $request->virtual = $route['virtual'];

                if (isset($route['route']['directory'])) $request->directory = root($route['route']['directory']);
                else if (isset($route['directory'])) $request->directory = root($route['directory']);

                //分别获取模块、控制器、动作的实际值
                $routeValue = $this->fill_route($request->virtual, $request->directory, $matches, $route['route']);
                if (is_string($routeValue)) return $routeValue;

                list($module, $controller, $action, $param) = $routeValue;

                //分别获取各个指定参数
                $params = array();
                if (isset($route['map'])) {
                    foreach ($route['map'] as $mi => $mk) {
                        $params[$mi] = isset($matches[$mk]) ? $matches[$mk] : null;
                    }
                } else {
                    $params = $param;
                }

                $request->router = $key;
                $request->module = $module ?: '';
                $request->controller = $controller ?: 'index';
                $request->action = $action ?: 'index';
                $request->params = $params + array_fill(0, 10, null);
                if (isset($route['static'])) {
                    $request->set('_disable_static', !$route['static']);
                }

                //缓存设置，结果可能为：true/false，或array(参与cache的$_GET参数)
                //将结果放入request，供Cache类读取
                if (isset($route['cache'])) {
                    $request->set('_cache_set', $route['cache']);
                } else {
                    $cacheSet = $configure->get("cache.{$request->module}.{$request->controller}.{$request->action}");
                    if ($cacheSet) {
                        $request->set('_cache_set', $cacheSet);
                    }
                }

                //路由器对视图的定义
                if (isset($route['view']) and $route['view']) $request->route_view = $route['view'];
                unset($modRoute, $default);
                return true;
            }
        }
        return ('系统路由没有获取到相应内容');
    }


    /**
     * 分别获取模块、控制器、动作的实际值
     * @param string $virtual
     * @param string $directory
     * @param array $matches 正则匹配结果
     * @param array $route
     * @return array|string
     */
    private function fill_route(string $virtual, string $directory, array $matches, array $route): array
    {
        $module = $controller = $action = '';
        $param = array();

        //正则结果中没有指定结果集
        if (empty($matches) or !isset($matches[0])) return [null, null, null, null];

        //未指定MCA
        if (empty($route)) {
            if (($matches[1] ?? '') and is_dir("{$directory}/{$virtual}/{$matches[1]}")) {
                $module = strtolower($matches[1]);
                $controller = ($matches[2] ?? 'index');
                $action = ($matches[3] ?? 'index');
                if (isset($matches[4])) {
                    $param = array_slice($matches, 4);
                }
            } else {//否则第一模块指的就是控制器
                $module = null;
                $controller = ($matches[1] ?? 'index');
                $action = ($matches[2] ?? 'index');
                if (isset($matches[3])) {
                    $param = array_slice($matches, 3);
                }
            }
        } else {
            if (!isset($route['module'])) $route['module'] = '';
            foreach (['module', 'controller', 'action'] as $key) {
                ${$key} = $route[$key] ?? null;
                if (is_numeric(${$key})) {
                    if (!isset($matches[${$key}])) return ("自定义路由规则中需要第{${$key}}个正则结果，实际无此数据。");
                    ${$key} = $matches[${$key}];
                }
            }
        }

        auto:
        return [$module, strtolower($controller), strtolower($action), $param];
    }

    /**
     * 访问请求类型判断
     *
     * $mode格式 ：
     * ALL,HTTP,AJAX,CLI，这四项只能选一个，且必须是第一项
     * ALL  =   仅指get,post,cli这三种模式
     * HTTP/AJAX两项后可以跟具体的method类型，如：HTTP,GET,POST
     * CLI  =   只能单独出现
     *
     * @param string $mode 路由中指定的类型
     * @param string $method 当前请求的实际类型，get,post,put,head,delete之一
     * @param bool $ajax
     * @return bool
     */
    private function method_check(string $mode, string $method, bool $ajax): bool
    {
        if (!$mode) return true;
        list($mode, $method) = [strtoupper($mode), strtoupper($method)];
        if ($mode === $method) return true;//正好相同
        $modes = explode(',', $mode);

        if ($modes[0] === 'ALL') {
            if (count($modes) === 1) $modes = ['GET', 'POST'];
            $check = in_array($method, $modes) or _CLI;

        } elseif ($modes[0] === 'HTTP') {//限HTTP时，只能GET或POST，且不能是ajax
            if (count($modes) === 1) $modes = ['GET', 'POST'];
            $check = !$ajax and in_array($method, $modes);

        } elseif ($modes[0] === 'AJAX') {//限AJAX时，只能GET或POST
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