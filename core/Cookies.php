<?php

namespace esp\core;

final class Cookies
{

    public static function set($key, $value, $ttl = null)
    {
        if (_CLI) return null;
        if (!is_url($ttl) and preg_match('/^(\d+)\s?([ymDhw])$/i', trim($ttl), $mat)) {
            $s = ['y' => 86400 * 365, 'm' => 86400 * 30, 'w' => 86400 * 7, 'd' => 86400, 'h' => 3600][strtolower($mat[2])];
            $ttl = (intval($mat[1]) * $s) + time();
        }
        return setcookie($key, $value, $ttl, '/', '.' . _HOST, _HTTPS, true);
    }

    public static function del($key)
    {
        if (_CLI) return null;
        return setcookie($key, null, time() - 3600, '/', '.' . _HOST, _HTTPS, true);
    }

    public static function get($key = null, $autoValue = null)
    {
        if (_CLI) return null;
        if (is_null($key)) return $_COOKIE;
        return $_COOKIE[$key] ?? $autoValue;
    }

    public static function disable()
    {
        $empty = empty($_COOKIE);
        if (!$empty) return false;
        setcookie('_c', 1, time() + 10, '/', '.' . _HOST, _HTTPS, true);
        $empty = empty($_COOKIE);
        return $empty;
    }

}

