<?php

namespace esp\core;


final class Dispatcher
{
    private static $debug_run = false;

    /**
     * 系统运行调度中心
     * @throws \Exception
     */
    public static function run(array &$option): void
    {
        if (isset($option['debug'])) {
            self::$debug_run = true;
            Debug::_init($option['debug']);
        }
        self::define();

        /**
         * 以下三项必须在chdir之前，且三项顺序不可变
         */
        if (isset($option['error'])) Error::register_handler($option['error']);
        if (isset($option['buffer'])) Buffer::_init($option['buffer']);
        if (isset($option['config'])) Config::_init($option['config']);

        chdir(_ROOT);


        Request::_init($option['request']);
        Response::_init($option['response']);

        if (!_CLI) {
            if (isset($option['session'])) Session::_init($option['session']);
            if (isset($option['cache'])) Cache::_init($option['cache']);
        }

//        unset($GLOBALS['option']);

        Route::_init($option['router']);


        if (!_CLI and isset($option['cache'])) if (Cache::Display()) goto end;

        self::$debug_run and Debug::relay('Dispatcher Star', []);
        $dispatch = self::dispatch();
        Response::display($dispatch);//运行控制器->方法
        self::$debug_run and Debug::relay('Dispatcher Display', []);

        if (!_CLI) fastcgi_finish_request(); //运行结束，客户端断开
        if (!_CLI and isset($option['cache'])) Cache::save();

        end:
    }


    private static function define()
    {
        if (!defined('_ROOT')) exit("网站入口处须定义 _ROOT 项，指向系统根目录");
        if (!defined('_MODULE')) exit('网站入口处须定义 _MODULE 项');

        define('_TIME', time());//今天零时整的时间戳
        define('_DAY_TIME', strtotime(date('Ymd', _TIME)));//今天零时整的时间戳
        define('_CLI', (PHP_SAPI === 'cli' or php_sapi_name() === 'cli'));
        define('_DEBUG', is_file(_ROOT . '/cache/debug.lock'));

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
     * 路由结果分发至控制器动作
     * @return mixed
     * @throws \Exception
     */
    private static function dispatch()
    {
        $suffix = Request::$suffix;
        $actionExt = $suffix['get'];
        if ((strtoupper(getenv('REQUEST_METHOD')) === 'POST') and ($p = $suffix['post'])) $actionExt = $p;
        elseif ((strtolower(getenv('HTTP_X_REQUESTED_WITH')) === 'xmlhttprequest') and ($p = $suffix['ajax'])) $actionExt = $p;

        $module = strtolower(Request::$module);
        $controller = ucfirst(strtolower(Request::$controller));
        $action = strtolower(Request::$action) . ucfirst($actionExt);

        if ($controller === 'Base') throw new \Exception('控制器名不可以为base，这是系统保留公共控制器名', 404);

        $base = Request::$directory . "/{$module}/controllers/Base.php";
        $file = Request::$directory . "/{$module}/controllers/{$controller}.php";
        if (is_readable($base)) load($base);//加载控制器公共类，有可能不存在
        if (!load($file)) {
            throw new \Exception("控制器[{$file}]不存在", 404);
        }

        self::$debug_run and Debug::relay('Controller Create', []);
        $controller = $module . '\\' . $controller . 'Controller';
        $_Controller = new $controller();
        if (!$_Controller instanceof Controller) {
            throw new \Exception("{$controller} 须继承自 \\esp\\core\\Controller", 404);
        }

        if (!method_exists($_Controller, $action) or !is_callable([$_Controller, $action])) {
            if (_CLI) {
                // cli中，若路由结果的action不存在，则检查是否存在indexAction，若存在，则调用此方法，并将原action作为参数提交到此方法
                if (method_exists($_Controller, 'index' . ucfirst($actionExt)) and is_callable([$_Controller, 'index' . ucfirst($actionExt)])) {
                    array_unshift(Request::getParams(), Request::$action);
                    Request::$action = 'index';
                    $action = strtolower(Request::$action) . ucfirst($actionExt);
                }
            } else {
                throw new \Exception("控制器[{$controller}]动作[{$action}]方法不存在或不可运行", 404);
            }
        }

        //运行初始化方法
        if (method_exists($_Controller, '_init') and is_callable([$_Controller, '_init'])) {
            call_user_func_array([$_Controller, '_init'], [$action]);
        }

        /**
         * 正式请求到控制器
         */
        self::$debug_run and Debug::relay('Controller Action Call', []);
        $val = call_user_func_array([$_Controller, $action], Request::getParams());
        self::$debug_run and Debug::relay('Controller Action End', []);

        //运行结束方法
        if (method_exists($_Controller, '_close') and is_callable([$_Controller, '_close'])) {
            call_user_func_array([$_Controller, '_close'], [$action, $val]);
        }
        unset($_Controller);
        self::$debug_run and Debug::relay('Controller Unset', []);
        return $val;
    }


}