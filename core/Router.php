<?php
declare(strict_types=1);

namespace esp\core;

use function esp\helper\is_match;
use function esp\helper\is_uri;
use function esp\helper\load;
use function esp\helper\root;

final class Router
{
    private $uri;

    public function __construct()
    {
        $this->uri = _URI;
    }

    /**
     * 路由中心
     * @param Configure $configure
     * @param Request $request
     * @return bool|string
     */
    public function run(Configure $configure, Request $request)
    {
        $rdsKey = $configure->_token . '_ROUTES_' . _VIRTUAL;
        $redis = $configure->_Redis;

        $cache = (!defined('_CONFIG_LOAD') or !_CONFIG_LOAD) and !isset($_GET['_config_load']);
        $modRoute = (!_CLI and $cache and $redis) ? $redis->get($rdsKey) : [];

        if (empty($modRoute) or $modRoute === 'null') {
            $modRoute = $this->loadRouteFile($request);
            if (!is_null($redis)) {
                if (empty($modRoute)) {
                    $redis->set($rdsKey, 'null');
                } else {
                    $redis->set($rdsKey, json_encode($modRoute, 256 | 64));
                }
            }
        }

        if (is_string($modRoute) and !empty($modRoute)) $modRoute = json_decode($modRoute, true);
        if (empty($modRoute) or !is_array($modRoute)) $modRoute = array();

        $default = ['__default__' => ['__default__' => 1, 'route' => []]];//默认路由
        foreach (array_merge($modRoute, $default) as $key => $route) {
            $matcher = $this->getMatcher($key, $route);
            if (!$matcher) continue;

            if (isset($route['method']) and !$this->method_check($route['method'], $request->method, $request->isAjax())) {
                return ('非法Method请求');
            }

            if (isset($route['route']['virtual'])) $request->virtual = $route['route']['virtual'];
            else if (isset($route['virtual'])) $request->virtual = $route['virtual'];

            if (isset($route['route']['directory'])) $request->directory = root($route['route']['directory']);
            else if (isset($route['directory'])) $request->directory = root($route['directory']);

            //分别获取模块、控制器、动作的实际值
            $routeValue = $this->fill_route($request->virtual, $request->directory, $matcher, $route['route']);
            if (is_string($routeValue)) return $routeValue;
            list($module, $controller, $action, $param) = $routeValue;

            //分别获取各个指定参数
            $params = array();
            if (isset($route['map'])) {
                foreach ($route['map'] as $mi => $mk) $params[$mi] = $matcher[$mk] ?? null;
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
//                print_r($request);
            return true;
        }

        return '系统路由没有获取到相应内容，或非法请求URL。';
    }

    /**
     * 匹配路由
     *
     * @param string $key
     * @param array $route
     * @return false|string[]|null
     */
    private function getMatcher(string $key, array $route)
    {
        if ((isset($route['path']) and $route['path'] === $this->uri) or
            (isset($route['uri']) and (stripos($this->uri, $route['uri']) === 0)) or
            (isset($route['like']) and (stripos($this->uri, $route['like'])) !== false)) {
            $matcher = explode('/', $this->uri);
            $matcher[0] = $this->uri;
            return $matcher;
        }

        if (isset($route['match']) and preg_match($route['match'], $this->uri, $matcher)) return $matcher;

        if (isset($route['__default__']) and ($this->uri === '/' or $this->uri === '')) return ['/', ''];

        if (isset($route['__default__']) and preg_match('#^\/[a-z][a-z0-9\-\_]+\/?.*#i', $this->uri)) {
            $matcher = explode('/', $this->uri);
            $matcher[0] = $this->uri;
            return $matcher;
        }

        return null;
    }

    private function loadRouteFile(Request $request)
    {
        if (is_readable($file = ($request->router_path . '/' . _VIRTUAL . '.php'))) {
            $modRoute = load($file);
        } else if (is_readable($file = ($request->router_path . '/' . _VIRTUAL . '.ini'))) {
            $modRoute = parse_ini_file($file, true);
            if (!is_array($modRoute) or empty($modRoute)) return [];
            //只将一级键名中带.号的，转换为数组，如将：abc.xyz=123转换为abc[xyz]=123
            foreach ($modRoute as $k => $v) {
                if (!is_string($k)) continue;
                if (strpos($k, '.')) {
                    $tm = explode('.', $k, 2);
                    $modRoute[$tm[0]][$tm[1]] = $v;
                    unset($modRoute[$k]);
                }
            }
        }
        if (empty($modRoute)) return [];

        foreach ($modRoute as $r => $route) {
            if (isset($route['match']) and !is_match($route['match']))
                return ("Route[Match]：{$route['match']} 不是有效正则表达式");

            if (isset($route['uri']) and !is_uri($route['uri']))
                return ("Route[uri]：{$route['uri']} 不是合法的URI格式");

            if (!isset($route['route'])) $modRoute[$r]['route'] = [];
        }
        return $modRoute;
    }

    /**
     * 分别获取模块、控制器、动作的实际值
     * @param string $virtual
     * @param string $directory
     * @param array $matcher 正则匹配结果
     * @param array $route
     * @return array|string
     */
    private function fill_route(string $virtual, string $directory, array $matcher, array $route): array
    {
        $module = $controller = $action = '';
        $param = array();
        if (empty($matcher) or !isset($matcher[0])) return [null, null, null, null];
        if (empty($route)) {
            //第一节点指向的目录存在，则第一节点为module
            if (($matcher[1] ?? '') and is_dir("{$directory}/{$virtual}/{$matcher[1]}")) {
                $module = strtolower($matcher[1]);
                $controller = ($matcher[2] ?? 'index');
                $action = ($matcher[3] ?? 'index');
                if (isset($matcher[4])) {
                    $param = array_slice($matcher, 4);
                }
            } else {//否则第一节点指的是控制器
                $module = null;
                $controller = ($matcher[1] ?? 'index');
                $action = ($matcher[2] ?? 'index');
                if (isset($matcher[3])) {
                    $param = array_slice($matcher, 3);
                }
            }
        } else {
            if (!isset($route['module'])) $route['module'] = '';
            foreach (['module', 'controller', 'action'] as $key) {
                ${$key} = $route[$key] ?? null;
                if (is_numeric(${$key})) {
                    ${$key} = $matcher[${$key}] ?? 'index';
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