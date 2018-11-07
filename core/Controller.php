<?php

namespace esp\core;

class Controller
{

    /**
     * Controller constructor.
     * @param Dispatcher $dispatcher
     * @throws \Exception
     */
//    public function __construct()
//    {
//    }

    /**
     * 检查来路是否本站相同域名
     * 本站_HOST，总是被列入查询，另外自定义更多的host，
     * 若允许本站或空来路，则用：$this->check_host('');
     *
     * @param array ...$host
     * @throws \Exception
     */
    final protected function check_host(...$host)
    {
        if (isset($host[0]) and is_array($host[0])) $host = $host[0];
        if (!in_array(host(Request::getReferer()), array_merge([_HOST], $host))) {
            throw new \Exception('禁止接入', 401);
        }
    }

    private $_runValue = [];

    final public function __set($name, $value)
    {
        $this->_runValue[$name] = $value;
    }

    final public function __get($name)
    {
        return $this->_runValue[$name] ?? null;
    }

    /**
     * 发送订阅，需要在swoole\redis中接收
     * @param string $action
     * @param array $value
     * @return int
     */
    final public function publish(string $action, array $value)
    {
        return Buffer::publish($action, $value);
    }


    final public function setView($value)
    {
        View::setView($value);
    }

    /**
     * 标签解析器
     * @param null $bool
     * @return bool|View
     */
    final protected function getAdapter()
    {
        return View::getAdapter();
    }

    final protected function setAdapter($bool)
    {
        View::setAdapter($bool);
    }

    final protected function setLayout($value)
    {
        View::setLayout($value);
    }

    /**
     * @param null $data
     * @param null $pre
     * @return null
     */
    final public function debug($data = null, $pre = null)
    {
        if (is_null($pre)) $pre = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
        Debug::relay($data, $pre);
        return $this;
    }

    /**
     * 网页跳转
     * @param string $url
     */
    final protected function redirect(string $url)
    {
        header('Expires: ' . gmdate('D, d M Y H:i:s', _TIME - 1) . ' GMT');
        header("Cache-Control: no-cache");
        header("Pragma: no-cache");
        header("Location: {$url}", true, 301);
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
     * @param array ...$param
     * @return bool
     */
    final protected function reload(...$param)
    {
        if (empty($param)) return false;
        $directory = Request::$directory;
        $module = Request::$module;
        $controller = $action = $params = null;

        if (is_dir($param[0])) {
            $directory = root($param[0]) . '/';
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
        if (!is_string($controller)) $controller = Request::$controller;
        if (!is_string($action)) $action = Request::$action;

        //路径，模块，控制器，动作，这四项都没变动，返回false，也就是闹着玩的，不真跳
        if ($directory == Request::$directory
            and $module == Request::$module
            and $controller == Request::$controller
            and $action == Request::$action
        ) return false;

        Request::$directory = $directory;
        Request::$module = $module;

        if ($controller) (Request::$controller = $controller);
        if ($action) (Request::$action = $action);
        if ($params) Request::$params = $params;
        return true;
    }

    /**
     * 向视图送变量
     * @param $name
     * @param $value
     */
    final protected function assign(string $name, $value = null)
    {
        View::assign($name, $value);
    }

    final protected function set(string $name, $value = null)
    {
        View::assign($name, $value);
    }

    final protected function get(string $name)
    {
        return View::get($name);
    }

    final protected function markdown(string $mdFile = null, string $mdCss = '/css/markdown.css?2')
    {
        return $this->md($mdFile, $mdCss);
    }

    final protected function md(string $mdFile = null, string $mdCss = '/css/markdown.css?1')
    {
        $this->css($mdCss);
        return Response::setDisplay('md', $mdFile);
    }

    final protected function html(string $value = null)
    {
        return Response::setDisplay('html', $value);
    }

    final protected function json(array $value)
    {
        return Response::setDisplay('json', $value);
    }

    final protected function title(string $title, bool $default = false)
    {
        View::setTitle($title, $default);
        return $this;
    }

    final protected function php(array $value)
    {
        return Response::setDisplay('php', $value);
    }

    final protected function text(string $value)
    {
        return Response::setDisplay('text', $value);
    }

    final protected function xml($root, $value = null)
    {
        if (is_array($root)) list($root, $value) = [$value ?: 'xml', $root];
        if (is_null($value)) list($root, $value) = ['xml', $root];
        if (!preg_match('/^\w+$/', $root)) $root = 'xml';
        return Response::setDisplay('xml', [$root, $value]);
    }

    final protected function ajax($viewFile)
    {
        if (Request::isAjax()) {
            $this->setLayout(false);
            $this->setView($viewFile);
        }
    }

    /**
     * 设置js引入
     * @param $file
     * @param string $pos
     * @return $this
     */
    final protected function js($file, $pos = 'foot')
    {
        View::setJs($file, $pos);
        return $this;
    }


    /**
     * 设置css引入
     * @param $file
     * @return $this
     */
    final protected function css($file)
    {
        View::setCss($file);
        return $this;
    }


    /**
     * 设置网页meta项
     * @param string $name
     * @param string $value
     * @return $this
     */
    final protected function meta(string $name, string $value)
    {
        View::setMeta($name, $value);
        return $this;
    }


    /**
     * 设置网页keywords
     * @param string $value
     * @return $this
     */
    final protected function keywords(string $value)
    {
        View::setKeywords($value);
        return $this;
    }


    /**
     * 设置网页description
     * @param string $value
     * @return $this
     */
    final protected function description(string $value)
    {
        View::setDescription($value);
        return $this;
    }


    /**
     * 注册关门后操作
     * @param callable $fun
     */
    final protected function shutdown(callable $fun, $parameter = null)
    {
        register_shutdown_function($fun, $parameter);
    }


}