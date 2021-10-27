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
     * @return string
     */
    public function run(Configure $configure, Request $request): ?string
    {
        $rdsKey = $configure->_token . '_ROUTES_' . _VIRTUAL;
        $redis = $configure->_Redis;

        $cache = boolval((!defined('_CONFIG_LOAD') or !_CONFIG_LOAD) and !isset($_GET['_config_load']));
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
                return '非法Method请求';
            }

            if (isset($route['return']) and !empty($ret = trim($route['return']))) {
                $rHd = substr(strtolower($ret), 0, 6);
                $rHs = $ret[0];
                if ($rHd === 'http:/' or $rHd === 'https:') {
                    header('Expires: ' . gmdate('D, d M Y H:i:s', time() - 1) . ' GMT');
                    header("Cache-Control: no-cache");
                    header("Pragma: no-cache");
                    header("Location: {$ret}", true, 301);
                    fastcgi_finish_request();
                    return '';

                } else if ($rHd === 'redis:') {
                    header("Content-type: text/plain; charset=UTF-8", true, 200);
                    return strval($redis->get(substr($ret, 6)));

                } else if ($ret[0] === '/') {
                    if (!is_readable(_ROOT . $ret)) return "route return `{$ret}` not exists.";
                    include_once _ROOT . $ret;
                    return '';

                } else if ($ret[0] === '{') {
                    header("Content-type: application/json; charset=UTF-8", true, 200);
                    return $ret;

                } else {
                    header("Content-type: text/plain; charset=UTF-8", true, 200);
                    return strval(str_replace(['\r', '\n'], ["\r", "\n"], $ret));
                }
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
                foreach ($route['map'] as $mi => $mk) {
                    if (is_int($mk)) {
                        $params[$mi] = $matcher[$mk] ?? null;
                    } else {
                        $params[$mi] = $mk;
                    }
                }
            } else {
                $params = $param;
            }

            $request->router = $key;
            $request->module = $module ?: '';
            $request->controller = $controller ?: 'index';
            $request->action = $action ?: 'index';
            $request->params = $params + array_fill(0, 10, null);
            if (!defined('_MODULE')) define('_MODULE', $request->module);

            $check = $request->checkController();
            if (is_string($check)) return $check;

            //路由器对视图的定义
            if (isset($route['view']) and $route['view']) $request->route_view = $route['view'];
            unset($modRoute, $default);

            return null;
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
        if (_URI === '/' or !_URI) return ['/', ''];

        /**
         * 1，指定了path，需完全相等
         * 2，指定了uri，需以际URI开头
         * 3，指定了相似，也就是URI是此值的一部分
         */
        if ((isset($route['path']) and $route['path'] === _URI) or
            (isset($route['uri']) and (stripos(_URI, $route['uri']) === 0)) or
            (isset($route['like']) and (stripos(_URI, $route['like'])) !== false)) {
            $matcher = explode('/', _URI);
            $matcher[0] = _URI;
            return $matcher;
        }

        /**
         * 以正则方式匹配
         */
        if (isset($route['match']) and preg_match($route['match'], _URI, $matcher)) return $matcher;

        /**
         * 默认路由
         */
        //#^/[a-z][a-z0-9\-_]*/?.*#i
        if (isset($route['__default__']) and preg_match('#^/[a-z]\w*/?.*#i', _URI)) {
            $matcher = explode('/', _URI);
            $matcher[0] = _URI;
            return $matcher;
        }

        return null;
    }

    /**
     * 加载路由文件
     *
     * @param Request $request
     * @return mixed|array|bool|string
     */
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
     * ALL,HTTP,AJAX,CLI，这四项只能选一个
     * ALL  =   仅指get,post,cli这三种模式
     * HTTP/AJAX两项后可以跟具体的method类型，如：HTTP,GET,POST
     *
     * @param string $mode 路由中指定的类型
     * @param string $method 当前请求的实际类型，get,post,put,head,delete之一
     * @param bool $ajax
     * @return bool
     */
    private function method_check($mode, string $method, bool $ajax): bool
    {
        if (!$mode) return true;
        $method = strtoupper($method);
        if (is_string($mode)) $mode = explode(',', $mode);

        $modes = array_map(function ($md) {
            return strtoupper($md);
        }, $mode);

        if (in_array('ALL', $modes)) array_push($modes, 'GET', 'POST', 'CLI');
        if (!$ajax && in_array('HTTP', $modes)) array_push($modes, 'GET', 'POST');
        if ($ajax && in_array('AJAX', $modes)) array_push($modes, 'GET', 'POST');

        return (in_array($method, $modes));
    }
}