<?php
namespace esp\core;


abstract class Controller
{
    private $_request;
    private $_response;
    private $_plugs;

    final public function __construct(&$plugs, Request &$request, Response &$response)
    {
        $this->_plugs = $plugs;
        $this->_request = $request;
        $this->_response = $response;
    }


    /**
     * 检查来路是否本站相同域名
     * 本站_HOST，总是被列入查询，另外自定义更多的host，
     * 若允许本站或空来路，则用：$this->check_host('');
     *
     * @param array ...$host
     */
    final protected function check_host(...$host)
    {
        if (isset($host[0]) and is_array($host[0])) $host = $host[0];
        if (!in_array(host($this->_request->referer), array_merge([_HOST], $host))) error(Config::get('error.host'));
    }

    /**
     * 设置视图文件，或获取对象
     * @return View|bool
     */
    final public function getView()
    {
        return $this->getResponse()->getView();
    }

    final public function setView($value)
    {
        $this->getResponse()->setView($value);
    }

    /**
     * 标签解析器
     * @param null $bool
     * @return bool|View
     */
    final protected function getAdapter()
    {
        return $this->getResponse()->getView()->getAdapter();
    }

    final protected function setAdapter($bool)
    {
        return $this->getResponse()->getView()->setAdapter($bool);
    }

    /**
     * 关闭，或获取layout对象，可同时指定框架文件
     * @param null $file
     * @return bool|View
     */
    final protected function getLayout()
    {
        return $this->getResponse()->getLayout();
    }

    final protected function setLayout($value)
    {
        $this->getResponse()->setLayout($value);
    }

    /**
     * @return Request
     */
    final protected function getRequest()
    {
        return $this->_request;
    }

    final protected function getResponse()
    {
        return $this->_response;
    }

    final protected function getPlugin($name)
    {
        $name = ucfirst($name);
        return isset($this->_plugs[$name]) ? $this->_plugs[$name] : null;
    }

    final protected function redirect($url)
    {
        header('Location:' . $url, true, 301);
        exit;
    }

    final protected function jump($route)
    {
        return $this->reload($route);
    }

    /**
     * 路径，模块，控制器，动作 间跳转，重新分发
     * TODO 此操作会重新分发，当前Response对象将重新初始化，Controller也会按目标重新加载
     * 若这四项都没变动，则返回false
     * @param array $mvc
     */
    final protected function reload(...$param)
    {
        if (empty($param)) return false;
        $directory = $this->_request->directory;
        $module = $this->_request->module;
        $controller = $action = $params = null;

        if (is_dir($param[0])) {
            $directory = root($param[0], true);
            array_shift($param);
        }
        if (is_dir($directory . $param[0])) {
            $module = $param[0];
            array_shift($param);
        }
        if (count($param) === 1) {
            list($action) = $param;
        } elseif (count($param) === 2) {
            list($controller, $action) = $param;
        } elseif (count($param) > 2) {
            list($controller, $action) = $param;
            $params = array_slice($param, 2);
        }
        if (!is_string($controller)) $controller = $this->_request->controller;
        if (!is_string($action)) $action = $this->_request->action;

        //路径，模块，控制器，动作，这四项都没变动，返回false，也就是闹着玩的，不真跳
        if ($directory == $this->_request->directory
            and $module == $this->_request->module
            and $controller == $this->_request->controller
            and $action == $this->_request->action
        ) return false;

        $this->_request->directory = $directory;
        $this->_request->module = $module;

        if ($controller) ($this->_request->controller = $controller);
        if ($action) ($this->_request->action = $action);
        if ($params) $this->_request->params = $params;
        return $this->_request->loop = true;
    }

    /**
     * 向视图送变量
     * @param $name
     * @param $value
     */
    final protected function assign($name, $value = null)
    {
        $this->_response->assign($name, $value);
    }

    final public function __set($name, $value)
    {
        $this->_response->assign($name, $value);
    }

    final public function __get($name)
    {
        return $this->_response->get($name);
    }

    final protected function set($name, $value = null)
    {
        $this->_response->assign($name, $value);
    }

    final protected function get($name)
    {
        return $this->_response->get($name);
    }

    final protected function html($value = null)
    {
        $this->_response->set_value('html', $value);
    }

    final protected function json(array $value)
    {
        $this->_response->set_value('json', $value);
    }

    final protected function text($value)
    {
        $this->_response->set_value('text', $value);
    }

    final protected function xml($root, $value = null)
    {
        $from = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT | DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
        if (is_array($root)) list($root, $value) = [$value ?: 'xml', $root];
        if (is_null($value)) list($root, $value) = ['xml', $root];
        if (!preg_grep('/^\w+$/', $root)) error('XML根节点只可以是字母与数字的组合', $from);
        $this->_response->set_value('xml', [$root, $value]);
    }


    final protected function js($file, $pos = 'foot')
    {
        $this->_response->js($file, $pos);
        return $this;
    }


    final protected function css($file)
    {
        $this->_response->css($file);
        return $this;
    }


    final protected function meta($name, $value)
    {
        $this->_response->meta($name, $value);
        return $this;
    }


    final protected function title($title, $default = true)
    {
        $this->_response->title($title, $default);
        return $this;
    }


    final protected function keywords($value)
    {
        $this->_response->keywords($value);
        return $this;
    }


    final protected function description($value)
    {
        $this->_response->description($value);
        return $this;
    }


}