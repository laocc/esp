<?php
declare(strict_types=1);

namespace esp\core;

use esp\core\db\Mongodb;
use esp\core\db\Mysql;
use esp\core\db\Redis;
use esp\core\db\Yac;
use esp\error\EspError;
use esp\face\Adapter;
use esp\helper\library\ext\Markdown;
use esp\session\Session;
use esp\dbs\Pool;
use function \esp\helper\host;
use function esp\helper\locked;
use function \esp\helper\root;
use function esp\helper\str_rand;

abstract class Controller
{
    /**
     * @var $_config Configure
     */
    public $_config;
    /**
     * @var $_request Request
     */
    public $_request;
    /**
     * @var $_response Response
     */
    public $_response;
    public $_session;
    public $_plugs;
    public $_cookies;
    public $_debug;
    public $_redis;
    public $_cache;
    public $_counter;

    /**
     * 各种连接池管理器，此对象在Model中自行管理，这里只是起到中转器的作用
     *
     * @var $_pool Pool
     */
    public $_pool;

    /**
     * 以下4个是用于Model中的链接缓存
     * @var $_Yac Yac
     * @var $_Mysql Mysql
     * @var $_Redis Redis
     * @var $_Mongodb Mongodb
     */
    public $_Yac = array();
    public $_Mysql = array();
    public $_Mongodb = array();
    public $_Redis = array();
    public $_PdoPool = array();
    public $_Sqlite = array();

    public function __construct(Dispatcher $dispatcher)
    {
        $this->_config = &$dispatcher->_config;
        $this->_request = &$dispatcher->_request;
        $this->_response = &$dispatcher->_response;
        $this->_counter = &$dispatcher->_counter;
        $this->_session = &$dispatcher->_session;
        $this->_cookies = &$dispatcher->_cookies;
        $this->_debug = &$dispatcher->_debug;
        $this->_plugs = &$dispatcher->_plugs;
        $this->_cache = &$dispatcher->_cache;
        $this->_redis = &$dispatcher->_config->_Redis;
    }

    /**
     * 检查来路是否本站相同域名
     * 本站_HOST，总是被列入查询，另外自定义更多的host，
     * 若允许本站或空来路，则用：$this->check_host('');
     *
     * @param mixed ...$host
     * @throws EspError
     */
    final protected function check_host(...$host)
    {
        if (isset($host[0]) and is_array($host[0])) $host = $host[0];
        if (!in_array(host($this->_request->referer), array_merge([_HOST], $host))) {
            throw new EspError('禁止接入');
        }
    }

    /**
     * 读取Config值
     *
     * @param mixed ...$key
     * @return array|null|string
     */
    final protected function config(...$key)
    {
        return $this->_config->get(...$key);
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


    /**
     * 重新指定视图目录
     *
     * 若以@开头，为系统的绝对目录，注意是否有权限读取
     * 若以/开头，为相对于_ROOT的目录
     *
     * 被指定的目录内，仍要按控制器名称规放置视图文件
     *
     * @param string $value
     * @return $this
     */
    final protected function setViewPath(string $value): Controller
    {
        $this->_response->setViewPath($value);
        return $this;
    }

    /**
     * @return string
     */
    final protected function getViewPath(): string
    {
        return $this->_response->getViewPath();
    }

    /**
     * 强制以某账号运行
     * @param string $user
     * @throws EspError
     */
    final protected function run_user(string $user = 'www')
    {
        if (!_CLI) throw new EspError("run_user 只能运行于cli环境");

        if (getenv('USER') !== $user) {
            $cmd = implode(' ', $GLOBALS["argv"]);
            exit("请以{$user}账号运行：\n\nsudo -u {$user} -g {$user} -s php {$cmd}\n\n\n");
        }
    }

    /**
     * 标签解析器
     * @return Adapter|View
     */
    final protected function getAdapter()
    {
        return $this->_response->getView()->getAdapter();
    }

    final protected function setAdapter(bool $bool)
    {
        return $this->_response->setAdapter($bool)->getView()->setAdapter($bool);
    }

    /**
     * 关闭，或获取layout对象，可同时指定框架文件
     * @return View
     */
    final protected function getLayout(): View
    {
        return $this->_response->getLayout();
    }

    /**
     * 指定layout文件
     * 1，以/开头的绝对路径，查询顺序：
     *      _ROOT/path/file.php
     *      _ROOT/application/_VIRTUAL/views/path/file.php
     *      _ROOT/application/_VIRTUAL/_MODULE/views/path/file.php
     *
     * 2，不是以/开头的，指控制器所在模块下的views目录下，查询顺序：
     *      _ROOT/application/_VIRTUAL/_MODULE/views/path/file.php
     *
     * @param $value
     * @return $this
     */
    final protected function setLayout($value): Controller
    {
        $this->_response->setLayout($value);
        return $this;
    }

    /**
     * 发送通知信息到redis管道，一般要在swoole中接收
     *
     * 建议不同项目定义不同_PUBLISH_KEY
     *
     * 发送指令到后台进程的方法有很多，比如直接用文件中转，但是在多服务器环境下则不适用
     * 多服务器环境下建议用公共redis管道中转(比如阿里云的redis)
     *
     * @param string $action
     * @param array $value
     * @return int
     */
    final public function publish(string $action, array $value): int
    {
        $channel = defined('_PUBLISH_KEY') ? _PUBLISH_KEY : 'REDIS_ORDER';
        return $this->_redis->publish($channel, $action, $value);
    }

    /**
     *
     * 发送到队列，一般不建议在web环境中用队列，根据生产环境测试，经常发生堵塞
     *
     * @param string $action
     * @param array $data
     * @return int
     *
     * 用下面方法读取
     * while ($data = $this->_redis->lPop($queKey)){...}
     */
    final public function queue(string $action, array $data): int
    {
        $key = defined('_QUEUE_TABLE') ? _QUEUE_TABLE : 'REDIS_QUEUE';
        return $this->_redis->push($key, $data + ['_action' => $action]);
    }

    /**
     * 清空当前控制器中db连接池，一般只用在CLI环境下
     * @return $this
     */
    final protected function flush_db_pool(): Controller
    {
        $this->_Yac = [];
        $this->_Mysql = [];
        $this->_Mongodb = [];
        $this->_Redis = [];
        $this->_PdoPool = [];
        $this->_Sqlite = [];
        return $this;
    }

    /**
     * @return Redis
     */
    final public function getRedis(): Redis
    {
        return $this->_redis;
    }

    final public function _redis_flush()
    {
        return $this->_redis->flush();
    }

    /**
     * @return Session
     * @throws EspError
     */
    final public function getSession(): Session
    {
        if (is_null($this->_session)) throw new EspError('当前站点未开启session');
        return $this->_session;
    }

    /**
     * @return Request
     */
    final public function getRequest(): Request
    {
        return $this->_request;
    }

    /**
     * @return Configure
     */
    final public function getConfig(): Configure
    {
        return $this->_config;
    }

    /**
     * @return Response
     */
    final public function getResponse(): Response
    {
        return $this->_response;
    }

    /**
     * @return Resources
     */
    final public function getResource(): Resources
    {
        return $this->_response->getResource();
    }

    /**
     * @param string $name
     * @return null|Plugin
     */
    final public function getPlugin(string $name): ?Plugin
    {
        $name = ucfirst($name);
        return $this->_plugs[$name] ?? null;
    }

    final protected function getCache(): Cache
    {
        return $this->_cache;
    }


    /**
     * @param string $data
     * @param null $pre
     * @return false
     */
    final public function debug($data = '_R_DEBUG_', $pre = null)
    {
        if (_CLI) return false;
        if (is_null($this->_debug)) return null;
        if ($data === '_R_DEBUG_') return $this->_debug;
        if (is_null($pre)) $pre = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
        $this->_debug->relay($data, $pre);
        return $this->_debug;
    }

    /**
     * @param null $data
     * @return bool
     */
    final public function debug_mysql($data = null): ?bool
    {
        if (_CLI) return false;
        if (is_null($this->_debug)) return null;
        if (is_null($data)) return $this->_debug;
        $this->_debug->mysql_log($data);
        return $this->_debug;
    }

    final protected function header(...$kv): Controller
    {
        $this->_response->header(...$kv);
        return $this;
    }

    /**
     * 网页跳转
     * @param string $url
     * @param int $code
     * @return bool
     */
    final public function redirect(string $url, int $code = 302): bool
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
            $pre = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
            $this->_debug->relay(['控制器主动调用 redirect()结束客户端', $url], $pre);
            $this->_debug->save_logs('Controller Redirect');
        }
        exit;
    }

    final protected function exit(string $text = '')
    {
        echo $text;
        fastcgi_finish_request();
        if (!is_null($this->_debug)) {
            $pre = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
            $this->_debug->relay(['控制器主动调用exit()结束客户端', $text], $pre);
            $this->_debug->save_logs('Controller Exit');
        }
        exit;
    }

    /**
     * 冲刷(flush)所有响应的数据给客户端，与exit有所不同，
     * exit：是结束之后所有操作，
     * finish：只是结束客户端，后面所有工作都会执行
     *
     * 此函数冲刷(flush)所有响应的数据给客户端并结束请求。这使得客户端结束连接后，需要大量时间运行的任务能够继续运行。
     * 在mvc结构下，不能在控制器中结束客户端，否则视图不会渲染
     *
     * @param string|null $notes
     * @return bool
     */
    final protected function finish(string $notes = null): bool
    {
        if (!is_null($this->_debug)) {
            $pre = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
            $this->_debug->relay(['控制器主动调用finish()结束客户端', $notes], $pre);
        }
        return fastcgi_finish_request();
    }

    final protected function jump($route): bool
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
    final protected function reload(...$param): bool
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

    final protected function markdown(string $mdValue, bool $addNav = false, bool $addBoth = true): string
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
        return $this;
    }

    /**
     * @param string|null $value
     * @return bool
     */
    final protected function html(string $value = null): bool
    {
        return $this->_response->set_value('html', $value);
    }

    /**
     * @param array $value
     * @return bool
     */
    final protected function json(array $value): bool
    {
        return $this->_response->set_value('json', $value);
    }

    /**
     * @param string $title
     * @param bool $overwrite
     * @return $this
     *
     * $overwrite:
     * 默认null：最终的<title>为 $title + response.title
     * =true：覆盖response.title中的值
     * =false：仅显示 $title
     *
     * 例如：response.title=我的网站
     * 未调用此方法的时候，最终title=我的网站
     * 之后调用：->title('这是文章标题')，最终title为  这是文章标题 - 我的网站
     *
     * 第一次调用：->title('新的名称',true)，即将response.title改为新的名称
     * 之后调用：->title('这是文章标题')，最终title为  这是文章标题 - 新的名称
     */
    final protected function title(string $title, bool $overwrite = null): Controller
    {
        $this->_response->title($title, $overwrite);
        return $this;
    }

    final protected function php(array $value): bool
    {
        return $this->_response->set_value('php', $value);
    }

    final protected function text(string $value): bool
    {
        return $this->_response->set_value('text', $value);
    }

    final protected function image(string $value): bool
    {
        return $this->_response->set_value('image', $value);
    }

    final protected function xml($root, $value = null): bool
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
     * 带锁执行，有些有可能在锁之外会变的值，最好在锁内读取，比如要从数据库读取某个值
     * 如果任务出错，返回字符串表示出错信息，所以正常业务的返回要避免返回字符串
     * 出口处判断如果是字符串即表示出错信息
     *
     * @param string $lockKey 任意可以用作文件名的字符串，同时也表示同一种任务
     * @param callable $callable 该回调方法内返回的值即为当前函数返回值
     * @param mixed ...$args
     * @return null
     */
    public function locked(string $lockKey, callable $callable, ...$args)
    {
        return locked($lockKey, $callable, ...$args);
    }

    /**
     * 主要依赖版本号
     *
     * @return array
     */
    final protected function frameVersion(): array
    {
        $json = file_get_contents(_ROOT . '/composer.lock');
        $json = json_decode($json, true);
        $value = [];
        $value['php'] = phpversion();
        $value['redis'] = phpversion('redis');
        $value['swoole'] = phpversion('swoole');

        foreach ($json['packages'] as $pack) {
            if ($pack['name'] === 'laocc/esp') {
                $value['esp'] = $pack['version'];
            }
        }

        return $value;
    }

    /**
     * 客户端唯一标识
     * @param string $key
     * @param bool $number
     * @return string
     * @throws EspError
     */
    public function cid(string $key = '_SSI', bool $number = false): string
    {
        if (is_null($this->_cookies)) {
            throw new EspError("当前站点未启用Cookies，无法获取CID", 1);
        }

        $key = strtolower($key);
        $unique = $_COOKIE[$key] ?? null;
        if (!$unique) {
            $unique = $number ? mt_rand() : str_rand(20);
            if (headers_sent($file, $line)) {
                $err = ['message' => "Header be Send:{$file}[{$line}]", 'code' => 500, 'file' => $file, 'line' => $line];
                throw new EspError($err);
            }
            $time = time() + 86400 * 365;
            $dom = $this->_cookies->domain;

            if (version_compare(PHP_VERSION, '7.3', '>=')) {
                $option = [];
                $option['domain'] = $dom;
                $option['expires'] = $time;
                $option['path'] = '/';
                $option['secure'] = false;//仅https
                $option['httponly'] = true;
                $option['samesite'] = 'Lax';
                setcookie($key, $unique, $option);
                _HTTPS && setcookie($key, $unique, ['secure' => true] + $option);
            } else {
                setcookie($key, $unique, $time, '/', $dom, false, true);
                _HTTPS && setcookie($key, $unique, $time, '/', $dom, true, true);
            }
        }
        return $unique;
    }


    /**
     * 释放资源，好象没鸟用
     */
    public function ReleaseContent()
    {
        foreach ($this->_Yac as &$obj) $obj = null;
        foreach ($this->_Mysql as &$obj) $obj = null;
        foreach ($this->_Mongodb as &$obj) $obj = null;
        foreach ($this->_Redis as &$obj) $obj = null;
        foreach ($this->_PdoPool as &$obj) $obj = null;
        foreach ($this->_Sqlite as &$obj) $obj = null;
    }

}