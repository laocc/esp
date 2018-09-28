<?php

namespace esp\core;


use esp\core\db\Redis;
use esp\library\Tools;

final class Dispatcher
{
    public $_plugs = Array();
    private $_plugs_count = 0;
    public $_request;
    public $_response;
    public $_debug;
    public $_cache;
    public $_buffer;//缓存介质

    /**
     * Dispatcher constructor.
     * @param array $option
     * @throws \Exception
     */
    public function __construct(array $option)
    {
        if (!defined('_MODULE')) {
            throw new \Exception("网站入口处须定义 _MODULE 项", 404);
        }
        if (!defined('_ROOT')) {
            throw new \Exception("网站入口处须定义 _ROOT 项，指向系统根目录", 404);
        }

        define('_DAY_TIME', strtotime(date('Ymd')));//今天零时整的时间戳
        define('_CLI', (PHP_SAPI === 'cli' or php_sapi_name() === 'cli'));
        if (_CLI) {
            define('_DOMAIN', null);
            define('_HOST', null);//域名的根域
            define('_URI', ('/' . trim(implode('/', array_slice($GLOBALS['argv'], 1)), '/')));
            define('_HTTPS', false);
            define('_URL', null);
            define('_HTTP_DOMAIN', null);

        } else {
            define('_DOMAIN', explode(':', getenv('HTTP_HOST') . ':')[0]);
            define('_HOST', host(_DOMAIN));//域名的根域
            define('_URI', parse_url(getenv('REQUEST_URI'), PHP_URL_PATH));
            if (_URI === '/favicon.ico') die();

            /**
             * 若服务器为负截均衡架构，且主分发点没有做HTTPS，需要识别负截点是否有HTTPS，
             * 在负载点Nginx中加参数：【proxy_set_header HTTPS $https;】
             */
            define('_HTTPS', (getenv('HTTP_HTTPS') === 'on' or getenv('HTTPS') === 'on'));
            define('_URL', ((_HTTPS ? 'https://' : 'http://') . _DOMAIN . getenv('REQUEST_URI')));
            define('_HTTP_DOMAIN', ((_HTTPS ? 'https://' : 'http://') . _DOMAIN));
        }

        chdir(_ROOT);

        $this->_buffer = new Redis($option['buffer'] + ['_from' => 'dispatcher'], 0);

        Config::_init($this->_buffer, $option['config']);

        $this->_request = new Request($option['request']);
        $this->_response = new Response($this->_request);

        if (_CLI) {
            $this->_response->autoRun(false);
        } else {
            if (isset($option['session'])) Session::_init($option['session']);
            if (isset($option['debug'])) {
                $this->_debug = new Debug($this->_request, $this->_response, $option['debug'], $option['error']);
            } else {
                Debug::simple_register_handler();
            }
            if (isset($option['cache'])) $this->_cache = new Cache($this, $option['cache']);
        }

        unset($GLOBALS['option']);
    }

    public function __destruct()
    {

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
     * @return $this
     * @throws \Exception
     */
    public function bootstrap(): Dispatcher
    {
        if (!class_exists('Bootstrap')) {
            throw new \Exception('Bootstrap类不存在，请检查/helper/Bootstrap.php文件', 404);
        }
        $boot = new \Bootstrap();
        foreach (get_class_methods($boot) as $method) {
            if (substr($method, 0, 5) === '_init') {
                call_user_func_array([$boot, $method], [$this]);
            }
        }
        return $this;
    }

    /**
     * 接受注册插件
     * @param Plugin $class
     * @return bool
     * @throws \Exception
     */
    public function setPlugin(Plugin $class): bool
    {
        $name = get_class($class);
        $name = ucfirst(substr($name, strrpos($name, '\\') + 1));
        if (isset($this->_plugs[$name])) {
            throw new \Exception("插件名{$name}已被注册过", 404);
        }
        $this->_plugs[$name] = $class;
        $this->_plugs_count++;
        return true;
    }

    /**
     * 执行HOOK
     * @param $time
     */
    private function plugsHook(string $time): void
    {
        if (empty($this->_plugs)) return;
        if (!in_array($time, ['routeBefore', 'routeAfter', 'dispatchBefore', 'dispatchAfter', 'displayBefore', 'displayAfter', 'mainEnd'])) return;
        foreach ($this->_plugs as &$plug) {
            if (method_exists($plug, $time)) {
                call_user_func_array([$plug, $time], [$this->_request, $this->_response]);
            }
        }
    }

    /**
     * 系统运行调度中心
     * @throws \Exception
     */
    public function run(): void
    {
        $this->_plugs_count and $this->plugsHook('routeBefore');
        (new Route($this->_buffer, $this->_request));
        $this->_plugs_count and $this->plugsHook('routeAfter');

        if (!_CLI and !is_null($this->_cache)) if ($this->_cache->Display()) goto end;

        $this->_plugs_count and $this->plugsHook('dispatchBefore');
        $value = $this->dispatch();//运行控制器->方法
        $this->_plugs_count and $this->plugsHook('dispatchAfter');

        $this->_plugs_count and $this->plugsHook('displayBefore');
        $this->_response->display($value);
        $this->_plugs_count and $this->plugsHook('displayAfter');

        if (!_CLI) fastcgi_finish_request(); //运行结束，客户端断开
        if (!_CLI and !is_null($this->_cache)) $this->_cache->Save();

        end:
        $this->_plugs_count and $this->plugsHook('mainEnd');
    }


    /**
     * 路由结果分发至控制器动作
     * @return mixed
     * @throws \Exception
     */
    private function dispatch()
    {
        $suffix = &$this->_request->suffix;
        $actionExt = $suffix['get'];
        if ((strtoupper(getenv('REQUEST_METHOD')) === 'POST') and ($p = $suffix['post'])) $actionExt = $p;
        elseif ((strtolower(getenv('HTTP_X_REQUESTED_WITH')) === 'xmlhttprequest') and ($p = $suffix['ajax'])) $actionExt = $p;

        $module = strtolower($this->_request->module);
        $controller = ucfirst(strtolower($this->_request->controller));
        $action = strtolower($this->_request->action) . ucfirst($actionExt);

        if ($controller === 'Base') throw new \Exception('控制器名不可以为base，这是系统保留公共控制器名', 404);

        $base = $this->_request->directory . "/{$module}/controllers/Base.php";
        $file = $this->_request->directory . "/{$module}/controllers/{$controller}.php";
        if (is_readable($base)) load($base);//加载控制器公共类，有可能不存在
        if (!load($file)) {
            throw new \Exception("控制器[{$file}]不存在", 404);
        }

        $controller = $module . '\\' . $controller . 'Controller';
        $GLOBALS['_Controller'] = new $controller($this);
        if (!$GLOBALS['_Controller'] instanceof Controller) {
            throw new \Exception("{$controller} 须继承自 \\esp\\core\\Controller", 404);
        }

        if (!method_exists($GLOBALS['_Controller'], $action) or !is_callable([$GLOBALS['_Controller'], $action])) {
            if (_CLI) {
                // cli中，若路由结果的action不存在，则检查是否存在indexAction，若存在，则调用此方法，并将原action作为参数提交到此方法
                if (method_exists($GLOBALS['_Controller'], 'index' . ucfirst($actionExt)) and is_callable([$GLOBALS['_Controller'], 'index' . ucfirst($actionExt)])) {
                    array_unshift($this->_request->params, $this->_request->action);
                    $this->_request->action = 'index';
                    $action = strtolower($this->_request->action) . ucfirst($actionExt);
                }
            } else {
                throw new \Exception("控制器[{$controller}]动作[{$action}]方法不存在或不可运行", 404);
            }
        }

        //运行初始化方法
        if (method_exists($GLOBALS['_Controller'], '_init') and is_callable([$GLOBALS['_Controller'], '_init'])) {
            call_user_func_array([$GLOBALS['_Controller'], '_init'], [$action]);
        }

        /**
         * 正式请求到控制器
         */
        if (!is_null($this->_debug)) $this->_debug->relay('ControllerStar', []);
        $val = call_user_func_array([$GLOBALS['_Controller'], $action], $this->_request->params);
        if (!is_null($this->_debug)) $this->_debug->relay('ControllerStop', []);

        //运行结束方法
        if (method_exists($GLOBALS['_Controller'], '_close') and is_callable([$GLOBALS['_Controller'], '_close'])) {
            call_user_func_array([$GLOBALS['_Controller'], '_close'], [$action, $val]);
        }

        unset($GLOBALS['_Controller']);
        return $val;
    }


}