<?php
declare(strict_types=1);

namespace esp\core;

use esp\core\db\Redis;
use esp\core\ext\EspError;
use esp\core\face\Adapter;
use esp\core\ext\Input;
use esp\library\ext\Markdown;

abstract class Controller
{
    protected $_dispatcher;
    protected $_config;
    protected $_request;
    protected $_response;
    protected $_session;
    protected $_plugs;
    protected $_cookies;
    private $_input;
    public $_debug;
    public $_buffer;

    /**
     * 用于post,ajax的返回数据，只要启用此变量，最后_close之后会重新组织返回值
     * 对于get请求无效
     */
    public $result = [];

    /**
     * Controller constructor.
     * @param Dispatcher $dispatcher
     */
    final public function __construct(Dispatcher $dispatcher)
    {
        $this->_dispatcher = &$dispatcher;
        $this->_config = &$dispatcher->_config;
        $this->_plugs = &$dispatcher->_plugs;
        $this->_request = &$dispatcher->_request;
        $this->_response = &$dispatcher->_response;
        $this->_session = &$dispatcher->_session;
        $this->_cookies = &$dispatcher->_cookies;
        $this->_debug = &$dispatcher->_debug;
        $this->_buffer = $this->_config->Redis();
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
        if (!in_array(\esp\helper\host($this->_request->referer), array_merge([_HOST], $host))) {
            throw new EspError('禁止接入', 401);
        }
    }

    /**
     * 读取Config值
     * @param mixed ...$key
     * @return array|null|string
     */
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
    final protected function publish(string $action, $value)
    {
        return $this->_buffer->publish('order', $action, $value);
    }

    /**
     * 发送到队列
     * @param string $queKey
     * @param array $data
     * @return int
     *
     * 用下面方法读取
     * while ($data = $this->_redis->lPop($queKey)){...}
     */
    final public function queue(string $queKey, array $data)
    {
        return $this->_buffer->push('task', $data + ['_action' => $queKey]);
    }

    /**
     * 设置视图文件，或获取对象
     * @return View|bool
     */
    final protected function getView()
    {
        return $this->_response->getView();
    }

    final protected function setView($value): Controller
    {
        $this->_response->setView($value);
        return $this;
    }

    final protected function setViewPath(string $value): Controller
    {
        $this->_response->viewPath($value);
        return $this;
    }

    final protected function getViewPath()
    {
        return $this->_response->viewPath();
    }

    final protected function run_user(string $user = 'www')
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

    final protected function setLayout($value): Controller
    {
        $this->_response->setLayout($value);
        return $this;
    }

    /**
     * @return Input
     */
    final protected function input()
    {
        if (is_null($this->_input)) {
            $this->_input = new Input();
        }
        return $this->_input;
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
        if (is_null($this->_session)) throw new EspError('当前站点未开启session', 401);
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
     * 构造一个Debug空类
     */
    final private function anonymousDebug()
    {
        return new class()
        {
            public function relay(...$a)
            {
            }

            public function __call($name, $arguments)
            {
                // TODO: Implement __call() method.
            }
        };
    }

    /**
     * @param string $data
     * @param null $pre
     * @return bool|Debug|__anonymous@6848
     */
    final public function debug($data = '_R_DEBUG_', $pre = null)
    {
        if (_CLI) return false;
        if (is_null($this->_debug)) return $this->anonymousDebug();
        if ($data === '_R_DEBUG_') return $this->_debug;
        if (is_null($pre)) $pre = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
        $this->_debug->relay($data, $pre);
        return $this->_debug;
    }

    /**
     * @param null $data
     * @return bool|Debug|__anonymous@6848
     */
    final public function debug_mysql($data = null)
    {
        if (_CLI) return false;
        if (is_null($this->_debug)) return $this->anonymousDebug();
        if (is_null($data)) return $this->_debug;
        $this->_debug->mysql_log($data);
        return $this->_debug;
    }

    final public function buffer_flush()
    {
        return $this->_buffer->flush();
    }

    final protected function header(...$kv): Controller
    {
        $this->_response->header(...$kv);
        return $this;
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
            !is_null($this->_debug) && $this->_debug->relay([
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
        exit;
    }

    final protected function exit(string $text = '')
    {
        echo $text;
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
            $directory = \esp\helper\root($param[0]) . '/';
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
     * @param null $value
     * @return $this
     */
    final protected function assign($name, $value = null): Controller
    {
        if (_CLI) return $this;
        $this->_response->assign($name, $value);
        return $this;
    }

    final protected function markdown(string $mdValue, bool $addNav = false, bool $addBoth = true)
    {
        if (stripos($mdValue, _ROOT) === 0) {
            $mdValue = file_get_contents($mdValue);
        }
        return Markdown::html($mdValue, $addNav, $addBoth);
    }

    /**
     * @param null $mdFile
     * @param string $mdCss
     * @return $this
     * @throws \Exception
     */
    final protected function md($mdFile = null, string $mdCss = '/css/markdown.css?1')
    {
        if (is_array($mdFile)) {
            $this->_response->setMarkDown($mdFile);
            return $this;
        }
        $this->css($mdCss);
        if ($mdFile) $this->_response->setView($mdFile);
        $this->_response->set_value('md', null);
    }

    /**
     * @param string|null $value
     * @return bool
     */
    final protected function html(string $value = null)
    {
        return $this->_response->set_value('html', $value);
    }

    /**
     * @param array $value
     * @return bool
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
    final protected function title(string $title, bool $default = false): Controller
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

    final protected function ajax($viewFile): Controller
    {
        if ($this->getRequest()->isAjax()) {
            $this->setLayout(false);
            $this->setView($viewFile);
        }
        return $this;
    }

    /**
     * 设置js引入
     * @param $file
     * @param string $pos
     * @return $this
     */
    final protected function js($file, $pos = 'foot'): Controller
    {
        $this->_response->js($file, $pos);
        return $this;
    }


    /**
     * 设置css引入
     * @param $file
     * @return $this
     */
    final protected function css($file): Controller
    {
        $this->_response->css($file);
        return $this;
    }

    final protected function concat(bool $run): Controller
    {
        $this->_response->concat($run);
        return $this;
    }

    final protected function render(bool $run): Controller
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
    final protected function meta(string $name, string $value): Controller
    {
        $this->_response->meta($name, $value);
        return $this;
    }


    /**
     * 设置网页keywords
     * @param string $value
     * @return $this
     */
    final protected function keywords(string $value): Controller
    {
        $this->_response->keywords($value);
        return $this;
    }


    /**
     * 设置网页description
     * @param string $value
     * @return $this
     */
    final protected function description(string $value): Controller
    {
        $this->_response->description($value);
        return $this;
    }

    final protected function cache(bool $save = true): Controller
    {
        $this->_response->cache($save);
        return $this;
    }

    /**
     * 注册关门后操作
     * 先注册的先执行，后注册的后执和，框架最后还有debug保存
     * @param callable $fun
     * @param mixed ...$params
     * @return Controller
     */
    final protected function shutdown(callable $fun, ...$params): Controller
    {
        register_shutdown_function($fun, ...$params);
        return $this;
    }


    /**
     * @param $return
     * @return array|null
     *
     * 控制器返回约定：
     * string:错误信息
     * int:success值，APP据此值处理
     *
     * 302或router：进入指定URI
     * 500或reload：重启APP
     * 505或warn：出错
     * 400或login：进入登录页面
     */
    final public function ReorganizeReturn($return)
    {
        $value = &$this->result;
        if (empty($value)) return null;
        if (!is_array($value)) $value = ['data' => $value];

        if (is_string($return)) {
            $value = ['success' => 0, 'message' => $return] + $value;

        } else if (is_int($return)) {
            $value = ['success' => 0, 'message' => strval($return)] + $value;

        } else if (is_array($return)) {
            $value = $return + $value + ['success' => 1, 'message' => 'OK'];

        } else if (is_float($return)) {
            $value += ['success' => 1, 'message' => strval($return)];

        } else if ($return === true) {
            $value += ['success' => 1, 'message' => 'True'];

        } else if ($return === false) {
            $value += ['success' => 0, 'message' => 'False'];

        } else {
            $value += ['success' => 1, 'message' => 'OK'];
        }

        return $value;
    }

    final protected function Result(string $name, $value): Controller
    {
        $this->result[$name] = $value;
        return $this;
    }

}