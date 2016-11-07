<?php
namespace wbf\core;


class Controller
{
    private $_kernel;
    private $_request;
    private $_view_val = [];
    private $_use_layout = true;
    private $_use_adapter = true;

    final public function __construct(Kernel $kernel, $request)
    {
        $this->_kernel = $kernel;
        $this->_request = $request;
    }

    /**
     * 设置视图文件，或获取对象
     * @return View
     */
    final public function view($file = null)
    {
        static $obj;
        if (!is_null($obj)) return $obj;
        $dir = rtrim($this->_request['directory'], '/') . '/' . $this->_request['module'] . '/views/';
        return $obj = new View($dir, $file);
    }

    final public function adapter($bool = null)
    {
        if ($bool === false) {
            return $this->_use_adapter = $bool;
        }

        static $_adapter;
        if (!is_null($_adapter)) return $_adapter;

        $conf = Config::get('adapter');
        if (!$conf) return null;
        $dir = rtrim($this->_request['directory'], '/') . '/' . $this->_request['module'] . '/views/';
        $this->_use_adapter = true;

        if ($conf['driver'] === 'smarty') {
            $_adapter = new \Smarty();
            $_adapter->{'template_dir'} = $dir;//视图主目录
            $_adapter->{'compile_dir'} = root($conf['compile_dir']);//解析器缓存目录
            $_adapter->{'cache_dir'} = root($conf['cache_dir']);//缓存目录
        } else {
            exit('当前只实现了smarty解析器');
        }

        return $_adapter;
    }

    /**
     * 关闭，或获取layout对象，可同时指定框架文件
     * @param null $file
     * @return bool|View
     */
    final public function layout($layout_file = null)
    {
        if ($layout_file === false) {
            return $this->_use_layout = false;
        }

        static $obj;
        if (!is_null($obj)) return $obj;

        $dir = rtrim($this->_request['directory'], '/') . '/' . $this->_request['module'] . '/views/';
        $layout_file = $layout_file ?: $this->_request['controller'] . '/' . Config::_LAYOUT;
        if (stripos($layout_file, $dir) !== 0) $layout_file = $dir . ltrim($layout_file, '/');
        if (!is_file($layout_file)) $layout_file = $dir . Config::_LAYOUT;
        if (!is_file($layout_file)) exit('框架视图文件不存在');
        $this->_use_layout = true;
        return $obj = new View($dir, $layout_file);
    }


    /**
     * @param $request
     */
    final public function setRequest($request)
    {
        $this->_request = $request;
    }


    /**
     * 向视图送变量
     * @param $name
     * @param $value
     */
    final public function assign($name, $value)
    {
        $this->_view_val[$name] = $value;
    }

    final public function __set($name, $value)
    {
        $this->_view_val[$name] = $value;
    }

    final public function __get($name)
    {
        return isset($this->_view_val[$name]) ? $this->_view_val[$name] : null;
    }

    final public function set($name, $value)
    {
        $this->_view_val[$name] = $value;
    }

    final public function get($name)
    {
        return isset($this->_view_val[$name]) ? $this->_view_val[$name] : null;
    }


    /**
     * 最后显示内容
     */
    final public function display()
    {
        $view = $this->view();
        $file = $this->_request['controller'] . '/' . $this->_request['action'] . '.' . ltrim(Config::_VIEW_EXT, '.');

        //送入框架对象
        if ($this->_use_layout) $view->layout($this->layout());
        if ($this->_use_adapter) $view->adapter($this->adapter());

        $view->display($file, $this->_view_val);
    }


}