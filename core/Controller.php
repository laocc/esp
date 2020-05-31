<?php
//declare(strict_types=1);

namespace esp\core;

use esp\core\db\Redis;
use esp\core\face\Adapter;

abstract class Controller
{
    protected $_config;
    protected $_request;
    protected $_response;
    protected $_session;
    protected $_plugs;
    protected $_system;
    public $_debug;
    public $_buffer;

    /**
     * Controller constructor.
     * @param Dispatcher $dispatcher
     * @throws \Exception
     */
    public function __construct(Dispatcher $dispatcher)
    {
        $this->_config = &$dispatcher->_config;
        $this->_plugs = &$dispatcher->_plugs;
        $this->_request = &$dispatcher->_request;
        $this->_response = &$dispatcher->_response;
        $this->_session = &$dispatcher->_session;
        $this->_debug = &$dispatcher->_debug;
        $this->_buffer = $this->_config->Redis();
        $this->_system = defined('_SYSTEM') ? _SYSTEM : 'auto';
        if (_CLI) return;

        $this->_response->assign('_config', function (string $key) {
            return $this->_config->get($key);
        });

        if (defined('_DEBUG_PUSH_KEY')) {
            register_shutdown_function(function (Request $request) {
                //发送访问记录到redis队列管道中，后面由cli任务写入数据库
                $debug = [];
                $debug['time'] = time();
                $debug['system'] = _SYSTEM;
                $debug['virtual'] = _VIRTUAL;
                $debug['module'] = $request->module;
                $debug['controller'] = $request->controller;
                $debug['action'] = $request->action;
                $debug['method'] = $request->method;
                $this->_buffer->push(_DEBUG_PUSH_KEY, $debug);
            }, $this->_request);
        }
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

    final protected function config(...$key)
    {
        return $this->_config->get(...$key);
    }

    /**
     * 发送订阅，需要在swoole\redis中接收
     * @param string $action
     * @param $value
     * @return int
     */
    final public function publish(string $action, $value)
    {
        $channel = $this->_config->get('app.dim.channel');
        if (!$channel) $channel = 'order';
        return $this->_buffer->publish($channel, $action, $value);
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

    final public function setViewPath(string $value)
    {
        $this->_response->viewPath($value);
    }

    final public function run_user(string $user = 'www')
    {
        if (getenv('USER') !== $user) {
            $cmd = implode(' ', $GLOBALS["argv"]);
            exit("请以www账户运行，CLI模式下请用\n\nsudo -u {$user} -g {$user} -s php {$cmd}\n\n\n");
        }
    }

    /**
     * 标签解析器
     * @return bool|View|Adapter
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
     * @return Session
     * @throws \Exception
     */
    final public function getSession()
    {
        if (is_null($this->_session)) throw new \Exception('当前站点未开启session', 401);
        return $this->_session;
    }

    /**
     * @return Request
     */
    final public function getRequest()
    {
        return $this->_request;
    }

    /**
     * @return Configure
     */
    final public function getConfig()
    {
        return $this->_config;
    }

    /**
     * @return Response
     */
    final public function getResponse()
    {
        return $this->_response;
    }

    /**
     * @param $type
     * @param $val
     * @param null $color
     * @return string|array
     * $color 可以直接指定为一个数组
     * $color=true 时，无论是否有预定义的颜色，都返回全部内容
     * $color=false 时，不返回预定义的颜色
     */
    final public function state($type, $val, $color = null)
    {
        if ($val === '' or is_null($val)) return '';
        $value = $this->_config->get("app.state.{$type}.{$val}") ?: $val;
        if ($color === true) return $value;
        if (strpos($value, ':')) {
            $pieces = explode(':', $value);
            if (is_array($color)) return $pieces;
            if ($color === false) return $pieces[0];
            return "<span class='v{$val}' style='color:{$pieces[1]}'>{$pieces[0]}</span>";
        }
        if (empty($color)) return $value;
        if (!isset($color[$val])) return $value;
        return "<span class='v{$val}' style='color:{$color[$val]}'>{$value}</span>";
    }

    /**
     * @return Resources
     */
    final public function getResource()
    {
        return $this->_response->getResource();
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
     * @param string $modName
     * @param mixed ...$param
     * @return mixed
     * @throws \Exception
     */
    final public function Model(string $modName, ...$param)
    {
        static $model = [];
        $modName = ucfirst($modName);
        if (isset($model[$modName])) return $model[$modName];

        $base = "/models/main/Base.php";
        if (is_readable($base)) load($base);

        if (!load("/models/main/{$modName}.php")) {
            throw new \Exception("[{$modName}Model] don't exists", 404);
        }

        $mod = '\\models\\main\\' . $modName . 'Model';
        $model[$modName] = new $mod($this, ...$param);
        if (!$model[$modName] instanceof Model) {
            throw new \Exception("{$modName} 须继承自 \\esp\\core\\Model", 404);
        }
        return $model[$modName];
    }

    /**
     * @param string $data
     * @param null $pre
     * @return bool|Debug|EmptyClass
     */
    final public function debug($data = '_R_DEBUG_', $pre = null)
    {
        if (_CLI) return false;
        if (is_null($this->_debug)) return new EmptyClass();
//        if (is_null($data)) return $this->_debug;
        if ($data === '_R_DEBUG_') return $this->_debug;
        if (is_null($pre)) $pre = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
        $this->_debug->relay($data, $pre);
        return $this->_debug;
    }

    /**
     * @param null $data
     * @return bool|Debug|EmptyClass
     */
    final public function debug_mysql($data = null)
    {
        if (_CLI) return false;
        if (is_null($this->_debug)) return new EmptyClass();
        if (is_null($data)) return $this->_debug;
        $this->_debug->mysql_log($data);
        return $this->_debug;
    }

    final public function buffer_flush()
    {
        return $this->_buffer->flush();
    }

    final protected function header(...$kv): void
    {
        $this->_response->header(...$kv);
    }

    /**
     * 冲刷(flush)所有响应的数据给客户端
     * 此函数冲刷(flush)所有响应的数据给客户端并结束请求。这使得客户端结束连接后，需要大量时间运行的任务能够继续运行。
     * @return bool
     */
    final protected function finish()
    {
        $this->debug('fastcgi_finish_request');
        return fastcgi_finish_request();
    }

    /**
     * 网页跳转
     * @param string $url
     * @param int $code
     * @return bool
     */
    final protected function redirect(string $url, int $code = 302)
    {
        if (headers_sent($filename, $line)) {
            $this->_debug->relay([
                    "header Has Send:{$filename}({$line})",
                    'headers' => headers_list()]
            )->error("页面已经输出过");
            return false;
        }
        $this->_response->redirect("Location: {$url} {$code}");
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() - 1) . ' GMT');
        header("Cache-Control: no-cache");
        header("Pragma: no-cache");
        header("Location: {$url}", true, $code);
        fastcgi_finish_request();
        if (!is_null($this->_debug)) {
            register_shutdown_function(function () {
                $this->_debug->save_logs('Controller Redirect');
            });
        }
//        return true;
        exit;
    }

    final protected function exit(string $route = '')
    {
        echo $route;
        fastcgi_finish_request();
        if (!is_null($this->_debug)) {
            register_shutdown_function(function () {
                $this->_debug->save_logs('Controller Exit');
            });
        }
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

        if (is_string($param[0]) and is_dir($param[0])) {
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
//        var_dump([$directory,$module,$controller,$action]);

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
     * @param null $value
     * @return $this
     */
    final protected function assign($name, $value = null)
    {
        $this->_response->assign($name, $value);
        return $this;
    }

    final public function __set(string $name, $value)
    {
        $this->_response->assign($name, $value);
        return $this;
    }

    final public function __get(string $name)
    {
        return $this->_response->get($name);
    }

    final protected function set(string $name, $value = null)
    {
        $this->_response->assign($name, $value);
        return $this;
    }

    final protected function get(string $name)
    {
        return $this->_response->get($name);
    }

    final protected function markdown(string $mdFile = null, string $mdCss = '/css/markdown.css?2')
    {
        $this->css($mdCss);
        if ($mdFile) {
            $this->_response->setView($mdFile);
        } else {
            $this->_response->set_value('md', $mdFile);
        }
    }

    /**
     * @param string|null $mdFile
     * @param string $mdCss
     * @throws \Exception
     */
    final protected function md(string $mdFile = null, string $mdCss = '/css/markdown.css?1')
    {
        $this->css($mdCss);
        if ($mdFile) {
            $this->_response->setView($mdFile);
        } else {
            $this->_response->set_value('md', $mdFile);
        }
    }

    /**
     * @param string|null $value
     * @return bool
     * @throws \Exception
     */
    final protected function html(string $value = null)
    {
        return $this->_response->set_value('html', $value);
    }

    /**
     * @param array $value
     * @return bool
     * @throws \Exception
     */
    final protected function json(array $value)
    {
        return $this->_response->set_value('json', $value);
    }

    /**
     * @param string $title
     * @param bool $default
     * @return $this
     */
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

    final protected function image(string $value)
    {
        return $this->_response->set_value('image', $value);
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

    final protected function concat(bool $run)
    {
        $this->_response->concat($run);
        return $this;
    }

    final protected function render(bool $run)
    {
        $this->_response->autoRun($run);
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

    final protected function cache(bool $save = true)
    {
        $this->_response->cache($save);
        return $this;
    }

    /**
     * 注册关门后操作
     * 先注册的先执行，后注册的后执和，框架最后还有debug保存
     * @param callable $fun
     * @param mixed ...$parameter
     */
    final public function shutdown(callable $fun, ...$parameter)
    {
        register_shutdown_function($fun, ...$parameter);
    }


}