<?php
namespace esp\core;

final class Request
{
    private $_var = [];

    public function __construct()
    {
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

    public function __set($name, $value)
    {
        $this->_var[$name] = $value;
    }

}