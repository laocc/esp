<?php

namespace esp\core;

use esp\library\ext\Markdown;

final class View
{
    private static $_view_val = Array();
    private static $_view_set = [
        'view_use' => true,
        'view_file' => null,
        'layout_use' => true,
        'layout_file' => null,
    ];

    /**
     * 读取视图目录
     * @return string
     */
    private static function path()
    {
        static $dir;
        if (!is_null($dir)) return $dir;

        $dir = Request::$directory . '/' . Request::$module . '/views/';
        if (Client::is_wap()) {
            if (is_dir($dir_wap = Request::$directory . '/' . Request::$module . '/views_wap/')) {
                $dir = $dir_wap;
            }
        }
        return $dir;
    }


    /**
     * 设置视图文件
     * @param $value
     */
    public static function setView($value)
    {
        if (is_bool($value)) {
            self::$_view_set['view_use'] = $value;
        } elseif (is_string($value)) {
            self::$_view_set['view_use'] = true;
            self::$_view_set['view_file'] = $value;
        }
    }

    /**
     * @param $value
     */
    public static function setLayout($value)
    {
        if (is_bool($value)) {
            self::$_view_set['layout_use'] = $value;
        } elseif (is_string($value)) {
            self::$_view_set['layout_use'] = true;
            self::$_view_set['layout_file'] = $value;
        }
    }


    /**
     * @param $path
     * @return array|mixed|string
     * @throws \Exception
     */
    private static function getFile($path)
    {
        $viewFile = self::$_view_set['view_file'];
        if (substr($viewFile, 0, 1) === '/') {
            $viewFile = root($viewFile);
        } else if (empty($viewFile)) {
            $viewFile = $path . '/' . Request::$controller . '/' . Request::$action . '.php';
        } else {
            $viewFile = $path . '/' . ltrim($viewFile, '/');
        }
        if (!is_readable($viewFile) or !is_file($viewFile)) {
            throw new \Exception("视图文件({$viewFile})不存在", 400);
        }
        return $viewFile;
    }

    /**
     * 解析视图结果并返回
     * @return string
     */
    public static function render()
    {
        $path = root(self::path());//视图目录
        Layout::cleared_layout_val();
        $layoutValue = Layout::getValue();

        $viewFile = self::getFile($path);
        if (!self::$_view_set['layout_use']) {//无框架
            return self::fetch($viewFile, self::$_view_val + $layoutValue);
        }

//        if (substr($viewFile, -3) === '.md') {
        if (Response::getType() === 'md') {
            $html = Markdown::html(file_get_contents($viewFile), 0);
//            $html = "<article class='markdown' style='width:90%;margin:0 auto;'>{$html}</article>";

        } else {
            $html = self::fetch($viewFile, self::$_view_val);
        }

        return self::fetch(Layout::getFile($path), $layoutValue + ['_view_html' => &$html]);
    }


    /**
     * 解析视图并返回
     * @param string $__file__
     * @param array $__value__
     * @return string
     */
    private static function fetch(string $__file__, array $__value__)
    {
        ob_start();
        extract($__value__);
        include($__file__);
        $content = ob_get_contents();
        ob_end_clean();
        return $content;
    }

    /**
     * 向视图送变量
     * @param $name
     * @param $value
     */
    public static function assign(string $name, $value = null)
    {
        if (is_array($name)) {
            foreach ($name as $k => $v) {
                self::$_view_val[$k] = $v;
            }
        } else {
            self::$_view_val[$name] = $value;
        }
    }

    public static function get(string $name)
    {
        return isset(self::$_view_val[$name]) ? self::$_view_val[$name] : null;
    }

    public static function set(string $name, $value)
    {
        if (is_array($name)) {
            foreach ($name as $k => $v) {
                self::$_view_val[$k] = $v;
            }
        } else {
            self::$_view_val[$name] = $value;
        }
    }


}
