<?php
namespace wbf\core;


final class Kernel
{
    private $request;
    private $response;
    private $_Plugin;

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

    public function bootstrap($bootstrap = null)
    {
        if (!class_exists('Bootstrap')) {
            if (!load($bootstrap)) {
                exit("无法读取Bootstrap:{$bootstrap}");
            }
            if (!class_exists('Bootstrap')) {
                exit("Bootstrap不存在");
            }
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
     * @return $this
     */
    public function shutdown()
    {
        register_shutdown_function(function () {
            shutdown($this);
        });
        return $this;
    }

    /**
     * 接受注册插件
     * 或，返回已注册的插件
     * @param resource $class
     */
    public function setPlugin($name, $class = null)
    {
        if (!is_null($class)) {
            if (!is_subclass_of($class, Plugin::class)) {
                exit('插件' . get_class($class) . '须直接继承自\\wbf\\core\\Plugin');
            }
            $this->_Plugin[$name] = &$class;
            return true;
        } else {
            if (isset($this->_Plugin[$name])) {
                return $this->_Plugin[$name];
            } else {
                exit("系统没有注册插件[{$name}]");
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
     * 执行HOOK
     * @param $time
     */
    private function plugsHook($time)
    {
        if (!in_array($time, ['routeBefore', 'routeAfter', 'dispatchAfter', 'shutdown'])) return;

        foreach ($this->_Plugin as &$plug) {
            if (method_exists($plug, $time)) {
                call_user_func_array([$plug, $time], [$this->request]);
            }
        }
    }

    public function run($module = null)
    {
        Config::load();

        $module = $module ?: _MODULE;
        $routes = $this->_load("config/routes_{$module}.php");
        if (!$routes) exit("未定义当前模块路由：/config/routes_{$module}.php");

        $this->plugsHook('routeBefore');
        $route = new Route();        //开始路由
        $route->matching($routes, $this->request);
        $this->plugsHook('routeAfter');

        //开始分发到控制器
        $this->dispatch();
        $this->plugsHook('dispatchAfter');
        $this->response->display();
        $this->plugsHook('shutdown');
    }


    /**
     * 路由结果分发至控制器动作
     * @param $request
     * @param $control
     * @return bool|mixed
     */
    private function dispatch()
    {
        $module = strtolower($this->request->module);
        $controller = ucfirst($this->request->controller);
        $action = ucfirst($this->request->action) . Config::get('wbf.actionExt');

        //加载控制器公共类，有可能不存在
        $this->_load(Config::get('wbf.directory') . "/{$module}/controllers/Controller.php");

        $file = Config::get('wbf.directory') . "/{$module}/controllers/{$controller}.php";

        //路由需要请求的控制器
        if (!$this->_load($file)) {
            exit("控制器文件[{$file}]不存在");
        }

        $controller .= Config::get('wbf.controlExt');
        $control = new $controller($this->_Plugin, $this->request, $this->response);
        if (!$control instanceof Controller) {
            exit("{$controller} 须继承自 wbf\\core\\Controller");
        }
        if (!method_exists($control, $action) or !is_callable([$control, $action])) return false;
        return call_user_func_array([$control, $action], $this->request->params);
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