<?php
namespace io;

use \Yaf\Registry;


final class Session
{

    static private $keys = [];

    public static function set($key, $value, $ttl = 0)
    {
        self::check_status();
        if (preg_match('/^(\d+)\s?([ymDhw])$/i', trim($ttl), $mat)) {
            $s = ['y' => 86400 * 365, 'm' => 86400 * 30, 'w' => 86400 * 7, 'd' => 86400, 'h' => 3600][strtolower($mat[2])];
            $ttl = intval($mat[1]) * $s;
        }
        $_SESSION[$key] = $value;
        self::$keys[$key] = $ttl ?: 0;
        Registry::set('_session_new_keys', self::$keys);
    }

    public static function del($key)
    {
        self::check_status();
        unset($_SESSION[$key]);
    }

    public static function get($key = null, $autoValue = null)
    {
        self::check_status();
        if ($key === null) return $_SESSION;
        return isset($_SESSION[$key]) ? $_SESSION[$key] : $autoValue;
    }

    private static function check_status()
    {
        if (session_status() < 2) {
            error('session_start() no start,run it first in \config\config.ini');
        }
    }


}

