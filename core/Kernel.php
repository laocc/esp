<?php
namespace esp\core;


final class Kernel
{
    private $request;
    private $response;
    private $_Plugin = [];

    private $_shutdown;
    private $_autoDisplay = true;

    public function __construct()
    {
        if (!defined('_MODULE')) exit('网站入口处须定义_MODULE项');
        if (!defined('_ROOT')) exit('网站入口处须定义_ROOT项');

        define('_CLI', (PHP_SAPI === 'cli' or php_sapi_name() === 'cli'));
        define('_IP', _CLI ? '127.0.0.1' : server('x-real-ip'));//客户端IP
        define('_HTTPS', strtolower(server('HTTPS')) === 'on');
        define('_DOMAIN', _CLI ? null : explode(':', server('HTTP_HOST') . ':')[0]);
        define('_HOST', _CLI ? null : host(_DOMAIN));//域名的根域
        define('_RAND', mt_rand());

        chdir(_ROOT);
        Mistake::init();
        Config::load();
        Session::init();

        $this->request = new Request();
        $this->response = new Response($this->request);
    }

    public function bootstrap()
    {
        if (!class_exists('Bootstrap')) {
            exit("Bootstrap类不存在，请检查/helper/bootstrap.php文件");
        }
        $boot = new \Bootstrap();
        foreach (get_class_methods($boot) as $method) {
            if (stripos($method, '_init') === 0) {
                call_user_func_array([$boot, $method], [$this]);
            }
        }
        return $this;
    }

    /**
     * 注册关门方法，exit后也会被执行
     * 在网站入口处明确调用才会注册
     * @return bool|Kernel
     */
    public function shutdown()
    {
        if (!is_null($this->_shutdown)) {
            return $this->response->shutdown();
        }
        $this->_shutdown = true;
        register_shutdown_function(function () {
            shutdown($this);
        });
        return $this;
    }

    /**
     * 接受注册插件
     * 或，返回已注册的插件
     * @param object $class
     */
    public function setPlugin($class)
    {
        $name = get_class($class);
        if (!is_subclass_of($class, Plugin::class)) {
            exit('插件' . $name . '须直接继承自\\esp\\core\\Plugin');
        }
        $name = ucfirst(substr($name, strrpos($name, '\\') + 1));
        if (isset($this->_Plugin[$name])) exit("插件名{$name}已被注册过");
        $this->_Plugin[$name] = &$class;
    }

    /**
     * 执行HOOK
     * @param $time
     */
    private function plugsHook($time)
    {
        if (empty($this->_Plugin)) return;
        if (!in_array($time, ['routeBefore', 'routeAfter', 'dispatchBefore', 'dispatchAfter', 'kernelEnd'])) return;
        foreach ($this->_Plugin as &$plug) {
            if (method_exists($plug, $time)) {
                call_user_func_array([$plug, $time], [$this->request, $this->response]);
            }
        }
    }


    /**
     * @return Request
     */
    public function getRequest()
    {
        return $this->request;
    }

    public function getResponse()
    {
        return $this->response;
    }

    /**
     * 是否自动渲染
     * @param null $run
     * @return bool
     */
    public function autoDisplay($run = null)
    {
        if (is_null($run)) return $this->_autoDisplay;
        return $this->_autoDisplay = !!$run;
    }

    /**
     * 系统运行调度中心
     * @param null $module
     */
    public function run()
    {
        $loop = Config::get('esp.maxLoop');
        $this->plugsHook('routeBefore');
        (new Route())->matching($this->request);
        $this->plugsHook('routeAfter');
        $this->cache('display');
        $this->plugsHook('loopBefore');

        loop:
        $this->plugsHook('dispatchBefore');
        $this->dispatch();
        $this->plugsHook('dispatchAfter');
        if ($this->request->loop === true) {
            //控制器跳转，并初始化Response
            if (--$loop > 0) {
                $this->response = new Response($this->request);
                goto loop;
            }
        }
        $this->plugsHook('loopAfter');
        if ($this->_autoDisplay) $this->response->display();

        $this->cache('save');

        $this->plugsHook('kernelEnd');
    }

    private function cache($action)
    {
        static $cache;
        if (is_null($cache)) $cache = new Cache($this->request, $this->response);
        $action = 'cache' . ucfirst($action);
        $cache->{$action}();
    }

    /**
     * 路由结果分发至控制器动作
     * @param $request
     * @param $control
     */
    private function dispatch()
    {
        $module = strtolower($this->request->module);
        $controller = ucfirst($this->request->controller);
        $action = strtolower($this->request->action) . Config::get('esp.actionExt');

        if ($controller === 'Base') error('控制器名不可以为base，这是系统保留公共控制器名');

        //加载控制器公共类，有可能不存在
        load($this->request->directory . "{$module}/controllers/Base.php");
        $file = $this->request->directory . "{$module}/controllers/{$controller}.php";

        //路由需要请求的控制器
        if (!load($file)) error("控制器文件[{$file}]不存在");

        $controller = $module . '\\' . $controller . Config::get('esp.controlExt');

        $control = new $controller($this->_Plugin, $this->request, $this->response);
        if (!$control instanceof Controller) {
            error("{$controller} 须继承自 esp\\core\\Controller");
        }
        if (!method_exists($control, $action) or !is_callable([$control, $action])) {
            error("控制器[{$controller}]动作[{$action}]方法不存在或不可运行");
        }
        $this->request->loop = false;
        call_user_func_array([$control, $action], $this->request->params);
        unset($control);
    }


}