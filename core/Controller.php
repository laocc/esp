<?php
declare(strict_types=1);

namespace esp\core;

use Redis;
use esp\dbs\Pool;
use esp\debug\Counter;
use esp\debug\Debug;
use esp\face\Adapter;
use esp\http\Rpc;
use esp\helper\library\ext\Markdown;
use function esp\helper\_echo;
use function esp\helper\host;
use function esp\helper\numbers;
use function esp\helper\root;

abstract class Controller
{
    public Configure $_config;
    public Request $_request;
    public Response $_response;
    public Dispatcher $_dispatcher;
    public Redis $_redis;//config中创建的redis实例
    public Pool $_pool;//用于esp/dbs里的Pool池管理，此对象在library中会被创建为Pool对象

    public ?Cookies $_cookies;
    public ?Cache $_cache;
    public ?Handler $_error;
    public ?Counter $_counter;
    public ?Debug $_debug;
    public array $_plugs;
    public string $enumKey = 'enum';

    /**
     * 空数组，用于程序运行过程中保存全局统一、唯一的变量
     * 如果最后要对这个中转变量进行处理，建议在控制器的_close中进行，因为这是相对每个进程唯一的收关动作
     * Library的析构函数并不具有唯一性。
     */
    public array $tempData = [];

    public function __construct(Dispatcher $dispatcher)
    {
        $this->_dispatcher = &$dispatcher;
        $this->_config = &$dispatcher->_config;
        $this->_redis = &$dispatcher->_config->_Redis;
        $this->_request = &$dispatcher->_request;
        $this->_plugs = &$dispatcher->_plugs;

        if (_CLI) return;
        $this->_debug = &$dispatcher->_debug;
        $this->_response = &$dispatcher->_response;
        $this->_counter = &$dispatcher->_counter;
        $this->_cookies = &$dispatcher->_cookies;
        $this->_cache = &$dispatcher->_cache;
        $this->_error = &$dispatcher->_error;
    }

    /**
     * 向视图发送读取enum方法
     * $hide，只允许整型，或数组。整型时：>0表示只显示这些值，<0表示剔除这些值
     * @param string $funName
     * @return void
     */
    final public function setEnum(string $funName = 'enum')
    {
        $this->assign($funName, function (string $key, $hide = null) {
            if (strpos($key, '.') === false) {
                $val = $this->config("{$this->enumKey}.{$key}");
                if (!$val) return json_encode([0 => "{$this->enumKey}.{$key}.未定义"]);
            } else {
                $val = $this->config($key);
                if (!$val) return json_encode([0 => "{$key}.未定义1"]);
            }
            if (is_string($val)) $val = $this->config("{$this->enumKey}.{$val}");
            if (!$val) return json_encode([0 => "{$this->enumKey}{$key}映射目标{$val}不存在"]);

            if ($hide) {
                $unset = false;
                if (is_int($hide)) {
                    if ($hide < 0) {
                        $unset = true;
                        $hide = abs($hide);
                    }
                    $hide = numbers($hide);
                } else if (!is_array($hide)) return [0 => 'hide只能是int或array'];
                if ($unset) {
                    foreach ($hide as $k) unset($val[$k]);
                } else {
                    //使用键名比较计算数组的交集 交换数组中的键和值
                    return json_encode((array_intersect_key($val, array_flip($hide))), 320);
                }
            }
            return json_encode($val, 320);
        });
    }

    /**
     * 查询enum值
     *
     * @param string $type
     * @param $value
     * @param null $hide 只允许整型，或数组。整型时：>0表示只显示这些值，<0表示剔除这些值
     * @return array|string|null
     *
     * $value 若是不可拆分的数字，要用字串型传入
     * $value = null 时，返回$type完整值
     */
    final public function enum(string $type, $value, $hide = null)
    {
        $confKey = $type;
        if (strpos($type, '.') === false) $confKey = "{$this->enumKey}.{$type}";
        $enum = $this->config($confKey);
        if (!$enum) exit("{$confKey}没有此项置");

        if (is_string($enum)) {
            if (strpos($enum, '+') > 0) {
                $eKey = explode('+', $enum);
                $enum = [];
                foreach ($eKey as $key) {
                    $tVal = $this->config("{$this->enumKey}.{$key}");
                    if (!$tVal) exit("{$this->enumKey}没有{$key}的映射设置");
                    $enum = $enum + $tVal;
                }

            } else {
                $enum = $this->config("{$this->enumKey}.{$enum}");
                if (!$enum) exit("{$this->enumKey}没有{$type}的映射设置");
            }
        }

        if (is_int($value)) {
            if ($value < 0) {
                $value = max(numbers(abs($value)));
            } else {
                $value = numbers($value);
            }
        }

        $unset = false;
        if ($hide) {
            if (is_int($hide)) {
                if ($hide < 0) {
                    $unset = true;
                    $hide = abs($hide);
                }
                $hide = numbers($hide);
            } else if (!is_array($hide)) {
                $hide = [$hide];
            }
        }

        if (is_array($value)) {
            $value = array_map(function ($v) use ($enum) {
                return $enum[$v] ?? null;
            }, $value);
            if ($hide) {
                if ($unset) foreach ($hide as $k) unset($value[$k]);
                else $value = json_encode((array_intersect_key($value, array_flip($hide))), 320);
            }
            return implode(',', $value);
        }

        if ($hide) {
            if ($unset) {
                foreach ($hide as $k) unset($enum[$k]);
            } else {
                $enum = json_encode((array_intersect_key($enum, array_flip($hide))), 320);
            }
        }
        if (is_null($value)) return $enum;

        return $enum[$value] ?? null;
    }


    /**
     * 检查来路是否本站相同域名
     * 本站_HOST，总是被列入查询，另外自定义更多的host，
     * 若允许本站或空来路，则用：$this->check_host('');
     *
     * @param mixed ...$host
     */
    final protected function check_host(...$host)
    {
        if (isset($host[0]) and is_array($host[0])) $host = $host[0];
        if (!in_array(host($this->_request->referer), array_merge([_HOST], $host))) {
            exit('禁止接入');
        }
    }

    /**
     * 创建一个RPC对像
     *
     * @param array $conf
     * @return Rpc
     */
    public function rpc(array $conf = []): Rpc
    {
        return new Rpc($conf);
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
     * @return View
     */
    final protected function getView(): View
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
     */
    final protected function run_user(string $user = 'www')
    {
        if (!_CLI) esp_error('Controller', "run_user 只能运行于cli环境");

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

    final protected function setAdapter(bool $bool): View
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
     * @param  $message
     * @return bool
     */
    final public function publish(string $action, $message): bool
    {
        if (!isset($this->_redis)) esp_error('Controller Publish', '站点未启用redis，无法发送订阅消息');
        $value = [];
        $value['action'] = $action;
        $value['message'] = $message;
        $channel = defined('_PUBLISH_KEY') ? _PUBLISH_KEY : 'REDIS_ORDER';
        return (boolean)$this->_redis->publish($channel, serialize($value));
    }

    /**
     * 发布一个后台任务，这是由redis发布的，如果数据很多且不能丢失，最好用async
     * 需另外在后台执行的swoole中实现 /readme/22.task.md中的示例代码
     * _taskPlan_ 是专用词，程序中不可以直接publish时用此关键词
     * 也可以在自己的程序中自行实现此方法
     *
     * @param string $taskKey
     * @param array $args
     * @param int $after
     * @return bool
     */
    final public function task(string $taskKey, array $args, int $after = 0): bool
    {
        $data = [
            'action' => $taskKey,
            'after' => $after,
            'params' => $args
        ];
        $taskKey = str_replace(['->', '::'], '.', $taskKey);
        if (strpos($taskKey, '.') > 0) {
            $key = explode('.', $taskKey);
            $data['class'] = $key[0];
            $data['action'] = $key[1];
        }
        $pubKey = defined('_TASK_KEY_') ? _TASK_KEY_ : '_TASK_KEY_';
        return $this->publish($pubKey, $data);
    }

    /**
     * 保存后台任务到/async，需要另外实现读取并执行的程序，也就是调用下面asyncIterator
     * 不建议用这个方法，如果要实现队列，建议用redis->queue队列
     * 而且，这个方法只限于前后端在同一服务器
     * 不过此方法的优点：可以指定时间执行，基于文件缓存稳定性较高
     *
     * @param string $taskKey
     * @param array $args
     * @param int $runTime
     * @return false
     */
    final public function async(string $taskKey, array $args, int $runTime = 0): bool
    {
        $now = microtime(true);
        if ($runTime < 1000000000) $runTime = $now + $runTime;
        $data = ['key' => $taskKey, 'args' => $args, 'time' => $runTime];
        $file = $now . '.' . getenv('REQUEST_ID') . '.log';
        return (bool)file_put_contents(_RUNTIME . "/async/{$file}", serialize($data));
    }

    /**
     * 迭代目录
     *
     * @param callable $fun
     * @param bool $unlink 是否自动删除文件，若=true自动删除，或在callable里返回===true也可以自动删除
     * @return void
     *
     * 建议在callable里返回true值进行删除，若未返回true表示事务未执行完
     *
     */
    final public function asyncIterator(callable $fun, bool $unlink = false)
    {
        $dir = new \DirectoryIterator($path = (_RUNTIME . '/async/'));
        foreach ($dir as $f) {
            if ($f->isDot() or $f->isDir()) continue;
            $name = $path . $f->getFilename();
            $data = unserialize(file_get_contents($name));
            if ($data['time'] <= microtime(true)) {
                $run = $fun($data['key'], $data['args'], $name);
                if ($run === true or $unlink) @unlink($name);
            }
        }
    }

    /**
     * 侦听redis管道，此方法一般只用在CLI环境下
     *
     * @param callable $callable 回调有三个参数：$redis, $channel, $msg
     * @return void
     *
     * 回调参数 $callable($redis, $channel, $msg)
     */
    final public function subscribe(callable $callable): void
    {
        if (!isset($this->_redis)) esp_error('Controller Subscribe', '站点未启用redis，无法接收订阅消息');
        $channel = defined('_PUBLISH_KEY') ? _PUBLISH_KEY : 'REDIS_ORDER';
        $this->_redis->subscribe([$channel], $callable);
    }

    /**
     *
     * 发送到队列，一般不建议在web环境中用队列，根据生产环境测试，经常发生堵塞
     *
     * @param string $action
     * @param array $data
     * @return bool
     *
     * 用下面方法读取
     * while ($data = $this->_redis->lPop($queKey)){...}
     */
    final public function queue(string $action, array $data): bool
    {
        if (!isset($this->_redis)) esp_error('Controller Queue', '站点未启用redis，无法发送队列消息');
        $key = defined('_QUEUE_TABLE') ? _QUEUE_TABLE : 'REDIS_QUEUE';
        return (boolean)$this->_redis->rPush($key, $data + ['_action' => $action]);
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
     * @param $data
     * @param int $lev
     * @return Debug|false|null
     */
    final public function debug($data = '_R_DEBUG_', int $lev = 1)
    {
        if (_CLI) return false;
        if (!isset($this->_debug)) return null;
        if ($data === '_R_DEBUG_') return $this->_debug;
        return $this->_debug->relay($data, $lev + 1);
//        return $this->_dispatcher->debug($data, $lev + 1);
    }

    /**
     * @param $data
     * @param int $lev
     */
    final public function error($data, int $lev = 1): void
    {
        if (_CLI) return;
        if (!isset($this->_debug)) return;
//        $this->_dispatcher->error($data, $lev + 1);
        $this->_debug->error($data, $lev + 1);
    }

    /**
     * @param $data
     * @param int $lev
     */
    final public function debug_mysql($data, int $lev = 1): void
    {
        if (_CLI) return;
        if (!isset($this->_debug)) return;
        $this->_debug->mysql_log($data, $lev + 1);
//        $this->_dispatcher->debug_mysql($data, $lev + 1);
    }

    /**
     * @param ...$kv
     * @return $this
     */
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
            if (isset($this->_debug)) {
                $this->_debug->relay(
                    [
                        "header Has Send:{$filename}({$line})",
                        'headers' => headers_list()],
                    -1,
                    [
                        'file' => $filename,
                        'line' => $line
                    ]
                )->error("页面已经输出过");
            }
            return false;
        }
        $this->_response->redirect("Location: {$url} {$code}");
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() - 1) . ' GMT');
        header("Cache-Control: no-cache");
        header("Pragma: no-cache");
        header("Location: {$url}", true, $code);
        fastcgi_finish_request();
        if (isset($this->_debug)) {
            $this->_debug->relay(['控制器主动调用 redirect()结束客户端', $url], 1);
            $this->_debug->save_logs('Controller Redirect');
        }
        exit;
    }

    final protected function exit($text = null)
    {
        if (is_array($text)) $text = json_encode($text, 320);
        echo strval($text);
        fastcgi_finish_request();
        if (isset($this->_debug)) {
            $this->_debug->relay(['控制器主动调用exit()结束客户端', $text], 1);
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
        if (isset($this->_debug)) {
            $this->_debug->relay(['控制器主动调用finish()结束客户端', $notes], 1);
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

    /**
     * @param string $mdValue
     * @param bool $addNav
     * @param bool $addBoth
     * @return string
     */
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
    final protected function md($mdFile = null, string $mdCss = '/css/markdown.css?1'): Controller
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
     * @param callable $callable
     * @param ...$params
     * @return bool
     */
    final protected function shutdown(callable $callable, ...$params): bool
    {
        return $this->_dispatcher->shutdown($callable, ...$params);
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
    final public function locked(string $lockKey, callable $callable, ...$args)
    {
        return $this->_dispatcher->locked($lockKey, $callable, ...$args);
    }

    /**
     * 注册屏蔽的错误
     *
     * 例：$this->ignoreError(__FILE__, __LINE__ + 1);
     * 是指屏蔽下一行的错误
     *
     * @param string $file
     * @param int $line
     * @return $this
     */
    final function ignoreError(string $file, int $line): Controller
    {
        $this->_dispatcher->ignoreError($file, $line);
        return $this;
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
     */
    public function cid(string $key = '_SSI', bool $number = false): string
    {
        if (!isset($this->_cookies)) {
            esp_error('Controller CID', "当前站点未启用Cookies，无法获取CID");
        }
        return $this->_cookies->cid($key, $number);
    }

    /**
     * 生成一个唯一键（无法保证绝对唯一）
     * 建议直接用uniqid()
     *
     * @param string $type
     * @param string $salt
     * @return string
     */
    public function uniqid(string $type = 'md5', string $salt = ''): string
    {
        switch ($type) {
            case 'sha256':
            case 'sha1':
            case 'md5':
                return hash($type, getenv('REQUEST_ID') . uniqid($salt, true) . $salt);

            default:
                return uniqid($salt, true);
        }
    }

    /**
     * var_export
     *
     * @return string
     */
    public static function __set_state(array $data)
    {
        return __CLASS__;
    }

    /**
     * echo
     *
     * @return string
     */
    public function __toString()
    {
        return print_r($this, true);
    }

    /**
     * var_dump
     * @return array
     */
    public function __debugInfo()
    {
        return [__CLASS__];
    }


}