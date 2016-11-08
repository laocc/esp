<?php
namespace esp\core;


final class Kernel
{
    private $request;
    private $response;
    private $_Plugin;
    private $_shutdown;

    public function __construct()
    {
        if (!defined('_MODULE')) exit('网站入口处须定义_MODULE项');
        if (!defined('_ROOT')) exit('网站入口处须定义_ROOT项');

        chdir(_ROOT);
        Mistake::init();
        Config::load();
        $this->request = new Request();
        $this->response = new Response($this->request);
    }

    public function bootstrap()
    {
        if (!class_exists('Bootstrap')) {
            exit("Bootstrap类不存在，请检查/helper/ootstrap.php文件");
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
    public function setPlugin($name, $class)
    {
        if (!is_subclass_of($class, Plugin::class)) {
            exit('插件' . get_class($class) . '须直接继承自\\esp\\core\\Plugin');
        }
        if (isset($this->_Plugin[$name])) exit("插件名{$name}已被注册过");
        $this->_Plugin[$name] = &$class;
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
     * 执行HOOK
     * @param $time
     */
    private function plugsHook($time)
    {
        if (!in_array($time, ['routeBefore', 'routeAfter', 'dispatchAfter', 'kernelEnd'])) return;
        foreach ($this->_Plugin as &$plug) {
            if (method_exists($plug, $time)) {
                call_user_func_array([$plug, $time], [$this->request, $this->response]);
            }
        }
    }

    /**
     * 系统运行调度中心
     * @param null $module
     */
    public function run()
    {
        $module = _MODULE;
        $routes = $this->_load("config/routes_{$module}.php");
        if (!$routes) exit("未定义当前模块路由：/config/routes_{$module}.php");
        $this->plugsHook('routeBefore');
        (new Route())->matching($routes, $this->request);
        $this->plugsHook('routeAfter');
        $this->dispatch($this->request);    //开始分发到控制器
        $this->plugsHook('dispatchAfter');
        $this->response->display();         //结果显示
        $this->plugsHook('kernelEnd');
    }


    /**
     * 路由结果分发至控制器动作
     * @param $request
     * @param $control
     * @return bool|mixed
     */
    private function dispatch(Request &$request)
    {
        $module = strtolower($request->module);
        $controller = ucfirst($request->controller);
        $action = ucfirst($request->action) . Config::get('esp.actionExt');

        if ($controller === 'Base') error('控制器名不可以为base，这是系统保留公共控制器名。');

        //加载控制器公共类，有可能不存在
        $this->_load(Config::get('esp.directory') . "/{$module}/controllers/Base.php");

        $file = Config::get('esp.directory') . "/{$module}/controllers/{$controller}.php";

        //路由需要请求的控制器
        if (!$this->_load($file)) {
            exit("控制器文件[{$file}]不存在");
        }

        $controller .= Config::get('esp.controlExt');
        $control = new $controller($this->_Plugin, $request, $this->response);
        if (!$control instanceof Controller) {
            exit("{$controller} 须继承自 esp\\core\\Controller");
        }
        if (!method_exists($control, $action) or !is_callable([$control, $action])) return false;
        return call_user_func_array([$control, $action], $request->params);
    }


    /**
     * 加载文件
     * @param $file
     * @return bool|mixed
     */
    private function _load($file)
    {
        if (!$file) return false;
        $file = root($file);
        if (!is_readable($file)) return false;
        return include $file;
    }

}