<?php

namespace wbf\core;

class Route
{

    /**
     * 路由中心
     */
    public function matching($routes, &$request)
    {
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $method = $_SERVER['REQUEST_METHOD'];
        $directory = root('application');

        $default = [
            'match' => '/\/(?:[\w\-]+\/?)*/',
            'method' => 'all',
            'route' => null,
        ];

        foreach (array_merge($routes, ['_default' => $default]) as $key => &$route) {
            if ($route['match'] == $uri or @preg_match($route['match'], $uri, $matches)) {

                if (isset($route['method']) and !self::method_check($route['method'], $method))
                    exit('禁止的请求方法');

                if (!isset($matches) or $key === '_default') {
                    $matches = explode('/', $uri);
                    $matches[0] = $uri;
                }

                //MVC位置，不含模块
                $directory = isset($route['directory']) ? root($route['directory']) : $directory;
                $directory = rtrim($directory, '/') . '/';

                $mca = (isset($route['route']) and is_array($route['route'])) ? $route['route'] : [];

                //分别获取模块、控制器、动作的实际值
                list($module, $controller, $action) = self::fill_route($directory, $matches, $mca);
                if (!$controller and !$action) continue;

                //分别获取各个指定参数
                if (isset($route['map'])) {
                    foreach ($route['map'] as $mi => $mk) {
                        $matches[$mi] = isset($matches[$mk]) ? $matches[$mk] : null;
//                        unset($matches[$mk]);
                    }
                }
                $request = [
                    'route' => $key,
                    'uri' => $uri,
                    'method' => $method,
                    'directory' => $directory,
                    'module' => $module,
                    'controller' => $controller,
                    'action' => $action,
                    'params' => $matches,
                ];
                return;
            }
        }
        exit('系统路由没有获取到相应内容');
    }


    /**
     * 分别获取模块、控制器、动作的实际值
     * @param array $matches 路由匹配的正则结果集
     * @param $route
     * @return array
     * @throws \Exception
     */
    private static function fill_route($directory, $matches, $route)
    {
        $module = $controller = $action = null;

        //正则结果中没有指定结果集，则都以index返回
        if (empty($matches) or !isset($matches[1])) goto auto;

        //未指定MCA
        if (empty($route)) {
            //在有三个以上匹配的情况下，且第一个是模块名称
            if (isset($matches[3]) and is_dir($directory . $matches[1])) {
                $module = strval($matches[1]);
                $controller = strval($matches[2]);
                $action = strval($matches[3]);
            } else {//否则第一模块指的就是控制器
                $module = null;
                $controller = strval($matches[1]);
                if (isset($matches[2])) $action = strval($matches[2]);
            }
        } else {
            foreach (['module', 'controller', 'action'] as $key) {
                ${$key} = isset($route[$key]) ? $route[$key] : null;
                if (is_numeric(${$key})) {
                    if (!isset($matches[${$key}])) exit("自定义路由规则中需要第{${$key}}个正则结果，实际无此数据。");
                    ${$key} = $matches[${$key}];
                }
            }
        }
        auto:
        return [$module ?: 'index', $controller ?: 'index', $action ?: 'index'];
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
    private static function method_check($mode, $method)
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
            $check = !_AJAX and in_array($method, $modes);

        } elseif ($modes[0] === 'AJAX') {//限AJAX状态
            if (count($modes) === 1) $modes = ['GET', 'POST'];
            $check = _AJAX and in_array($method, $modes);

        } elseif ($modes[0] === 'CLI') {//限CLI
            $check = _CLI;

        } else {
            $check = in_array($method, $modes);
        }
        return $check;
    }
}