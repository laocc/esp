<?php
declare(strict_types=1);

namespace esp\core;

use ErrorException;
use esp\debug\Counter;
use esp\debug\Debug;
use esp\error\Error;
use esp\error\EspError;
use esp\session\Session;
use esp\helper\library\Result;
use function esp\helper\host;

final class Dispatcher
{
    private $_plugs_count = 0;//引入的plugs数量
    private $run = true;//任一个bootstrap若返回false，则不再执行run()方法中的后续内容
    public $_plugs = array();
    public $_request;
    public $_response;
    public $_session;
    public $_cookies;
    public $_config;
    public $_debug;
    public $_cache;
    public $_error;
    public $_counter;

    /**
     * Dispatcher constructor.
     * @param array $option
     * @param string $virtual
     * @throws EspError
     */
    public function __construct(array $option, string $virtual = 'www')
    {
        /**
         * 最好在nginx server中加以下其中之一：
         * if ($request_method ~ ^(HEAD)$ ) { return 200 "OK"; }
         * if ($request_method !~ ^(GET|POST)$ ) { return 200 "OK"; }
         */
        if (getenv('REQUEST_METHOD') === 'HEAD') exit('OK');

        if (!defined('_CLI')) define('_CLI', (PHP_SAPI === 'cli' or php_sapi_name() === 'cli'));
        if (!getenv('HTTP_HOST') && !_CLI) exit('unknown host');
        if (!defined('_ROOT')) {
            if ($dirI = strpos(__DIR__, '/vendor/laocc/esp/core')) {
                $rootPath = substr(__DIR__, 0, $dirI);
            } else if ($dirI = strpos(__DIR__, '/laocc/esp/core')) {
                $rootPath = (substr(__DIR__, 0, $dirI));
            } else {
                $rootPath = dirname($_SERVER['DOCUMENT_ROOT'], 2);
            }
            define('_ROOT', $rootPath); //网站根目录
        }
        if (!defined('_RUNTIME')) define('_RUNTIME', _ROOT . '/runtime');
        if (!defined('_DEBUG')) define('_DEBUG', is_readable($df = _RUNTIME . '/debug.lock') ? (file_get_contents($df) ?: true) : false);
        if (!defined('_VIRTUAL')) define('_VIRTUAL', strtolower($virtual));
        if (!defined('_DOMAIN')) define('_DOMAIN', explode(':', getenv('HTTP_HOST') . ':')[0]);
        if (!defined('_HOST')) define('_HOST', host(_DOMAIN));//域名的根域
        if (!defined('_HTTPS')) define('_HTTPS', (getenv('HTTP_HTTPS') === 'on' or getenv('HTTPS') === 'on'));
        if (!defined('_HTTP_')) define('_HTTP_', (_HTTPS ? 'https://' : 'http://'));
        if (!defined('_URL')) define('_URL', _HTTP_ . _DOMAIN . getenv('REQUEST_URI'));

        $ip = '127.0.0.1';
        if (_CLI) {
            if (!defined('_URI')) define('_URI', ('/' . trim(implode('/', array_slice($GLOBALS['argv'], 1)), '/')));
        } else {
            if (!defined('_URI')) define('_URI', parse_url(str_replace('//', '/', getenv('REQUEST_URI') ?: '/'), PHP_URL_PATH) ?: '/');
            //对于favicon.ico，建议在根目录直接放一个该文件，或在nginx中直接拦截
            if (_URI === '/favicon.ico') {
                header('Content-type: image/x-icon', true);
                exit('data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAAAQSURBVHjaYvj//z8DQIABAAj8Av7bok0WAAAAAElFTkSuQmCC');
            }
            foreach (['X-REAL-IP', 'X-FORWARDED-FOR', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'HTTP_X_CLIENT_IP', 'HTTP_X_CLUSTER_CLIENT_IP', 'REMOTE_ADDR'] as $k) {
                if (!empty($ip = ($_SERVER[$k] ?? null))) {
                    if (strpos($ip, ',')) $ip = explode(',', $ip)[0];
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) break;
                }
            }
        }
        if (!defined('_CIP')) define('_CIP', $ip);

        if (isset($option['before'])) $option['before']($option);

        //以下2项必须在`chdir()`之前，且顺序不可变
        if (!_CLI) $this->_error = new Error($this, $option['error'] ?? []);

        if (!isset($option['config'])) $option['config'] = [];
        $option['config'] += ['driver' => 'redis'];
        $this->_config = $cfg = new Configure($option['config']);

        /**
         * 切换之前是nginx中指定的root入口目录，
         * 切换后 getcwd() 的结果为_ROOT
         */
        chdir(_ROOT);
        $request = $cfg->get('request');
        if (empty($request)) $request = [];
        $request = $this->mergeConf($request);
        $this->_request = new Request($request);
        if (_CLI) return;

        if (!empty($counter = $cfg->get('counter'))) {
            $counter = $this->mergeConf($counter);
            if ($counter['run'] ?? 0) {
                $counter['_key'] = md5(_ROOT);
                $this->_counter = new Counter($counter, $cfg->_Redis->redis, $this->_request);
            }
        }

        if ($debugConf = $cfg->get('debug')) {
            $debug = $this->mergeConf($debugConf);
            if ($debug['run'] ?? 0) {
                $this->_debug = new \esp\debug\Debug($this, $debug);
                $this->_error->setDebug($this->_debug);
            }
        }

        $response = $cfg->get('response');
        if (empty($response)) $response = [];
        $response = $this->mergeConf($response);
        $response['_rand'] = $cfg->_Redis->get('resourceRand') ?: date('YmdH');
        $this->_response = new Response($this->_request, $response);

        if ($cookies = $cfg->get('cookies')) {
            $cokConf = $this->mergeConf($cookies, ['run' => false, 'debug' => false, 'domain' => 'host']);

            if ($cokConf['run'] ?? false) {
                $this->_cookies = new Cookies($cokConf);
                if ($cokConf['debug']) $this->relayDebug(['cookies' => $_COOKIE]);

                //若不启用Cookies，则也不启用Session
                if ($session = ($cfg->get('session'))) {
                    $sseConf = $this->mergeConf($session, ['run' => false, 'domain' => $cokConf['domain']]);

                    if ($sseConf['run'] ?? false) {
                        if (!isset($sseConf['driver'])) $sseConf['driver'] = $option['config']['driver'];

                        if ($sseConf['driver'] === 'redis') {
                            $rds = $cfg->get('database.redis');
                            $cID = $rds['db'];
                            if (is_array($cID)) $cID = $cID['config'] ?? 1;

                            $rdsConf = ($sseConf['redis'] ?? []) + $rds;
                            if (is_array($rdsConf['db'])) $rdsConf['db'] = $rdsConf['db']['session'] ?? 0;
                            if ($rdsConf['db'] === 0) $rdsConf['db'] = $cID;

                            $sseConf['redis'] = $rdsConf;
                        }

                        $this->_session = new Session($sseConf);
                        if ($sseConf['redis']['db'] === $cfg->RedisDbIndex
                            and $option['config']['drive'] === 'redis') {
                            $this->_session->start($cfg->_Redis->redis);
                        } else {
                            $this->_session->start();
                        }

                    }
                }
            }
        }

        if ($cacheConf = $cfg->get('cache')) {
            $cache = $this->mergeConf($cacheConf);
            if ($cache['run'] ?? 0) {
                $this->_cache = new Cache($this, $cache);
                $this->_response->cache(true);
            }
        }

        if (isset($option['after'])) $option['after']($option);

        unset($GLOBALS['option']);
        if (headers_sent($file, $line)) {
            throw new EspError("在{$file}[{$line}]行已有数据输出，系统无法启动");
        }
    }

    /**
     * 系统运行调度中心
     * @throws EspError|ErrorException
     */
    public function run(): void
    {
        $showDebug = boolval($_GET['_debug'] ?? 0);
        if ($this->run === false) goto end;
        if (_CLI) throw new EspError("cli环境中请直接调用\$this->simple()方法");

        if ($this->_plugs_count and !is_null($hook = $this->plugsHook('router'))) {
            $this->_response->display($hook);
            goto end;
        }

        $route = (new Router())->run($this->_request);
        if ($route) {
            if ($route === 'true') $route = '';
            if (substr($route, 0, 6) === 'redis:') {
                $route = $this->_config->_Redis->get(substr($route, 6));
            }
            exit($route);
        }

        if (!is_null($this->_debug)) $this->_debug->setRouter($this->_request->RouterValue());

        if (!is_null($this->_cache)) {
            if ($this->_response->cache && $this->_cache->Display()) {
                fastcgi_finish_request();//运行结束，客户端断开
                $this->relayDebug("[blue;客户端已断开 =============================]");
                goto end;
            }
        }

        if ($this->_plugs_count and !is_null($hook = $this->plugsHook('dispatch'))) {
            $this->_response->display($hook);
            goto end;
        }

        //TODO 运行控制器->方法
        $value = $this->dispatch();

        //控制器、并发计数
        if ($this->_counter) $this->_counter->recodeCounter();

        //若启用了session，立即保存并结束session
        if ($this->_session) session_write_close();

        if ($this->_plugs_count and !is_null($hook = $this->plugsHook('display', $value))) {
            $this->_response->display($hook);
            goto end;
        }

        $this->_response->display($value);

        $this->_plugs_count and $hook = $this->plugsHook('finish', $value);

        if (!_DEBUG and !$showDebug) fastcgi_finish_request();//运行结束，客户端断开

        $this->relayDebug("[blue;客户端已断开 =============================]");
        if (!is_null($this->_cache) && $this->_response->cache and !_CLI) $this->_cache->Save();

        end:
        $this->_plugs_count and $hook = $this->plugsHook('shutdown');

        if (is_null($this->_debug)) return;

        $this->_debug->setResponse([
            'type' => $this->_response->_Content_Type,
            'display' => $this->_response->_display_Result
        ]);

        if ($this->_debug->mode === 'cgi' or $showDebug) {
            $save = $this->_debug->save_logs('run.Dispatcher.Cgi');
            if ($showDebug) var_dump($save);

        } else {
            register_shutdown_function(function () {
                $this->_debug->save_logs('run.Dispatcher.Shutdown');
            });
        }
    }

    /**
     * 不运行plugs，不执行缓存
     *
     * @throws EspError|ErrorException
     */
    public function simple(): void
    {
        $showDebug = boolval($_GET['_debug'] ?? 0);
        if ($this->run === false) goto end;

        $route = (new Router())->run($this->_request);
        if ($route) {
            if ($route === 'true') $route = '';
            if (substr($route, 0, 6) === 'redis:') {
                $route = $this->_config->_Redis->get(substr($route, 6));
            }
            exit($route);
        }

        if (!_CLI && !is_null($this->_debug)) {
            $this->_debug->setRouter($this->_request->RouterValue());
        }

        $value = $this->dispatch();

        if (_CLI) {
            print_r($value);
            return;
        }

        //控制器、并发计数
        if ($this->_counter) $this->_counter->recodeCounter();

        //若启用了session，立即保存并结束session
        if ($this->_session) session_write_close();

        $this->_response->display($value);

        end:
        if (!_DEBUG and !$showDebug) fastcgi_finish_request();

        if (is_null($this->_debug)) return;

        $this->_debug->setResponse([
            'type' => $this->_response->_Content_Type,
            'display' => $this->_response->_display_Result
        ]);

        if ($this->_debug->mode === 'cgi' or $showDebug) {
            $save = $this->_debug->save_logs('simple.Dispatcher.Cgi');
            if ($showDebug) var_dump($save);
        } else {
            register_shutdown_function(function () {
                $this->_debug->save_logs('simple.Dispatcher.Shutdown');
            });
        }
    }

    /**
     * 合并设置
     *
     * @param array $allConf
     * @param array $conf
     * @return array
     */
    private function mergeConf(array $allConf, array $conf = []): array
    {
        if (!isset($allConf['default'])) return $allConf + $conf;
        $conf = $allConf['default'] + $conf;

        if (isset($allConf[_VIRTUAL])) {
            $conf = array_replace_recursive($conf, $allConf[_VIRTUAL]);
        }
        if (isset($allConf[_HOST])) {
            $conf = array_replace_recursive($conf, $allConf[_HOST]);
        }
        if (isset($allConf[_DOMAIN])) {
            $conf = array_replace_recursive($conf, $allConf[_DOMAIN]);
        }

        return $conf;
    }

    private function relayDebug($info): void
    {
        if (is_null($this->_debug)) return;
        $this->_debug->relay($info, 2);
    }

    /**
     * @param string $data
     * @param int $pre
     * @return Debug|false|null
     */
    final public function debug($data = '_R_DEBUG_', int $pre = 1)
    {
        if (_CLI) return false;
        if (is_null($this->_debug)) return null;
        if ($data === '_R_DEBUG_') return $this->_debug;
        $this->_debug->relay($data, $pre + 1);
        return $this->_debug;
    }

    final public function error($data, $pre = 1): void
    {
        if (_CLI) return;
        if (is_null($this->_debug)) return;
        $this->_debug->error($data, $pre);
    }

    final public function debug_mysql($data, $pre = 1): void
    {
        if (_CLI) return;
        if (is_null($this->_debug)) return;
        $this->_debug->mysql_log($data, $pre);
    }

    /**
     * @return Request
     */
    public function getRequest(): Request
    {
        return $this->_request;
    }

    /**
     * @return Response
     */
    public function getResponse(): Response
    {
        return $this->_response;
    }

    /**
     * @param $class
     * @return Dispatcher
     * @throws EspError
     */
    public function bootstrap($class): Dispatcher
    {
        if (is_string($class)) {
            if (!class_exists($class)) {
                throw new EspError("Bootstrap类不存在，请检查{$class}.php文件");
            }
            $class = new $class();
        }
        foreach (get_class_methods($class) as $method) {
            if (substr($method, 0, 5) === '_init') {
                $run = call_user_func_array([$class, $method], [$this]);
                if ($run === false) {
                    $this->run = false;
                    break;
                }
            }
        }
        return $this;
    }

    /**
     * 接受注册插件
     * @param Plugin $class
     * @return $this
     * @throws EspError
     */
    public function setPlugin(Plugin $class): Dispatcher
    {
        $name = get_class($class);
        $name = ucfirst(substr($name, strrpos($name, '\\') + 1));
        if (isset($this->_plugs[$name])) {
            throw new EspError("插件名{$name}已被注册过");
        }
        $this->_plugs[$name] = $class;
        $this->_plugs_count++;
        return $this;
    }

    /**
     * 执行HOOK
     * @param string $time 'router', 'dispatch', 'display', 'finish', 'shutdown'
     * @param null $runValue dispatchAfter之后才有该值，此值在hook中可以被修改
     * @return mixed|null
     */
    private function plugsHook(string $time, &$runValue = null)
    {
        if (empty($this->_plugs)) return null;

        foreach ($this->_plugs as $plug) {
            if (method_exists($plug, $time) and is_callable([$plug, $time])) {
                return call_user_func_array([$plug, $time], [$this->_request, $this->_response, &$runValue]);
            }
        }

        return null;
    }

    public function min(): void
    {
        $this->simple();
    }

    /**
     * 路由结果分发至控制器动作
     * @return mixed
     * @throws EspError
     */
    private function dispatch()
    {
        $actionExt = $this->_request->getActionExt();

        LOOP:
        $virtual = $this->_request->virtual;
        if ($this->_request->module) $virtual .= '\\' . $this->_request->module;

        $controller = ucfirst($this->_request->controller) . $this->_request->contFix;
        $action = strtolower($this->_request->action) . $actionExt;

        $class = "\\application\\{$virtual}\\controllers\\{$controller}";
        if (!is_null($this->_debug)) $this->_debug->setController($class);

        if (!class_exists($class)) {
            if (_DEBUG) {
                return $this->err404("[{$class}] 控制器不存在，请确认文件是否存在，或是否在composer.json中引用了控制器目录");
            } else {
                return $this->err404("[{$this->_request->controller}] not exists.");
            }
        }

        $cont = new $class($this);
        if (!($cont instanceof Controller)) {
            throw new EspError("{$class} 须继承自 \\esp\\core\\Controller");
        }

        if (!method_exists($cont, $action) or !is_callable([$cont, $action])) {
            $auto = strtolower($this->_request->action) . 'Action';
            if (method_exists($cont, $auto) and is_callable([$cont, $auto])) {
                $action = $auto;
            } else {
                if (method_exists($cont, "default{$actionExt}") and is_callable([$cont, "default{$actionExt}"])) {
                    $action = "default{$actionExt}";
                } else if (method_exists($cont, 'defaultAction') and is_callable([$cont, 'defaultAction'])) {
                    $action = 'defaultAction';
                } else {
                    return $this->err404("[{$class}::{$action}()] not exists.");
                }
            }
        }

        /**
         * 运行初始化，一般这个放在Base中
         */
        if (method_exists($cont, '_init') and is_callable([$cont, '_init'])) {
            $this->relayDebug("[blue;{$class}->_init() ============================]");
            $contReturn = call_user_func_array([$cont, '_init'], [$action]);
            if (is_bool($contReturn) or !is_null($contReturn)) {
                $this->relayDebug(['_init' => 'return', 'return' => $contReturn]);
                if ($contReturn === false) $contReturn = null;
                goto close;
            }
        }

        /**
         * 一般这个放在实际控制器中
         */
        if (method_exists($cont, '_main') and is_callable([$cont, '_main'])) {
            $this->relayDebug("[blue;{$class}->_main() =============================]");
            $contReturn = call_user_func_array([$cont, '_main'], [$action]);
            if (is_bool($contReturn) or !is_null($contReturn)) {
                $this->relayDebug(['_main' => 'return', 'return' => $contReturn]);
                if ($contReturn === false) $contReturn = null;
                goto close;
            }
        }

        /**
         * 正式请求到控制器
         */
        $this->relayDebug("[green;{$class}->{$action} Star ==============================]");
        $contReturn = call_user_func_array([$cont, $action], $this->_request->params);
        $this->relayDebug("[red;{$class}->{$action} End ==============================]");

        //在控制器中，如果调用了reload方法，则所有请求数据已变化，loop将赋为true，开始重新加载
        if ($this->_request->loop === true) {
            $this->_request->loop = false;
            goto LOOP;
        }

        close:
        if ($contReturn instanceof Result) $contReturn = $contReturn->display();

        //运行结束方法
        if (method_exists($cont, '_close') and is_callable([$cont, '_close'])) {
            $clo = call_user_func_array([$cont, '_close'], [$action, &$contReturn]);
            if (!is_null($clo) and is_null($contReturn)) $contReturn = $clo;
            $this->relayDebug("[red;{$class}->_close() ==================================]");
        }

        if ($contReturn instanceof Result) return $contReturn->display();
        else if (is_object($contReturn)) return (string)$contReturn;

        return $contReturn;
    }


    private function err404(string $msg)
    {
        if (!is_null($this->_debug)) $this->_debug->folder('error');
        $empty = $this->_config->get('request.empty');
        if (is_array($empty)) $empty = $empty[$this->_request->virtual] ?? $msg;
        $this->_request->exists = false;
        if (!empty($empty)) return $empty;
        return $msg;
    }

    private $_skipError = [];

    /**
     * @param string $file
     * @param int $line
     * @param bool $isCheck
     * @return bool
     */
    public function ignoreError(string $file, int $line, bool $isCheck = false): bool
    {
        if (in_array("{$file}.{$line}", $this->_skipError)) return true;
        if ($isCheck) return false;

        $this->_skipError[] = "{$file}.{$line}";
        return true;
    }


    /**
     * 带锁执行，有些有可能在锁之外会变的值，最好在锁内读取，比如要从数据库读取某个值
     * 如果任务出错，返回字符串表示出错信息，所以正常业务的返回要避免返回字符串
     * 出口处判断如果是字符串即表示出错信息
     *
     * @param string $lockKey 任意可以用作文件名的字符串，同时也表示同一种任务
     * @param callable $callable 该回调方法内返回的值即为当前函数返回值
     * @param mixed ...$args
     * @return mixed
     */
    public function locked(string $lockKey, callable $callable, ...$args)
    {
        $option = intval($lockKey[0]);
        $operation = ($option & 1) ? (LOCK_EX | LOCK_NB) : LOCK_EX;
        $lockKey = str_replace(['/', '\\', '`', '*', '"', "'", '<', '>', ':', ';', '?', ' '], '', $lockKey);
        if (_CLI) $lockKey = $lockKey . '_CLI';
        $fn = fopen(($lockFile = "/tmp/flock_{$lockKey}.flock"), 'a');
        if (!$fn) {
            $msg = "/tmp/flock_{$lockKey}.flock fopen error";
            if (_CLI) {
                var_dump($msg);
            } else if (!is_null($this->_debug)) {
                $this->_debug->relay($msg, 2);
            }
        }
        if (flock($fn, $operation)) {           //加锁
            try {

                $rest = $callable(...$args);    //执行

            } catch (\Exception $exception) {
                $rest = 'locked: ' . $exception->getMessage();
            } catch (\Error $error) {
                $rest = 'locked: ' . $error->getMessage();
            }
            flock($fn, LOCK_UN);//解锁
        } else {
            $rest = "locked: Running";
        }
        fclose($fn);
        $this->ignoreError(__FILE__, __LINE__ + 1);
        if (!($option & 2) && is_readable($lockFile)) @unlink($lockFile);
        return $rest;
    }

}
