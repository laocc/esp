<?php
declare(strict_types=1);

namespace esp\core;

use esp\core\ext\EspError;

final class Dispatcher
{
    public $_plugs = array();
    private $_plugs_count = 0;//引入的Bootstrap数量
    public $_request;
    public $_response;
    public $_session;
    public $_cookies;
    public $_config;
    public $_debug;
    public $_cache;
    private $run = true;

    /**
     * Dispatcher constructor.
     * @param array $option
     * @param string $virtual
     * @throws EspError
     */
    public function __construct(array $option, string $virtual = 'www')
    {
        if (!defined('_CLI')) define('_CLI', (PHP_SAPI === 'cli' or php_sapi_name() === 'cli'));
        if (!getenv('HTTP_HOST') && !_CLI) die('unknown host');
        if (!defined('_ROOT')) define('_ROOT', dirname(\esp\helper\same_first(__DIR__, getenv('DOCUMENT_ROOT'))));//网站根目录
        if (!defined('_ESP_ROOT')) define('_ESP_ROOT', dirname(__DIR__));//esp框架自身的根目录
        if (!defined('_RUNTIME')) define('_RUNTIME', _ROOT . '/runtime');
        if (!defined('_DAY_TIME')) define('_DAY_TIME', strtotime(date('Ymd')));//今天零时整的时间戳
        if (!defined('_DEBUG')) define('_DEBUG', is_file(_RUNTIME . '/debug.lock'));
        if (!defined('_VIRTUAL')) define('_VIRTUAL', strtolower($virtual));
        if (!defined('_DOMAIN')) define('_DOMAIN', explode(':', getenv('HTTP_HOST') . ':')[0]);
        if (!defined('_HOST')) define('_HOST', \esp\helper\host(_DOMAIN));//域名的根域
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
        if (!_CLI) $err = new Error($this, $option['error'] ?? []);
        $this->_config = new Configure($option['config'] ?? []);
        chdir(_ROOT);
        $this->_request = new Request($this, $this->_config);
        if (_CLI) return;

        $this->_response = new Response($this->_config, $this->_request);

        if ($debug = $this->_config->get('debug')) {
            $this->_debug = new Debug($this->_request, $this->_response, $this->_config, $debug);
            $GLOBALS['_Debug'] = $this->_debug;
        }

        if ($cookies = $this->_config->get('cookies') ?: $this->_config->get('frame.cookies')) {
            $cokConf = ($cookies['default'] ?? []) + ['run' => false, 'domain' => 'host'];
            if (isset($cookies[_VIRTUAL])) $cokConf = $cookies[_VIRTUAL] + $cokConf;
            if (isset($cookies[_HOST])) $cokConf = $cookies[_HOST] + $cokConf;
            if (isset($cookies[_DOMAIN])) $cokConf = $cookies[_DOMAIN] + $cokConf;
            if ($cokConf['run'] ?? false) {
                $this->_cookies = new Cookies($cokConf);
                $this->relayDebug(['cookies' => $_COOKIE]);

                //若不启用Cookies，则也不启用Session
                if ($session = ($this->_config->get('session') ?: $this->_config->get('frame.session'))) {
                    $sseConf = ($session['default'] ?? []) + ['run' => false];
                    if (isset($session[_VIRTUAL])) $sseConf = $session[_VIRTUAL] + $sseConf;
                    if (isset($session[_HOST])) $sseConf = $session[_HOST] + $sseConf;
                    if (isset($session[_DOMAIN])) $sseConf = $session[_DOMAIN] + $sseConf;
                    if ($sseConf['run'] ?? false) {
                        $this->_session = new Session($sseConf, $this->_debug);
                        $this->relayDebug(['session' => $_SESSION]);
                    }
                }
            }
        }

        if ($cache = $this->_config->get('cache')) {
            $setting = $cache['setting'];
            if (isset($cache[_VIRTUAL])) {
                $setting = $cache[_VIRTUAL] + $setting;
            }
            if (isset($setting['run']) and $setting['run']) {
                $this->_cache = new Cache($this, $setting);
            }
        }

        if (isset($option['after'])) $option['after']($option);

        unset($GLOBALS['option']);
        if (headers_sent($file, $line)) {
            throw new EspError("在{$file}[{$line}]行已有数据输出，系统无法启动");
        }
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
     * @param string $class '\library\Bootstrap'
     * @return Dispatcher
     * @throws EspError
     */
    public function bootstrap($class): Dispatcher
    {
        if (is_string($class)) {
            if (!class_exists($class)) {
                throw new EspError("Bootstrap类不存在，请检查{$class}.php文件", 404);
            }
            $class = new $class();
        }
        foreach (get_class_methods($class) as $method) {
            if (substr($method, 0, 5) === '_init') {
                $run = call_user_func_array([$class, $method], [$this]);
                if ($run === false) {
                    $this->run = false;
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
            throw new EspError("插件名{$name}已被注册过", 404);
        }
        $this->_plugs[$name] = $class;
        $this->_plugs_count++;
        return $this;
    }

    /**
     * 执行HOOK
     * @param string $time
     * @return mixed
     * bool:仅表示Hook是否执行
     * string或array，都将直接截断前端
     * string以原样返回
     * array以json返回
     */
    private function plugsHook(string $time)
    {
        if (empty($this->_plugs)) {
            return false;
        }
        if (!in_array($time, ['routeBefore', 'routeAfter', 'dispatchBefore', 'dispatchAfter', 'displayBefore', 'displayAfter', 'mainEnd'])) {
            return false;
        }
        foreach ($this->_plugs as $plug) {
            if (method_exists($plug, $time)) {
                $val = call_user_func_array([$plug, $time], [$this->_request, $this->_response]);
                if (is_string($val) or is_array($val)) {
                    return $val;
                }
            }
        }
        return true;
    }


    /**
     * 构造一个Debug空类
     */
    public function anonymousDebug()
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
     * 系统运行调度中心
     * @param callable|null $callable
     * @throws EspError
     */
    public function run(callable $callable = null): void
    {
        if ($callable and call_user_func($callable)) {
            goto end;
        }

        if ($this->run === false) {
            goto end;
        }

        if (!_CLI and $this->_plugs_count and ($hook = $this->plugsHook('routeBefore')) and (is_array($hook) or is_string($hook))) {
            $this->_response->display($hook);
            goto end;
        }

        (new Router($this->_config, $this->_request));

        if (!_CLI) {

            if ($this->_plugs_count and ($hook = $this->plugsHook('routeAfter')) and (is_array($hook) or is_string($hook))) {
                $this->_response->display($hook);
                goto end;
            }

            if (!_CLI and !is_null($this->_cache)) {
                if ($this->_cache->Display()) {
                    fastcgi_finish_request();//运行结束，客户端断开
                    $this->relayDebug("[blue;客户端已断开 =============================]");
                    goto end;
                }
            }

            if ($this->_plugs_count and ($hook = $this->plugsHook('dispatchBefore')) and (is_array($hook) or is_string($hook))) {
                $this->_response->display($hook);
                goto end;
            }
        }

        $value = $this->dispatch();//运行控制器->方法

        if (_CLI) {
            print_r($value);
            return;
        } else {

            if ($this->_plugs_count and ($hook = $this->plugsHook('dispatchAfter')) and (is_array($hook) or is_string($hook))) {
                $this->_response->display($hook);
                goto end;
            }

            if ($this->_plugs_count and ($hook = $this->plugsHook('displayBefore')) and (is_array($hook) or is_string($hook))) {
                $this->_response->display($hook);
                goto end;
            }

            if (!is_null($this->_session)) {
                $this->relayDebug(['_SESSION' => $_SESSION, 'Update' => var_export($this->_session->update, true)]);
                session_write_close();//立即保存并结束
            }
            $this->_response->display($value);

            $this->_plugs_count and $hook = $this->plugsHook('displayAfter');

            fastcgi_finish_request();//运行结束，客户端断开
            $this->relayDebug("[blue;客户端已断开 =============================]");

            if (!is_null($this->_cache)) $this->_cache->Save();
        }

        end:
        if (!_CLI) {
            $this->_plugs_count and $hook = $this->plugsHook('mainEnd');
            if (!is_null($this->_debug)) {
                register_shutdown_function(function () {
                    $this->_debug->save_logs('Dispatcher');
                });
            }
        }
    }

    /**
     * 路由结果分发至控制器动作
     * @return mixed
     * @throws EspError
     */
    private function dispatch()
    {
        $contExt = $this->_request->contFix;
        $actionExt = $this->_request->getActionExt();

        LOOP:
        if (empty($this->_request->module)) {
            $virtual = $this->_request->virtual;
            $module = $this->_request->virtual;
        } else {
            $virtual = "{$this->_request->virtual}/{$this->_request->module}";
            $module = $this->_request->virtual . '\\' . $this->_request->module;
        }
        $cFile = ucfirst($this->_request->controller) . $contExt;
        $action = strtolower($this->_request->action) . $actionExt;
        $auto = strtolower($this->_request->action) . 'Action';

        /**
         * 加载控制器，也可以在composer中预加载  "application\\": "application/",
         * 若不符合psr-4标准，则需要在入口入定义    define('_PSR4', false);
         */
        if (defined('_PSR4') and !_PSR4) {
            $base = $this->_request->directory . "/{$virtual}/controllers/Base{$contExt}.php";
            $file = $this->_request->directory . "/{$virtual}/controllers/{$cFile}.php";
            if (is_readable($base)) \esp\helper\load($base);

            //加载控制器公共类，有可能不存在
            if (!\esp\helper\load($file)) {
                return $this->err404("[{$this->_request->directory}/{$virtual}/controllers/{$cFile}.php] not exists.");
            }

            $cName = '\\' . $module . '\\' . $cFile;
        } else {
            $cName = '\\application\\' . $module . '\\controllers\\' . $cFile;
        }

        if (!$contExt) $cName .= 'Controller';

        if (!class_exists($cName)) return $this->err404("[{$cName}] not exists.");

        $cont = new $cName($this);
        if (!$cont instanceof Controller) {
            throw new EspError("{$cName} 须继承自 \\esp\\core\\Controller", 404);
        }

        if (!method_exists($cont, $action) or !is_callable([$cont, $action])) {
            if (method_exists($cont, $auto) and is_callable([$cont, $auto])) {
                $action = $auto;
            } else {
                return $this->err404("[{$cName}::{$action}()] not exists.");
            }
        }

        $GLOBALS['_Controller'] = &$cont;//放入公共变量，后面Model需要读取

        //运行初始化方法
        if (method_exists($cont, '_init') and is_callable([$cont, '_init'])) {
            $this->relayDebug("[blue;{$cName}->_init() ============================]");
            $contReturn = call_user_func_array([$cont, '_init'], [$action]);
            if (!is_null($contReturn)) {
                $this->relayDebug(['_init' => 'return', 'return' => $contReturn]);
                goto close;
            }
        }

        if (method_exists($cont, '_main') and is_callable([$cont, '_main'])) {
            $this->relayDebug("[blue;{$cName}->_main() =============================]");
            $contReturn = call_user_func_array([$cont, '_main'], [$action]);
            if (!is_null($contReturn)) {
                $this->relayDebug(['_main' => 'return', 'return' => $contReturn]);
                goto close;
            }
        }

        /**
         * 正式请求到控制器
         */
        $this->relayDebug("[green;{$cName}->{$action} Star ==============================]");
        $contReturn = call_user_func_array([$cont, $action], $this->_request->params);
        $this->relayDebug("[red;{$cName}->{$action} End ==============================]");

        //在控制器中，如果调用了reload方法，则所有请求数据已变化，loop将赋为true，开始重新加载
        if ($this->_request->loop === true) {
            $this->_request->loop = false;
            goto LOOP;
        }

        close:
        //运行结束方法
        if (method_exists($cont, '_close') and is_callable([$cont, '_close'])) {
            $clo = call_user_func_array([$cont, '_close'], [$action, $contReturn]);
            if (!is_null($clo)) $contReturn = $clo;
            $this->relayDebug("[red;{$cName}->_close() ==================================]");
        }

        if (!empty($cont->result) and $this->_request->isAjax() or $this->_request->isPost()) {
            $rest = $cont->ReorganizeReturn($contReturn);
            if (!is_null($rest)) return $rest;
        }

        return $contReturn;
    }


    private function err404(string $msg)
    {
        if (!is_null($this->_debug)) $this->_debug->folder('error');
        $empty = $this->_config->get('frame.request.empty');
        if (!empty($empty)) return $empty;
        return $msg;
    }
}
