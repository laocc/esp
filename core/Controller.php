<?php

namespace esp\core;

use esp\core\db\Mongodb;
use esp\core\db\Mysql;
use esp\core\db\Redis;
use esp\core\db\Yac;

class Controller
{
    protected $_request;
    protected $_response;
    protected $_plugs;
    public $_debug;
    public $_buffer;

    /**
     * Controller constructor.
     * @param Dispatcher $dispatcher
     * @throws \Exception
     */
    public function __construct(Dispatcher $dispatcher)
    {
        $this->_plugs = &$dispatcher->_plugs;
        $this->_request = &$dispatcher->_request;
        $this->_response = &$dispatcher->_response;
        $this->_debug = &$dispatcher->_debug;
        $this->_buffer = &$dispatcher->_buffer;
    }

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
        if (!in_array(host($this->_request->referer), array_merge([_HOST], $host))) {
            throw new \Exception('禁止接入', 401);
        }
    }

    /**
     * 发送订阅，需要在swoole\redis中接收
     * @param string $action
     * @param array $value
     * @return int
     */
    final public function publish(string $action, array $value)
    {
        return $this->_buffer->publish($action, $value);
    }

    /**
     * 设置视图文件，或获取对象
     * @return View|bool
     */
    final public function getView()
    {
        return $this->_response->getView();
    }

    final public function setView($value)
    {
        $this->_response->setView($value);
    }

    /**
     * 标签解析器
     * @param null $bool
     * @return bool|View
     */
    final protected function getAdapter()
    {
        return $this->_response->getView()->getAdapter();
    }

    final protected function setAdapter($bool)
    {
        return $this->_response->getView()->setAdapter($bool);
    }

    /**
     * 关闭，或获取layout对象，可同时指定框架文件
     * @param null $file
     * @return bool|View
     */
    final protected function getLayout()
    {
        return $this->_response->getLayout();
    }

    final protected function setLayout($value)
    {
        $this->_response->setLayout($value);
    }

    /**
     * @return Redis
     */
    final public function getBuffer()
    {
        return $this->_buffer;
    }

    /**
     * @return Request
     */
    final public function getRequest()
    {
        return $this->_request;
    }

    /**
     * @return Response
     */
    final public function getResponse()
    {
        return $this->_response;
    }

    /**
     * @param string $name
     * @return null|Plugin
     */
    final public function getPlugin(string $name)
    {
        $name = ucfirst($name);
        return isset($this->_plugs[$name]) ? $this->_plugs[$name] : null;
    }


    /**
     * @param null $data
     * @param null $pre
     * @return Debug|null
     */
    final public function debug($data = null, $pre = null)
    {
        if (is_null($this->_debug)) return null;
        if (is_null($data)) return $this->_debug;
        if (is_null($pre)) $pre = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
        $this->_debug->relay($data, $pre);
        return $this->_debug;
    }

    /**
     * 网页跳转
     * @param string $url
     */
    final protected function redirect(string $url)
    {
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() - 1) . ' GMT');
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
        $directory = $this->_request->directory;
        $module = $this->_request->module;
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
    final protected function assign(string $name, $value = null)
    {
        $this->_response->assign($name, $value);
    }

    final public function __set(string $name, $value)
    {
        $this->_response->assign($name, $value);
    }

    final public function __get(string $name)
    {
        return $this->_response->get($name);
    }

    final protected function set(string $name, $value = null)
    {
        $this->_response->assign($name, $value);
    }

    final protected function get(string $name)
    {
        return $this->_response->get($name);
    }

    final protected function markdown(string $mdFile = null, string $mdCss = '/css/markdown.css?2')
    {
        return $this->md($mdFile, $mdCss);
    }

    final protected function md(string $mdFile = null, string $mdCss = '/css/markdown.css?1')
    {
        $this->css($mdCss);
        return $this->_response->set_value('md', $mdFile);
    }

    final protected function html(string $value = null)
    {
        return $this->_response->set_value('html', $value);
    }

    final protected function json(array $value)
    {
        return $this->_response->set_value('json', $value);
    }

    final protected function title(string $title, bool $default = false)
    {
        $this->_response->title($title, $default);
        return $this;
    }

    final protected function php(array $value)
    {
        return $this->_response->set_value('php', $value);
    }

    final protected function text(string $value)
    {
        return $this->_response->set_value('text', $value);
    }

    final protected function xml($root, $value = null)
    {
        if (is_array($root)) list($root, $value) = [$value ?: 'xml', $root];
        if (is_null($value)) list($root, $value) = ['xml', $root];
        if (!preg_match('/^\w+$/', $root)) $root = 'xml';
        return $this->_response->set_value('xml', [$root, $value]);
    }

    final protected function ajax($viewFile)
    {
        if ($this->getRequest()->isAjax()) {
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
        $this->_response->js($file, $pos);
        return $this;
    }


    /**
     * 设置css引入
     * @param $file
     * @return $this
     */
    final protected function css($file)
    {
        $this->_response->css($file);
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
        $this->_response->meta($name, $value);
        return $this;
    }


    /**
     * 设置网页keywords
     * @param string $value
     * @return $this
     */
    final protected function keywords(string $value)
    {
        $this->_response->keywords($value);
        return $this;
    }


    /**
     * 设置网页description
     * @param string $value
     * @return $this
     */
    final protected function description(string $value)
    {
        $this->_response->description($value);
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


    //=========数据相关===========


    /**
     * @param string $tab
     * @return Yac
     */
    final public function Yac(string $tab = 'tmp')
    {
        static $yac = Array();
        if (!isset($yac[$tab])) {
            $yac[$tab] = new Yac($tab);
            $this->debug("New Yac({$tab});");
        }
        return $yac[$tab];
    }

    /**
     * @param int $dbID
     * @param array $_conf
     * @return Redis
     */
    final public function Redis(int $dbID = 1, array $_conf = [])
    {
        static $redis = array();
        if (!isset($redis[$dbID])) {
            $conf = Config::get('database.redis');
            $redis[$dbID] = new Redis($_conf + $conf, $dbID);
            $this->debug("create Redis({$dbID});");
        }
        return $redis[$dbID];
    }

    /**
     * 创建一个Mysql实例
     * @param int $tranID
     * @param array $_conf 如果要创建一个持久连接，则$_conf需要传入参数：persistent=true，
     * @return Mysql
     * @throws \Exception
     */
    final public function Mysql(int $tranID = 0, array $_conf = [])
    {
        static $mysql = Array();
        if (!isset($mysql[$tranID])) {
            $conf = Config::get('database.mysql');
            if (empty($conf)) {
                throw new \Exception('无法读取Mysql配置信息', 501);
            }
            $mysql[$tranID] = new Mysql($tranID, ($_conf + $conf), $this);
            $this->debug("New Mysql({$tranID});");
        }
        return $mysql[$tranID];
    }


    /**
     * @param string $db
     * @param array $_conf
     * @return Mongodb
     * @throws \Exception
     */
    final public function Mongodb(string $db = 'temp', array $_conf = [])
    {
        static $mongodb = Array();
        if (!isset($mongodb[$db])) {
            $conf = Config::get('database.mongodb');
            if (empty($conf)) {
                throw new \Exception('无法读取mongodb配置信息', 501);
            }
            $mongodb[$db] = new Mongodb($_conf + $conf, $db);
            $this->debug("New Mongodb({$db});");
        }
        return $mongodb[$db];
    }


}