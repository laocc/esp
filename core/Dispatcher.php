<?php
declare(strict_types=1);

namespace esp\core;

use esp\error\Error;
use esp\error\EspError;
use esp\library\Result;
use function esp\helper\same_first;
use function esp\helper\host;

final class Dispatcher
{
    private $_plugs_count = 0;//引入的plugs数量
    private $run = true;//任一个钩子若返回false，则不再执行run()方法中的后续内容
    public $_plugs = array();
    public $_request;
    public $_response;
    public $_session;
    public $_cookies;
    public $_config;
    public $_debug;
    public $_cache;

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
        if (getenv('REQUEST_METHOD') === 'HEAD') die('OK');

        if (!defined('_CLI')) define('_CLI', (PHP_SAPI === 'cli' or php_sapi_name() === 'cli'));
        if (!getenv('HTTP_HOST') && !_CLI) die('unknown host');
        if (!defined('_ROOT')) {    //网站根目录
            define('_ROOT', _CLI ? getenv('PWD') : rtrim(same_first(__DIR__, getenv('DOCUMENT_ROOT')), '/'));
        }
        if (!defined('_ESP_ROOT')) define('_ESP_ROOT', dirname(__DIR__));//esp框架自身的根目录
        if (!defined('_RUNTIME')) define('_RUNTIME', _ROOT . '/runtime');
        if (!defined('_DEBUG')) define('_DEBUG', is_file(_RUNTIME . '/debug.lock'));
        if (!defined('_VIRTUAL')) define('_VIRTUAL', strtolower($virtual));
        if (!defined('_DOMAIN')) define('_DOMAIN', explode(':', getenv('HTTP_HOST') . ':')[0]);
        if (!defined('_HOST')) define('_HOST', host(_DOMAIN));//域名的根域
        if (!defined('_HTTPS')) define('_HTTPS', (getenv('HTTP_HTTPS') === 'on' or getenv('HTTPS') === 'on'));
        if (!defined('_HTTP_')) define('_HTTP_', (_HTTPS ? 'https://' : 'http://'));
        if (!defined('_URL')) define('_URL', _HTTP_ . _DOMAIN . getenv('REQUEST_URI'));

        $ip = '127.0.0.1';
        if (_CLI) {
            define('_URI', ('/' . trim(implode('/', array_slice($GLOBALS['argv'], 1)), '/')));
        } else {
            define('_URI', parse_url(getenv('REQUEST_URI'), PHP_URL_PATH));
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
        if (!_CLI) new Error($this, $option['error'] ?? []);
        $this->_config = new Configure($option['config'] ?? []);
        chdir(_ROOT);
        $request = $this->_config->get('request');
        $this->_request = new Request($this, $request);
        if (_CLI) return;

        if (!defined('_TIME')) define('_TIME', time());//当前时间戳
        if (!defined('_DAY_TIME')) define('_DAY_TIME', strtotime(date('Ymd', _TIME)));//今天零时整的时间戳

        //控制器、并发计数
        if ($request['concurrent'] ?? '') {
            $this->_request->recodeConcurrent($this->_config->_Redis);
        }

        $resourceConf = $this->_config->get('resource');
        $resource = $this->mergeConf($resourceConf);
        $resource['_rand'] = $this->_config->_Redis->get('resourceRand') ?: date('YmdH');
        $this->_response = new Response($this, $resource);

        if ($debugConf = $this->_config->get('debug')) {
            $debug = $this->mergeConf($debugConf);
            if ($debug['run'] ?? 0) {
                $this->_debug = new \esp\debug\Debug($debug);
                $GLOBALS['_Debug'] = $this->_debug;
            } else {
                $this->_debug = new Debug([]);
                $GLOBALS['_Debug'] = $this->_debug;
            }
        }

        if ($cookies = $this->_config->get('cookies')) {
            $cokConf = $this->mergeConf($cookies, ($cookies['default'] ?? []) + ['run' => false, 'debug' => false, 'domain' => 'host']);

            if ($cokConf['run'] ?? false) {
                $this->_cookies = new Cookies($cokConf);
                if ($cokConf['debug']) $this->relayDebug(['cookies' => $_COOKIE]);

                //若不启用Cookies，则也不启用Session
                if ($session = ($this->_config->get('session'))) {
                    $sseConf = $this->mergeConf($session, ($session['default'] ?? []) + ['run' => false, 'domain' => $cokConf['domain']]);

                    if ($sseConf['run'] ?? false) {

                        $rds = $this->_config->get('database.redis');
                        $cID = $rds['db'];
                        if (is_array($cID)) $cID = $cID['config'] ?? 1;

                        $rdsConf = ($sseConf['redis'] ?? []) + $rds;
                        if (is_array($rdsConf['db'])) $rdsConf['db'] = $rdsConf['db']['session'] ?? 0;
                        if ($rdsConf['db'] === 0) $rdsConf['db'] = $cID;
                        if ($rdsConf['db'] === $cID) $sseConf['object'] = $this->_config->_Redis->redis;
                        $sseConf['redis'] = $rdsConf;

                        $this->_session = new Session($sseConf, $this->_debug);
                        if ($this->_session->debug) $this->relayDebug(['session' => $_SESSION]);

                    }
                }
            }
        }

        if ($cacheConf = $this->_config->get('cache')) {
            $cache = $this->mergeConf($cacheConf);
            if ($cache['run'] ?? 0) $this->_cache = new Cache($this, $cache);
        }

        if (isset($option['after'])) $option['after']($option);

        unset($GLOBALS['option']);
        if (headers_sent($file, $line)) {
            throw new EspError("在{$file}[{$line}]行已有数据输出，系统无法启动");
        }
    }

    /**
     * 合并设置
     *
     * @param $allConf
     * @param null $conf
     * @return array|null
     */
    private function mergeConf($allConf, $conf = null)
    {
        if (is_null($conf)) $conf = $allConf['default'];

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

    private function relayDebug($info)
    {
        if (is_null($this->_debug)) return;
        $this->_debug->relay($info, []);
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
     * @param string $class
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
     * @param string $time
     * @param null $runValue
     * @return mixed|null
     */
    private function plugsHook(string $time, $runValue = null)
    {
        if (empty($this->_plugs)) return null;

        if (!in_array($time, ['routeBefore', 'routeAfter', 'dispatchBefore', 'dispatchAfter', 'displayBefore', 'displayAfter', 'mainEnd'])) {
            return null;
        }

        foreach ($this->_plugs as $plug) {
            if (method_exists($plug, $time) and is_callable([$plug, $time])) {
                return call_user_func_array([$plug, $time], [$this->_request, $this->_response, $runValue]);
            }
        }

        return null;
    }


    /**
     * 系统运行调度中心
     * @param callable|null $callable
     * @throws EspError
     */
    public function run(callable $callable = null): void
    {
        $showDebug = isset($_GET['_debug']);
        if ($this->run === false) goto end;
        if ($callable and call_user_func($callable)) goto end;
        if (_CLI) throw new EspError("cli环境中请直接调用\$this->simple()方法");

        if ($this->_plugs_count and !is_null($hook = $this->plugsHook('routeBefore'))) {
            $this->_response->display($hook);
            goto end;
        }

        $route = (new Router())->run($this->_config, $this->_request);
        if (is_string($route)) exit($route);

        $this->_debug->setRouter([
            'virtual' => $this->_request->virtual,
            'method' => $this->_request->getMethod(),
            'module' => $this->_request->module,
            'controller' => $this->_request->controller,
            'action' => $this->_request->action,
            'exists' => $this->_request->exists,
            'params' => $this->_request->params,
        ]);

        //控制器、并发计数
        if ($this->_request->counter['counter']) {
            $this->_request->recodeCounter($this->_config->_Redis);
        }

        if ($this->_plugs_count and !is_null($hook = $this->plugsHook('routeAfter'))) {
            $this->_response->display($hook);
            goto end;
        }

        if (!is_null($this->_cache)) {
            if ($this->_cache->Display()) {
                fastcgi_finish_request();//运行结束，客户端断开
                $this->relayDebug("[blue;客户端已断开 =============================]");
                goto end;
            }
        }

        if ($this->_plugs_count and !is_null($hook = $this->plugsHook('dispatchBefore'))) {
            $this->_response->display($hook);
            goto end;
        }

        //TODO 运行控制器->方法
        $value = $this->dispatch();

        if ($this->_plugs_count and !is_null($hook = $this->plugsHook('dispatchAfter', $value))) {
            $this->_response->display($hook);
            goto end;
        }

        if ($this->_plugs_count and !is_null($hook = $this->plugsHook('displayBefore', $value))) {
            $this->_response->display($hook);
            goto end;
        }

        if (!is_null($this->_session)) {
            if ($this->_session->debug) $this->relayDebug(['_SESSION' => $_SESSION, 'Update' => var_export($this->_session->update, true)]);
            session_write_close();//立即保存并结束
        }

        $this->_response->display($value);

        $this->_plugs_count and $hook = $this->plugsHook('displayAfter', $value);

        if (!_DEBUG and !$showDebug) fastcgi_finish_request();//运行结束，客户端断开

        $this->relayDebug("[blue;客户端已断开 =============================]");

        if (!is_null($this->_cache)) $this->_cache->Save();

        end:
        $this->_plugs_count and $hook = $this->plugsHook('mainEnd');

        if (is_null($this->_debug)) return;

        $this->_debug->setResponse([
            'type' => $this->_response->_Content_Type,
            'display' => $this->_response->_display_Result
        ]);

        if ($this->_debug->mode === 'shutdown' or $showDebug) {
            $save = $this->_debug->save_logs('DispatcherCgi');
            if ($showDebug) var_dump($save);

        } else {
            register_shutdown_function(function () {
                $this->_debug->save_logs('Dispatcher');
            });
        }
    }

    /**
     * 不运行plugs，不执行缓存，不执行session保存
     *
     * @throws EspError
     */
    public function simple()
    {
        $showDebug = isset($_GET['_debug']);
        if ($this->run === false) goto end;

        $route = (new Router())->run($this->_config, $this->_request);
        if (is_string($route)) exit($route);

        if (!_CLI) {
            $this->_debug->setRouter([
                'virtual' => $this->_request->virtual,
                'method' => $this->_request->getMethod(),
                'module' => $this->_request->module,
                'controller' => $this->_request->controller,
                'action' => $this->_request->action,
                'exists' => $this->_request->exists,
                'params' => $this->_request->params,
            ]);

            //控制器、并发计数
            if ($this->_request->counter['counter']) {
                $this->_request->recodeCounter($this->_config->_Redis);
            }
        }

        $value = $this->dispatch();

        if (_CLI) {
            print_r($value);
            return;
        }

        $this->_response->display($value);

        end:
        if (!_DEBUG and !$showDebug) fastcgi_finish_request();

        if (is_null($this->_debug)) return;

        $this->_debug->setResponse([
            'type' => $this->_response->_Content_Type,
            'display' => $this->_response->_display_Result
        ]);

        if ($this->_debug->mode === 'shutdown' or $showDebug) {
            $save = $this->_debug->save_logs('minDispatcherCgi');
            if ($showDebug) var_dump($save);
        } else {
            register_shutdown_function(function () {
                $this->_debug->save_logs('minDispatcher');
            });
        }
    }

    public function min()
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
        $contExt = $this->_request->contFix ?: 'Controller';
        $actionExt = $this->_request->getActionExt();

        LOOP:
        $virtual = $this->_request->virtual;
        if ($this->_request->module) $virtual .= '\\' . $this->_request->module;

        $controller = ucfirst($this->_request->controller) . $contExt;
        $action = strtolower($this->_request->action) . $actionExt;

        $class = "\\application\\{$virtual}\\controllers\\{$controller}";
        if (!class_exists($class)) return $this->err404("[{$class}] not exists.");

        $cont = new $class($this);
        if (!($cont instanceof Controller)) {
            throw new EspError("{$class} 须继承自 \\esp\\core\\Controller");
        }

        if (!method_exists($cont, $action) or !is_callable([$cont, $action])) {
            $auto = strtolower($this->_request->action) . 'Action';
            if (method_exists($cont, $auto) and is_callable([$cont, $auto])) {
                $action = $auto;
            } else {
                return $this->err404("[{$class}::{$action}()] not exists.");
            }
        }

        $GLOBALS['_Controller'] = &$cont;//放入公共变量，后面Model需要读取

        /**
         * 运行初始化，一般这个放在Base中
         */
        if (method_exists($cont, '_init') and is_callable([$cont, '_init'])) {
            $this->relayDebug("[blue;{$class}->_init() ============================]");
            $contReturn = call_user_func_array([$cont, '_init'], [$action]);
            if (!is_null($contReturn)) {
                $this->relayDebug(['_init' => 'return', 'return' => $contReturn]);
                goto close;
            }
        }

        /**
         * 一般这个放在实际控制器中
         */
        if (method_exists($cont, '_main') and is_callable([$cont, '_main'])) {
            $this->relayDebug("[blue;{$class}->_main() =============================]");
            $contReturn = call_user_func_array([$cont, '_main'], [$action]);
            if (!is_null($contReturn)) {
                $this->relayDebug(['_main' => 'return', 'return' => $contReturn]);
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
            $clo = call_user_func_array([$cont, '_close'], [$action, $contReturn]);
            if (!is_null($clo)) $contReturn = $clo;
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


}
