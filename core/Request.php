<?php
namespace esp\core;

final class Request
{
    private $_var = [];
    public $loop = false;//控制器间跳转循环标识
    public $route = null;
    public $params = [];
    public $https;
    public $agent;
    public $ajax;

    public function __construct()
    {
        $this->module = Config::get('esp.module');
        $this->controller = Config::get('esp.controller');
        $this->action = Config::get('esp.action');
        $this->https = strtolower(server('HTTPS')) === 'on';
        $this->agent = server('HTTP_USER_AGENT', '');
        $this->ajax = _CLI ? false : strtolower(server('HTTP_X_REQUESTED_WITH')) === 'xmlhttprequest';

        $this->method = server('REQUEST_METHOD');
        $this->directory = root(Config::get('esp.directory'), true);
        $this->referer = _CLI ? null : server("HTTP_REFERER");
        $this->url = _CLI ? null : (($this->https ? 'https://' : 'http://') . _DOMAIN . server("REQUEST_URI"));
        $this->uri = _CLI ?
            ('/' . trim(implode('/', array_slice($GLOBALS["argv"], 1)), '/')) :
            parse_url(server('REQUEST_URI'), PHP_URL_PATH);
    }

    public function __get($name)
    {
        return isset($this->_var[$name]) ? $this->_var[$name] : null;
    }

    public function get($name)
    {
        return isset($this->_var[$name]) ? $this->_var[$name] : null;
    }

    public function __set($name, $value)
    {
        $this->_var[$name] = $value;
    }

    public function set($name, $value)
    {
        $this->_var[$name] = $value;
    }

    public function getParams()
    {
        return $this->params;
    }

    public function getParam($key)
    {
        return isset($this->params[$key]) ? $this->params[$key] : null;
    }

    public function setParam($name, $value)
    {
        $this->params[$name] = $value;
    }

    public function getMethod()
    {
        return $this->method;
    }

}