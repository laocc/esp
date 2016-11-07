<?php
namespace wbf\core;


final class Kernel
{
    public $request;
    private $_Plugin;

    public function __construct()
    {
        if (!defined('_MODULE')) exit('网站入口处须定义_MODULE项');
        if (!defined('_ROOT')) exit('网站入口处须定义_ROOT项');
    }

    public function bootstrap($bootstrap = null)
    {
        Mistake::init();
        Config::load();

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

    public function shutdown()
    {
        register_shutdown_function(function () {
            shutdown();
        });
        return $this;
    }

    /**
     * 接受注册插件
     * @param resource $class
     */
    public function setPlugin($name, $class = null)
    {
        if (!is_null($class)) {
            if (!is_subclass_of($class, Plugs::class)) {
                exit('插件' . get_class($class) . '须继承自\\wbf\\core\\Plugs');
            }
            $this->_Plugin[$name] = $class;
            return true;
        } else {
            if (isset($this->_Plugin[$name])) {
                return $this->_Plugin[$name];
            } else {
                exit("系统没有注册插件[{$name}]");
            }
        }
    }


    public function run($module = null)
    {
        Config::load();

        $module = $module ?: _MODULE;
        $routes = $this->_load("config/routes_{$module}.php");
        if (!$routes) exit("未定义当前模块路由：/config/routes_{$module}.php");

        //开始路由
        $route = new Route();
        $route->matching($routes, $this->request);

        //开始分发到控制器
        $this->dispatch($this->request, $control);
        $control instanceof Controller && 1;

        //控制器显示
        $control->display_response();
    }


    /**
     * 路由结果分发至控制器动作
     * @param $request
     * @param $control
     * @return bool|mixed
     */
    private function dispatch(&$request, &$control)
    {
        $module = strtolower($request['module']);
        $controller = ucfirst($request['controller']);
        $action = ucfirst($request['action']) . Config::get('wbf.actionExt');

        //加载控制器公共类，有可能不存在
        $this->_load(Config::get('wbf.directory') . "/{$module}/controllers/Controller.php");

        $file = Config::get('wbf.directory') . "/{$module}/controllers/{$controller}.php";

        //路由需要请求的控制器
        if (!$this->_load($file)) {
            exit("控制器文件[{$file}]不存在");
        }

        $controller .= Config::get('wbf.controlExt');
        $control = new $controller($this, $request);
        if (!$control instanceof Controller) {
            exit("{$controller} 须继承自 wbf\\core\\Controller");
        }

        if (!method_exists($control, $action) or !is_callable([$control, $action])) return false;
        return call_user_func_array([$control, $action], $request['params']);
    }


    private function _load($file)
    {
        if (!$file) return false;
        $file = root($file);
        if (!is_file($file)) return false;
        return include $file;
    }

}