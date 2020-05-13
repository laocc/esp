<?php
//declare(strict_types=1);

namespace esp\core;

final class Request
{
    private $_var = Array();
    public $loop = false;//控制器间跳转循环标识
    public $router_path = null;//路由配置目录
    public $router = null;//实际生效的路由器名称
    public $params = Array();

    public $module;
    public $controller;//控制器名
    public $action;
    public $method;
    public $directory;
    public $referer;
    public $uri;
    public $suffix;
    public $route_view;

    public function __construct(array $conf = null)
    {
        $this->method = strtoupper(getenv('REQUEST_METHOD') ?: '');
        if ($this->isAjax() and !$this->isPost()) $this->method = 'AJAX';
        if (!is_array($conf)) $conf = [];
        $conf += ['directory' => '/application'];

        $this->directory = root($conf['directory'] ?? '/directory');
        $this->router_path = root($conf['router'] ?? '/config/routes');
        if (!isset($conf['suffix'])) $conf['suffix'] = array();
        $this->suffix = $conf['suffix'] + ['auto' => 'Action', 'get' => 'Get', 'ajax' => 'Ajax', 'post' => 'Post'];
        $this->referer = _CLI ? null : (getenv("HTTP_REFERER") ?: '');
        $this->uri = _CLI ? //CLI模式下 取参数作为路由
            ('/' . trim(implode('/', array_slice($GLOBALS["argv"], 1)), '/')) :
            parse_url(getenv('REQUEST_URI'), PHP_URL_PATH);
    }

    public function id()
    {
        return getenv('REQUEST_ID') ?: md5(mt_rand() . print_r($_SERVER, true));
    }

    /**
     * 用于缓存组KEY用
     * @return string
     */
    public function getControllerKey()
    {
        return $this->directory . $this->module . $this->controller . $this->action . json_encode($this->params);
    }

    public function __get(string $name)
    {
        return isset($this->_var[$name]) ? $this->_var[$name] : null;
    }

    public function get(string $name)
    {
        return isset($this->_var[$name]) ? $this->_var[$name] : null;
    }

    public function __set(string $name, $value)
    {
        $this->_var[$name] = $value;
    }

    public function set(string $name, $value)
    {
        $this->_var[$name] = $value;
    }

    public function getParams()
    {
        unset($this->params['_plugin_debug']);
        return $this->params;
    }

    public function getParam(string $key)
    {
        return isset($this->params[$key]) ? $this->params[$key] : null;
    }

    public function setParam(string $name, $value)
    {
        $this->params[$name] = $value;
    }


    public function getMethod()
    {
        return $this->method;
    }

    public function isGet()
    {
        return $this->method === 'GET' && strtolower(getenv('HTTP_X_REQUESTED_WITH') ?: '') !== 'xmlhttprequest';
    }

    public function isPost()
    {
        return $this->method === 'POST';
    }

    public function isCli()
    {
        return _CLI;
//        return $this->method === 'CLI';
    }

    public function isAjax()
    {
        return _CLI ? false : strtolower(getenv('HTTP_X_REQUESTED_WITH') ?: '') === 'xmlhttprequest';
    }

    public function agent()
    {
        return getenv('HTTP_USER_AGENT') ?: '';
    }

}