<?php
declare(strict_types=1);

namespace esp\fast;

use esp\core\Router;
use esp\debug\Counter;
use esp\debug\Debug;
use esp\help\Helps;
use esp\session\Session;
use esp\helper\library\Result;
use function esp\helper\host;

function esp_error(string $title, string ...$msg)
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

final class Dispatcher
{
    private int $_plugs_count = 0;//引入的plugs数量
    private bool $run = true;//任一个bootstrap若返回false，则不再执行run()方法中的后续内容
    public array $_plugs = array();

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
        if (!defined('_DOMAIN')) define('_DOMAIN', explode(':', getenv('HTTP_HOST') . ':')[0]);

        $ip = '127.0.0.1';
        if (_CLI) {
            if (!defined('_URI')) define('_URI', ('/' . trim(implode('/', array_slice($GLOBALS['argv'], 1)), '/')));
        } else {
            if (!defined('_URI')) define('_URI', parse_url(str_replace('//', '/', getenv('REQUEST_URI') ?: '/'), PHP_URL_PATH) ?: '/');
            //对于favicon.ico，建议在根目录直接放一个该文件，或在nginx中直接拦截
            foreach (['X-REAL-IP', 'X-FORWARDED-FOR', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'HTTP_X_CLIENT_IP', 'HTTP_X_CLUSTER_CLIENT_IP', 'REMOTE_ADDR'] as $k) {
                if (!empty($ip = ($_SERVER[$k] ?? null))) {
                    if (strpos($ip, ',')) $ip = explode(',', $ip)[0];
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) break;
                }
            }
        }
        if (!defined('_CIP')) define('_CIP', $ip);

        if (isset($option['before'])) $option['before']($option);

        /**
         * 切换之前是nginx中指定的root入口目录，
         * 切换后 getcwd() 的结果为_ROOT
         */
        chdir(_ROOT);
        if (_CLI) return;

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


        $route = (new Router($this->_config->_Redis))->run($this->_request, $alias);
        if (is_string($route)) {
            echo $route;
            fastcgi_finish_request();
            exit;
        }

        if (isset($this->_debug)) $this->_debug->setRouter($this->_request->RouterValue());

        //TODO 运行控制器->方法
        $value = $this->dispatch();
        if (_CLI) {
            print_r($value);
            echo "\r\n";
            return;
        }

        $this->_response->display($value);

        if (!_DEBUG and !$showDebug) fastcgi_finish_request();//运行结束，客户端断开

    }

    public function error($data, int $pre = 1): void
    {
        if (_CLI) return;
        if (!isset($this->_debug)) return;
        $this->_debug->error($data, $pre + 1);
    }

    /**
     * 路由结果分发至控制器动作
     * @return mixed
     */
    private function dispatch()
    {
        $actionExt = $this->_request->getActionExt();

        LOOP:
        $virtual = $this->_request->virtual;
        if ($this->_request->module) $virtual .= '\\' . $this->_request->module;

        if (_CLI && $this->_request->controller === '_esp') {
            $cont = new Helps($this);
            if (method_exists($cont, $this->_request->action) and is_callable([$cont, $this->_request->action])) {
                return call_user_func_array([$cont, $this->_request->action], $this->_request->params);
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

            if (_DEBUG) {
                return $this->_request->returnEmpty('controller', "[{$class}] 控制器不存在，请确认文件是否存在，或是否在composer.json中引用了控制器目录");
            } else {
                return $this->_request->returnEmpty('controller', "[{$this->_request->controller}] not exists.]");
            }
        }

        $cont = new $class($this);
        if (!($cont instanceof Controller)) {
            esp_error('Controller Error', "{$class} 须继承自 \\esp\\core\\Controller");
        }

        /**
         * 运行初始化，一般这个放在Base中
         */
        if (method_exists($cont, '_init') and is_callable([$cont, '_init'])) {
            $contReturn = call_user_func_array([$cont, '_init'], [$action]);
            if (is_bool($contReturn) or !is_null($contReturn)) {
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
                    return $this->_request->returnEmpty('action', "[{$class}::{$action}()] not exists.");
                }
            }
        }

        /**
         * 一般这个放在实际控制器中
         */
        if (method_exists($cont, '_main') and is_callable([$cont, '_main'])) {
            $contReturn = call_user_func_array([$cont, '_main'], [$action]);
            if (is_bool($contReturn) or !is_null($contReturn)) {
                if ($contReturn === false) $contReturn = null;
                goto close;
            }
        }

        /**
         * 正式请求到控制器
         */
        $contReturn = call_user_func_array([$cont, $action], $this->_request->params);

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
        }

        if ($contReturn instanceof Result) return $contReturn->display();
        else if (is_object($contReturn)) return (string)$contReturn;

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

            } catch (\Exception $exception) {
                $err = [];
                $err['file'] = $exception->getFile();
                $err['line'] = $exception->getLine();
                $err['message'] = $exception->getMessage();
                $this->error($err);

            } catch (\Error $error) {
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
            } else if (isset($this->_debug)) {
                $this->_debug->relay($msg, 2);
            }
        }
        if (flock($fn, $operation)) {           //加锁
            try {

                $rest = $callable(...$args);    //执行

            } catch (\Exception $exception) {
                $rest = 'locked: ' . $exception->getMessage();
                $err = [];
                $err['file'] = $exception->getFile();
                $err['line'] = $exception->getLine();
                $err['message'] = $exception->getMessage();
                $this->error($err);

            } catch (\Error $error) {
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
