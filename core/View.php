<?php
namespace wbf\core;


class View
{
    private $_path = [
        'dir' => null,
        'file' => null,
    ];
    private $_view_val = [];
    private $_layout;//框架对象
    private $_adapter;//标签解析器对象

    public function __construct($dir, $file)
    {
        $this->_path['dir'] = $dir;
        $this->_path['file'] = $file;


        $this->_adapter instanceof View and 1;
    }

    /**
     * 设置或获取视图路径
     */
    public function dir($dir = null)
    {
        if (is_null($dir)) {
            return $this->_path['dir'];
        } else {
            return $this->_path['dir'] = $dir;
        }
    }

    /**
     * 设置视图文件名
     */
    public function file($file = null)
    {
        if (is_null($file)) {
            return $this->_path['file'];
        } else {
            return $this->_path['file'] = $file;
        }
    }

    /**
     * 视图接收变量
     * @param $name
     * @param $value
     */
    public function assign($name, $value)
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
     * 设置框架对象
     */
    public function layout(View $object)
    {
        $this->_layout = $object;
        return $this;
    }

    /**
     * @param null $object
     * @return $this|\Smarty
     */
    public function adapter($object = null)
    {
        if (is_null($object)) return $this->_adapter;

        $this->_adapter = $object;
        return $this;
    }


    /**
     * 解析视图结果并返回
     */
    public function render($file, $value)
    {
        $dir = $this->dir();
        $file = $this->file() ?: $file;

        if (stripos($file, $dir) !== 0) $file = rtrim($dir, '/') . '/' . ltrim($file, '/');
        if (!is_file($file)) exit("视图文件{$file}不存在");

        if ($this->_layout instanceof View) {

            $html = $this->fetch($file, $value + $this->_view_val);

            return $this->_layout->render($file, ['_body_html' => &$html]);
        }

        return $this->fetch($file, $value + $this->_view_val);
    }

    /**
     * 显示解析视图结果
     */
    public function display($file, $value)
    {
        echo $this->render($file, $value);
    }


    private function fetch($file, $value)
    {
        if (!is_null($this->_adapter)) {
            $adp = $this->adapter();
            $adp->assign($value);
            return $adp->fetch($file, []);
        }

        ob_start();
        extract($value);
        include($file);
        $content = ob_get_contents();
        ob_end_clean();
        return $content;
    }


}