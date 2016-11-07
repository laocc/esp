<?php
namespace wbf\core;


class Config
{
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