<?php
namespace esp\core;

final class Request
{
    private $_var = [];
    public $route = null;
    public $params = [];

    public function __construct()
    {
        $this->module = Config::get('esp.module');
        $this->controller = Config::get('esp.controller');
        $this->action = Config::get('esp.action');

        $this->method = server('REQUEST_METHOD');
        $this->directory = root(Config::get('esp.directory'), true);
        $this->referer = _CLI ? null : server("HTTP_REFERER");
        $this->url = _CLI ? null : ((_HTTPS ? 'https://' : 'http://') . _DOMAIN . server("REQUEST_URI"));
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

}