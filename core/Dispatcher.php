<?php
//declare(strict_types=1);

namespace esp\core;

final class Dispatcher
{
    public $_plugs = array();
    private $_plugs_count = 0;//引入的Bootstrap数量
    public $_request;
    public $_response;
    public $_session;
    public $_config;
    public $_debug;
    public $_cache;
    private $run = true;

    public function __construct(array $option, string $virtual = 'www')
    {
        if (!defined('_ROOT')) {
            exit("网站入口处须定义 _ROOT 项，指向系统根目录");
        }
        define('_DAY_TIME', strtotime(date('Ymd')));//今天零时整的时间戳
        define('_CLI', (PHP_SAPI === 'cli' or php_sapi_name() === 'cli'));
        if (!defined('_DEBUG')) {
            define('_DEBUG', is_file(_RUNTIME . '/debug.lock'));
        }
        if (!defined('_VIRTUAL')) {
            define('_VIRTUAL', strtolower($virtual));
        }
        if (!defined('_SYSTEM')) {
            define('_SYSTEM', 'auto');
        }
        if (_CLI) {
            define('_URI', ('/' . trim(implode('/', array_slice($GLOBALS['argv'], 1)), '/')));
        } else {
            define('_URI', parse_url(getenv('REQUEST_URI'), PHP_URL_PATH));
            if (_URI === '/favicon.ico') {
                exit;
            }
        }

        $ip = '127.0.0.1';
        if (!_CLI) {
            foreach (['X-REAL-IP', 'X-FORWARDED-FOR', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'HTTP_X_CLIENT_IP', 'HTTP_X_CLUSTER_CLIENT_IP', 'REMOTE_ADDR'] as $k) {
                if (!empty($ip = ($_SERVER[$k] ?? null))) {
                    if (strpos($ip, ',')) {
                        $ip = explode(',', $ip)[0];
                    }
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                        break;
                    }
                }
            }
        }
        define('_CIP', $ip);

        if (isset($option['callback'])) {
            $option['callback']($option);
        }
        $option += ['error' => [], 'config' => []];
        //以下2项必须在`chdir()`之前，且顺序不可变
        if (!_CLI) {
            $err = new Error($this, $option['error']);
        }
        $this->_config = new Configure($option['config']);
        chdir(_ROOT);

        $this->_request = new Request($this->_config->get('frame.request'));
        if (_CLI) {
            return;
        }

        $this->_response = new Response($this->_config, $this->_request);

        if ($debug = $this->_config->get('debug')) {
            $this->_debug = new Debug($this->_request, $this->_response, $this->_config->Redis(), $debug);
            $GLOBALS['_Debug'] = $this->_debug;
        }

        if (($session = $this->_config->get('session')) and !_CLI) {
            $config = $session['default'] + ['run' => 1];
            if (isset($session[_VIRTUAL])) {
                $config = $session[_VIRTUAL] + $config;
            }
            if (isset($session[_HOST])) {
                $config = $session[_HOST] + $config;
            }
            if (isset($session[_DOMAIN])) {
                $config = $session[_DOMAIN] + $config;
            }
            if ($config['run']) {
                $this->_session = new Session($config, $this->_debug);
                if (!is_null($this->_debug)) {
                    $this->_debug->relay(['cookies' => $_COOKIE, 'session' => $_SESSION]);
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

        if (isset($option['attack'])) {
            $option['attack']($option);
        }

        $GLOBALS['cookies'] = $this->_config->get('cookies');
        unset($GLOBALS['option']);
        if (headers_sent($file, $line)) {
            throw new \Exception("在{$file}[{$line}]行已有数据输出，系统无法启动");
        }
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
     */
    public function bootstrap($class): Dispatcher
    {
        if (is_string($class)) {
            if (!class_exists($class)) {
                throw new \Exception("Bootstrap类不存在，请检查{$class}.php文件", 404);
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
     */
    public function setPlugin(Plugin $class): Dispatcher
    {
        $name = get_class($class);
        $name = ucfirst(substr($name, strrpos($name, '\\') + 1));
        if (isset($this->_plugs[$name])) {
            throw new \Exception("插件名{$name}已被注册过", 404);
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
            return;
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
     * 系统运行调度中心
     * @param callable|null $callable
     */
    public function run(callable $callable = null): void
    {
        if ($callable and call_user_func($callable)) {
            return;
        }

        if ($this->run === false) {
            return;
        }

        if ($this->_plugs_count and ($hook = $this->plugsHook('routeBefore')) and (is_array($hook) or is_string($hook))) {
            $this->_response->display($hook);
            return;
        }

        (new Router($this->_config, $this->_request));

        if ($this->_plugs_count and ($hook = $this->plugsHook('routeAfter')) and (is_array($hook) or is_string($hook))) {
            $this->_response->display($hook);
            return;
        }

        if (!_CLI and !is_null($this->_cache)) {
            if ($this->_cache->Display()) {
                goto end;
            }
        }

        if ($this->_plugs_count and ($hook = $this->plugsHook('dispatchBefore')) and (is_array($hook) or is_string($hook))) {
            $this->_response->display($hook);
            return;
        }

        $value = $this->dispatch();//运行控制器->方法

        if ($this->_plugs_count and ($hook = $this->plugsHook('dispatchAfter')) and (is_array($hook) or is_string($hook))) {
            $this->_response->display($hook);
            return;
        }

        if ($this->_plugs_count and ($hook = $this->plugsHook('displayBefore')) and (is_array($hook) or is_string($hook))) {
            $this->_response->display($hook);
            return;
        }

        if (_CLI) {
            print_r($value);
        } else {
            if (!is_null($this->_session) and !is_null($this->_debug)) {
                $this->_debug->relay(['_SESSION' => $_SESSION, 'Update' => var_export($this->_session->update, true)]);
                session_write_close();//立即保存并结束
            }
            $this->_response->display($value);
        }
        $this->_plugs_count and $hook = $this->plugsHook('displayAfter');

        if (!_CLI and _DEBUG and $this->_debug->_save_type !== 'cgi') {
            fastcgi_finish_request();
        } //运行结束，客户端断开
        if (!_CLI and !is_null($this->_cache)) {
            $this->_cache->Save();
        }

        end:
        $this->_plugs_count and $hook = $this->plugsHook('mainEnd');

        if (!is_null($this->_debug)) {
            if ($this->_debug->_save_type === 'cgi') {
                $save = $this->_debug->save_logs('Dispatcher Debug');
                $this->check_debug($save);
                if (!$this->_request->isAjax()) {
                    var_dump($save);
                }
                return;
            }

            register_shutdown_function(function () {
                $save = $this->_debug->save_logs('Dispatcher');
//                $this->check_debug($save);
            });
        }
    }

    private function check_debug($save, $file = null): void
    {
//        if (!getenv('HTTP_DEBUG')) return;
        $testDebug = [];
        $file .= $this->_request->controller . '_' . $this->_request->action;
        $testDebug['ua'] = getenv('HTTP_USER_AGENT');
        $testDebug['ver'] = getenv('HTTP_DEBUG');
        $testDebug['file'] = $this->_debug->filename();
        $testDebug['save'] = $save;
        file_put_contents(_RUNTIME . '/debug/' . date('YmdHis_') . $file . '.txt', json_encode($testDebug, 64 | 128 | 256), LOCK_EX);
    }

    /**
     * 路由结果分发至控制器动作
     * @return mixed
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
         * 加载控制器，也可以在composer中预加载
         * "admin\\": "application/admin/controllers/",
         */
        $base = $this->_request->directory . "/{$virtual}/controllers/Base{$contExt}.php";
        $file = $this->_request->directory . "/{$virtual}/controllers/{$cFile}.php";
        if (is_readable($base)) {
            load($base);
        }//加载控制器公共类，有可能不存在
        if (!load($file)) {
            return $this->err404("[{$this->_request->directory}/{$virtual}/controllers/{$cFile}.php] not exists.");
        }
        $cName = '\\' . $module . '\\' . $cFile;
        if (!$contExt) {
            $cName .= 'Controller';
        }

        if (!class_exists($cName)) {
            return $this->err404("[{$cName}] not exists.");
        }
        $cont = new $cName($this);
        if (!$cont instanceof Controller) {
            throw new \Exception("{$cName} 须继承自 \\esp\\core\\Controller", 404);
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
            if (!is_null($this->_debug)) {
                $this->_debug->relay("[blue;{$cName}->_init() ============================]", []);
            }
            $contReturn = call_user_func_array([$cont, '_init'], [$action]);
            if (!is_null($contReturn)) {
                if (!is_null($this->_debug)) {
                    $this->_debug->relay(['_init' => 'return', 'return' => $contReturn], []);
                }
                return $contReturn;
            }
        }

        if (method_exists($cont, '_main') and is_callable([$cont, '_main'])) {
            if (!is_null($this->_debug)) {
                $this->_debug->relay("[blue;{$cName}->_main() =============================]", []);
            }
            $contReturn = call_user_func_array([$cont, '_main'], [$action]);
            if (!is_null($contReturn)) {
                if (!is_null($this->_debug)) {
                    $this->_debug->relay(['_main' => 'return', 'return' => $contReturn], []);
                }
                return $contReturn;
            }
        }

        /**
         * 正式请求到控制器
         */
        if (!is_null($this->_debug)) {
            $this->_debug->relay("[green;{$cName}->{$action} Star ==============================]", []);
        }
        $contReturn = call_user_func_array([$cont, $action], $this->_request->params);
        if (!is_null($this->_debug)) {
            $this->_debug->relay("[red;{$cName}->{$action} End ==============================]", []);
        }
        if ($this->_request->loop === true) {
            $this->_request->loop = false;
            goto LOOP;
        }

        //运行结束方法
        if (method_exists($cont, '_close') and is_callable([$cont, '_close'])) {
            $clo = call_user_func_array([$cont, '_close'], [$action, $contReturn]);
            if (!is_null($clo)) {
                $contReturn = $clo;
            }
            if (!is_null($this->_debug)) {
                $this->_debug->relay("[red;{$cName}->_close() ==================================]", []);
            }
        }

        $rest = $cont->ReorganizeReturn($contReturn);
        if (!is_null($rest)) {
            return $rest;
        }

        return $contReturn;
    }


    final private function err404(string $msg)
    {
        if (!is_null($this->_debug)) {
            $this->_debug->folder('error');
        }
        if (_DEBUG) {
            return $msg;
        }
        $empty = $this->_config->get('frame.request.empty');
        if (!empty($empty)) {
            return $empty;
        }
        return $msg;
    }
}
