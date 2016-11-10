<?php
namespace esp\core;

final class Cookies
{
    public static function set($key, $value, $ttl = null)
    {
        if (preg_match('/^(\d+)\s?([ymDhw])$/i', trim($ttl), $mat)) {
            $s = ['y' => 86400 * 365, 'm' => 86400 * 30, 'w' => 86400 * 7, 'd' => 86400, 'h' => 3600][strtolower($mat[2])];
            $ttl = intval($mat[1]) * $s;
        }
        $ttl = !!$ttl ? (time() + $ttl) : null;
        return setcookie($key, $value, $ttl, '/', '.' . _HOST, _HTTPS, true);
    }

    public static function del($key)
    {
        return setcookie($key, null, time() - 3600, '/', '.' . _HOST, _HTTPS, true);
    }

    public static function get($key = null, $autoValue = null)
    {
        if ($key === null) return $_COOKIE;
        return isset($_COOKIE[$key]) ? $_COOKIE[$key] : $autoValue;
    }


}

