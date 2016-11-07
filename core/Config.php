<?php
namespace wbf\core;


class Config
{
    const _DIRECTORY = 'application';   //网站主程序所在路径，不含模块名
    const _CONTROL = 'Controller';      //控制器名后缀，注意：文件名不含这部分
    const _ACTION = 'Action';           //动作名后缀
    const _VIEW_EXT = 'phtml';          //视图文件后缀
    const _DEFAULT_MODULE = 'www';      //默认模块
    const _DEFAULT_CONTROL = 'index';   //默认控制器
    const _DEFAULT_ACTION = 'index';    //默认动作
    const _LAYOUT = 'layout.phtml';     //框架视图文件名

    static private $_conf = [];


    public static function load()
    {
        if (!empty(self::$_conf)) return;

        $file = ['config.php'];
        foreach ($file as $fil) {
            $_conf = self::load_file(root("config/{$fil}"));
            if (is_array($_conf) && !empty($_conf)) {
                self::$_conf = array_merge(self::$_conf, $_conf);
            }
        }
    }

    public static function get($key)
    {
        return isset(self::$_conf[$key]) ? self::$_conf[$key] : null;
    }

    public static function set($key, $value)
    {
        self::$_conf[$key] = $value;
    }

    private static function load_file($file)
    {
        if (!$file) return false;
        return @include root($file);
    }


}