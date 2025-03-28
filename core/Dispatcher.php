<?php
declare(strict_types=1);

namespace esp\core;

use esp\debug\Counter;
use esp\debug\Debug;
use esp\help\Helps;
use esp\error\Handler;
use esp\session\Session;
use esp\helper\library\Result;
use function esp\helper\host;

//标准的时间格式，用于date(DATE_YMD_HIS)
const DATE_YMD_HIS = 'Y-m-d H:i:s';

final class Dispatcher
{
    private int $_plugs_count = 0;//引入的plugs数量
    private bool $run = true;//任一个bootstrap若返回false，则不再执行run()方法中的后续内容
    public array $_plugs = array();
    public Request $_request;
    public Response $_response;
    public Configure $_config;
    public ?Debug $_debug;
    public ?Session $_session;
    public ?Cookies $_cookies;
    public ?Cache $_cache;
    public ?Handler $_error;
    public ?Counter $_counter;

    private array $_skipError = [];

    /**
     * Dispatcher constructor.
     * @param array $option
     * @param string $virtual
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
                $rootPath = dirname($_SERVER['DOCUMENT_ROOT'] ?: $_SERVER['PWD'], 2);
            }
            define('_ROOT', $rootPath); //网站根目录
        }
        if (!defined('_UNIQUE_KEY')) define('_UNIQUE_KEY', md5(__FILE__));//本项目在当前服务器的唯一键
        if (!defined('_RUNTIME')) define('_RUNTIME', _ROOT . '/runtime');//临时文件目录
        if (!defined('_DEBUG')) define('_DEBUG', is_readable($df = _RUNTIME . '/debug.lock') ? (file_get_contents($df) ?: true) : false);
        if (!defined('_VIRTUAL')) define('_VIRTUAL', strtolower($virtual));
        if (!defined('_MASTER')) define('_MASTER', is_readable(_RUNTIME . '/master.lock'));
        if (!defined('_DOMAIN')) define('_DOMAIN', explode(':', getenv('HTTP_HOST') . ':')[0]);
        if (!defined('_HOST')) define('_HOST', host(_DOMAIN));//根域，可以在入口自行定义_HOST，或在option里指明这是个三级子域名
        if (!defined('_HTTPS')) define('_HTTPS', (getenv('HTTP_HTTPS') === 'on' or getenv('HTTPS') === 'on'));
        if (!defined('_URL')) define('_URL', (_HTTPS ? 'https:' : 'http:') . '//' . _DOMAIN . getenv('REQUEST_URI'));

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
        if (!_CLI) {
            $this->_error = new Handler($option['error'] ?? [], function (string $file, int $line) {
                return $this->ignoreError($file, $line, true);
            });
        }

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
                $counter['_redis_index'] = $cfg->RedisDbIndex;
                $this->_counter = new Counter($counter, $cfg->_Redis, $this->_request);
            }
        }

        if ($debugConf = $cfg->get('debug')) {
            $debug = $this->mergeConf($debugConf);
            if ($debug['run'] ?? 0) {
                $this->_debug = new Debug($this, $debug);
                $this->_error->setDebug($this->_debug);
            }
        }

        $response = $cfg->get('response');
        if (empty($response)) $response = [];
        $response = $this->mergeConf($response);
        if (isset($response['rand'])) $response['_rand'] = $cfg->_Redis->get(_UNIQUE_KEY . '_RESOURCE_RAND_') ?: date('YmdH');
        $this->_response = new Response($this->_request, $response);

        if ($cookies = $cfg->get('cookies')) {
            $cokConf = $this->mergeConf($cookies, ['run' => false, 'debug' => false, 'domain' => 'host']);

            if ($cokConf['run'] ?? false) {
                $this->_cookies = new Cookies($cokConf);
                if ($cokConf['debug']) $this->relayDebug(['cookies' => $_COOKIE]);

                //若不启用Cookies，则也不启用Session
                if ($session = ($cfg->get('session'))) {
                    $sseConf = $this->mergeConf($session, ['run' => false]);

                    if ($sseConf['run'] ?? false) {
                        if (!isset($sseConf['driver'])) $sseConf['driver'] = $cfg->driver;

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
                        if (($sseConf['redis']['db'] ?? -1) === $cfg->RedisDbIndex and $cfg->driver === 'redis') {
                            $this->_session->start($cfg->_Redis);
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

        if (isset($option['after']) and is_callable($option['after'])) $option['after']($option);

        unset($GLOBALS['option']);
        if (headers_sent($file, $line)) {
            esp_error('header输出错误', "在{$file}[{$line}]行已有数据输出，系统无法启动");
        }
    }

    /**
     * 系统运行调度中心
     */
    public function run(bool $simple = false): void
    {
        $showDebug = boolval($_GET['_debug'] ?? 0);
        if ($this->run === false) goto end;
        if (_CLI and !$simple) esp_error("cli环境中请调用\$this->simple()或->run(true)方法");

        if (!$simple and $this->_plugs_count and !is_null($hook = $this->plugsHook('router'))) {
            $this->_response->display($hook);
            goto end;
        }

        $alias = $this->_config->get('alias');
        if (empty($alias)) $alias = [];
        else $alias = $this->mergeConf($alias);

        $route = (new Router($this->_config->_Redis))->run($this->_request, $alias);
        if (is_string($route)) {
            echo $route;
            if (!_CLI) fastcgi_finish_request();
            exit;
        }

        if (isset($this->_debug)) $this->_debug->setRouter($this->_request->RouterValue());

        if (!$simple and isset($this->_cache)) {
            if ($this->_response->cache && $this->_cache->Display()) {
                if (!_CLI) fastcgi_finish_request();//运行结束，客户端断开
                $this->relayDebug("[blue;客户端已断开 =============================]");
                goto end;
            }
        }

        if (!$simple and $this->_plugs_count and !is_null($hook = $this->plugsHook('dispatch'))) {
            $this->_response->display($hook);
            goto end;
        }

        //TODO 运行控制器->方法
        $value = $this->dispatch();
        if (_CLI) {
            print_r($value);
            echo "\r\n";
            return;
        }

        //控制器、并发计数
        if (isset($this->_counter)) $this->_counter->recodeCounter();

        //若启用了session，立即保存并结束session
        if (isset($this->_session)) session_write_close();

        if (!$simple and $this->_plugs_count and !is_null($hook = $this->plugsHook('display', $value))) {
            $this->_response->display($hook);
            goto end;
        }

        $this->_response->display($value);

        !$simple and $this->_plugs_count and $hook = $this->plugsHook('finish', $value);

        if (!_DEBUG and !$showDebug) fastcgi_finish_request();//运行结束，客户端断开

        $this->relayDebug("[blue;客户端已断开 =============================]");
        if (isset($this->_cache) && $this->_response->cache) $this->_cache->Save();

        end:
        !$simple and $this->_plugs_count and $hook = $this->plugsHook('shutdown');

        if (!isset($this->_debug)) return;

        if ($this->_debug->mode === 'none') return;

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
     */
    public function simple(): void
    {
        $showDebug = boolval($_GET['_debug'] ?? 0);
        if ($this->run === false) goto end;

        $alias = $this->_config->get('alias');
        if (empty($alias)) $alias = [];
        else $alias = $this->mergeConf($alias);

        $route = (new Router($this->_config->_Redis))->run($this->_request, $alias);
        if (is_string($route)) {
            echo $route;
            if (!_CLI) fastcgi_finish_request();
            exit;
        }

        if (!_CLI && isset($this->_debug)) {
            $this->_debug->setRouter($this->_request->RouterValue());
        }

        $value = $this->dispatch();

        if (_CLI) {
            print_r($value);
            echo "\r\n";
            return;
        }

        //控制器、并发计数
        if (isset($this->_counter)) $this->_counter->recodeCounter();

        //若启用了session，立即保存并结束session
        if (isset($this->_session)) session_write_close();

        $this->_response->display($value);

        end:
        if (!_DEBUG and !$showDebug) fastcgi_finish_request();

        if (!isset($this->_debug)) return;
        if ($this->_debug->mode === 'none') return;

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

    public function min(): void
    {
        $this->simple();
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
        if (!isset($this->_debug)) return;
        $this->_debug->relay($info, 2);
    }

    /**
     * @param string $data
     * @param int $pre
     * @return Debug|false|null
     */
    public function debug($data = '_R_DEBUG_', int $pre = 1)
    {
        if (_CLI) return false;
        if (!isset($this->_debug)) return null;
        if ($data === '_R_DEBUG_') return $this->_debug;
        $this->_debug->relay($data, $pre + 1);
        return $this->_debug;
    }

    public function error($data, int $pre = 1): void
    {
        if (_CLI) return;
        if (!isset($this->_debug)) return;
        $this->_debug->error($data, $pre + 1);
    }

    public function debug_mysql($data, int $pre = 1): void
    {
        if (_CLI) return;
        if (!isset($this->_debug)) return;
        $this->_debug->mysql_log($data, $pre);
    }

    /**
     * 设置并返回debug文件名
     * @param string|null $filename
     * @return string
     */
    public function debug_file(string $filename = null): string
    {
        if (!isset($this->_debug)) return 'null';
        return $this->_debug->filename($filename);
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
     */
    public function bootstrap($class): Dispatcher
    {
        if (is_string($class)) {
            if (!class_exists($class)) {
                esp_error('Bootstrap Error', "Bootstrap类不存在，请检查{$class}.php文件");
            }
            $class = new $class();
        }
        foreach (get_class_methods($class) as $method) {
            if (str_starts_with($method, '_init')) {
                $run = $class->{$method}($this);
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
     */
    public function setPlugin(Plugin $class): Dispatcher
    {
        $name = get_class($class);
        $name = ucfirst(substr($name, strrpos($name, '\\') + 1));
        if (isset($this->_plugs[$name])) {
            esp_error('Plugin Error', "插件名{$name}已被注册过");
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
                return $plug->{$time}($this->_request, $this->_response, $runValue);
            }
        }

        return null;
    }

    /**
     * 路由结果分发至控制器动作
     */
    private function dispatch()
    {
        $actionExt = $this->_request->getActionExt();

        LOOP:
        $virtual = $this->_request->virtual;
        if ($this->_request->module) $virtual .= '\\' . $this->_request->module;

        //以-开头为在CLI环境下执行Helps中的方法，如：exp -s flush
        if (_CLI && $this->_request->controller[0] === '-') {
            if (defined('_RPC') and !_MASTER) return "框架级系统方法只能在主服务器执行";

            $cont = new Helps($this, substr($this->_request->controller, 1));
            if (method_exists($cont, $this->_request->action) and is_callable([$cont, $this->_request->action])) {
                return $cont->{$this->_request->action}(...$this->_request->params);
            } else {
                return "Helps{}类没有{$this->_request->action}方法。";
            }
        }

        $controller = ucfirst($this->_request->controller) . $this->_request->contFix;
        $action = strtolower($this->_request->action) . $actionExt;

        $class = "\\application\\{$virtual}\\controllers\\{$controller}";
        if (isset($this->_debug)) $this->_debug->setController($class);

        if (!class_exists($class)) {
            if (isset($this->_debug)) $this->_debug->folder('controller_error');
            return $this->_request->returnEmpty('controller', "[{$this->_request->controller}] nonexistent");
        }

        $cont = new $class($this);
        if (!($cont instanceof Controller)) {
            esp_error('Controller Error', "{$class} 须继承自 \\esp\\core\\Controller");
        }

        /**
         * 运行初始化，一般这个放在Base中
         */
        if (method_exists($cont, "_init{$actionExt}") and is_callable([$cont, "_init{$actionExt}"])) {
            $this->relayDebug("[blue;{$class}->_init{$actionExt}() ============================]");
            $contReturn = $cont->{"_init{$actionExt}"}($this->_request->controller, $this->_request->action);
            if (is_bool($contReturn) or !is_null($contReturn)) {
                $this->relayDebug(["_init{$actionExt}" => $contReturn]);
                if ($contReturn === false) $contReturn = null;
                goto close;
            }

        } else
            //执行默认的
            if (method_exists($cont, '_init') and is_callable([$cont, '_init'])) {
                $this->relayDebug("[blue;{$class}->_init() ============================]");
                $contReturn = $cont->_init($this->_request->controller, $this->_request->action);
                if (is_bool($contReturn) or !is_null($contReturn)) {
                    $this->relayDebug(['_init' => $contReturn]);
                    if ($contReturn === false) $contReturn = null;
                    goto close;
                }
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
                    if (isset($this->_debug)) $this->_debug->folder('action_error');
                    return $this->_request->returnEmpty('action', "[{$class}::{$action}()] nonexistent");
                }
            }
        }

        /**
         * 一般这个放在实际控制器中
         */
        if (method_exists($cont, "_main{$actionExt}") and is_callable([$cont, "_main{$actionExt}"])) {
            $this->relayDebug("[blue;{$class}->_main{$actionExt}() =============================]");
            $contReturn = $cont->{"_main{$actionExt}"}($this->_request->controller, $this->_request->action);
            if (is_bool($contReturn) or !is_null($contReturn)) {
                $this->relayDebug(["_main{$actionExt}" => $contReturn]);
                if ($contReturn === false) $contReturn = null;
                goto close;
            }
        } else
            if (method_exists($cont, '_main') and is_callable([$cont, '_main'])) {
                $this->relayDebug("[blue;{$class}->_main() =============================]");
                $contReturn = $cont->_main($this->_request->controller, $this->_request->action);
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

        if (defined('_Use_ReflectionMethod')) {
            $params = array_values($this->_request->params);
            $reflectionMethod = new \ReflectionMethod($cont, $action);
            foreach ($reflectionMethod->getParameters() as $i => $parameter) {
                switch ($parameter->getType()) {
                    case 'int':
                        $params[$i] = intval($params[$i] ?? 0);
                        break;
                    case 'float':
                        $params[$i] = floatval($params[$i] ?? 0);
                        break;
                    case 'string':
                        $params[$i] = strval($params[$i] ?? '');
                        break;
                    case 'bool':
                        $params[$i] = boolval($params[$i] ?? 0);
                        break;
                    default:
                        $params[$i] = ($params[$i] ?? null);
                }
            }
            $contReturn = $cont->{$action}(...$params);//PHP7.4以后用可变函数语法来调用
        } else {
            $contReturn = $cont->{$action}(...array_values($this->_request->params));//PHP7.4以后用可变函数语法来调用
        }

        $this->relayDebug("[red;{$class}->{$action} End ==============================]");

        //在控制器中，如果调用了reload方法，则所有请求数据已变化，loop将赋为true，开始重新加载
        if ($this->_request->loop === true) {
            $this->_request->loop = false;
            goto LOOP;
        }

        close:
        if ($contReturn instanceof Result) $contReturn = $contReturn->display();

        //运行结束方法
        if (method_exists($cont, "_close{$actionExt}") and is_callable([$cont, "_close{$actionExt}"])) {
            $this->relayDebug("[red;{$class}->_close{$actionExt}() ==================================]");
            $closeReturn = $cont->{"_close{$actionExt}"}($contReturn);
            if (!is_null($closeReturn)) $contReturn = $closeReturn;
        } else
            if (method_exists($cont, '_close') and is_callable([$cont, '_close'])) {
                $this->relayDebug("[red;{$class}->_close() ==================================]");
                $closeReturn = $cont->_close($contReturn);
                if (!is_null($closeReturn)) $contReturn = $closeReturn;
            }

        if ($contReturn instanceof Result) return $contReturn->display();
        else if (is_object($contReturn)) {
            if (method_exists($contReturn, 'display')) return $contReturn->display();
            return (string)$contReturn;
        }

        return $contReturn;
    }

    /**
     * 注册调用位置的下一行屏蔽错误
     *
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
     * @param callable $callable
     * @param ...$params
     * @return bool
     */
    public function shutdown(callable $callable, ...$params): bool
    {
        return (boolean)register_shutdown_function(function (callable $callable, ...$params) {
            try {

                $callable(...$params);

            } catch (\Error|\Exception $error) {
                $err = [];
                $err['file'] = $error->getFile();
                $err['line'] = $error->getLine();
                $err['message'] = $error->getMessage();
                $this->error($err);
            }
        }, $callable, ...$params);
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
    public function locked(string $lockKey, callable $callable, ...$args): mixed
    {
        if (str_ends_with($lockKey, 'redis')) return $this->lockedRedis($lockKey, $callable, ...$args);
        return $this->lockedFile($lockKey, $callable, ...$args);
    }

    /**
     * @param string $lockKey
     * @param callable $callable
     * @param ...$args
     * @return mixed
     */
    public function lockedRedis(string $lockKey, callable $callable, ...$args): mixed
    {
        $lockKey = str_replace(['/', '\\', '`', '*', '"', "'", '<', '>', ':', ';', '?', ' '], '', $lockKey);
        if (_CLI) $lockKey = $lockKey . '_CLI';
        $option = intval($lockKey[0]);

        /**
         * 最多等50次，即5秒
         */
        $maxWait = 50;
        if ($option & 2) $maxWait = 100;
        else if ($option & 4) $maxWait = 200;

        if (defined('_LockedTime')) $maxWait = _LockedTime;

        for ($i = 0; $i < $maxWait; $i++) {
            //将 key 的值设为 value ，当且仅当 key 不存在。
            $set = $this->_config->_Redis->setnx("locked.{$lockKey}", microtime(true));

            if ($set) {  //key设置成功，执行
                $run = $callable(...$args);
                $this->_config->_Redis->del("locked.{$lockKey}");//删除Key
                return $run;
            }

            if ($option & 1) return 'locked';//非等待锁，只要有锁，就立即返回

            usleep(100000);// 休眠100,000微秒（即0.1秒）
        }

        return 'locked';
    }


    /**
     * @param string $lockKey
     * @param callable $callable
     * @param ...$args
     * @return mixed
     */
    public function lockedFile(string $lockKey, callable $callable, ...$args): mixed
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
            } else if (isset($this->_debug)) {
                $this->_debug->relay($msg, 2);
            }
        }
        if (flock($fn, $operation)) {           //加锁
            try {

                $rest = $callable(...$args);    //执行

            } catch (\Error|\Exception $error) {
                $rest = 'locked: ' . $error->getMessage();
                $err = [];
                $err['file'] = $error->getFile();
                $err['line'] = $error->getLine();
                $err['message'] = $error->getMessage();
                $this->error($err);

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


/**
 * @param string $title
 * @param string ...$msg
 * @return void
 */
function esp_error(string $title, string ...$msg): void
{
    if (_CLI) {
        echo "{$title}\n";
        foreach ($msg as $s) echo "\t{$s}\n";
    } else {
        echo "<meta charset='utf-8' />\r\n<h2>{$title}</h2>\r\n<ul>\r\n";
        foreach ($msg as $s) echo "\t<li>{$s}</li>\r\n";
        echo "</ul>";
    }
    exit;
}

