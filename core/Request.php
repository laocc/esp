<?php

namespace esp\core;

final class Request
{
    private static $method;//请求方式
    private static $_var = Array();//临时数据

    public $loop = false;//控制器间跳转循环标识

    public static $module;
    public static $controller;//控制器名
    public static $action;
    public static $params = Array();
    public static $router = null;//实际生效的路由器名称

    public static $directory;
    private static $uri;
    public static $suffix;

    public static function _init(array &$conf)
    {
        self::$method = strtoupper(getenv('REQUEST_METHOD'));
        if (self::isAjax()) self::$method = 'AJAX';
        self::$suffix = ($conf['suffix'] ?? []) + ['get' => 'Action', 'ajax' => 'Ajax', 'post' => 'Post'];

        self::$directory = root($conf['directory'] ?? '/directory');
        self::$uri = _CLI ? //CLI模式下 取参数作为路由
            ('/' . trim(implode('/', array_slice($GLOBALS["argv"], 1)), '/')) :
            parse_url(getenv('REQUEST_URI'), PHP_URL_PATH);
    }

    public static function getActionPath()
    {
        $method = strtolower(self::$method);
        return '/' . Request::$module . '/' . Request::$controller . '/' . Request::$action . ucfirst($method);
    }

    public static function get(string $name)
    {
        return isset(self::$_var[$name]) ? self::$_var[$name] : null;
    }

    public static function set(string $name, $value)
    {
        self::$_var[$name] = $value;
    }

    public static function getUri()
    {
        return self::$uri;
    }

    public static function getParams()
    {
        return self::$params;
    }

    public static function getParam(string $key)
    {
        return isset(self::$params[$key]) ? self::$params[$key] : null;
    }

    public static function getMethod(): string
    {
        return self::$method;
    }

    public static function isGet(): bool
    {
        return self::$method === 'GET';
    }

    public static function isPost(): bool
    {
        return self::$method === 'POST';
    }

    public static function isCli(): bool
    {
        return self::$method === 'CLI';
    }

    public static function isAjax(): bool
    {
        return _CLI ? false : strtolower(getenv('HTTP_X_REQUESTED_WITH')) === 'xmlhttprequest';
    }

    public static function getAgent(): string
    {
        return getenv('HTTP_USER_AGENT') ?: '';
    }

    public static function getReferer(): string
    {
        return _CLI ? '' : (getenv("HTTP_REFERER") ?: '');
    }

}