<?php
//declare(strict_types=1);

namespace esp\core;

final class Cookies
{

    public static function set($key, $value, $ttl = null)
    {
        if (_CLI) return null;
        if (!is_int($ttl) and preg_match('/^(\d+)\s?([ymDhw])$/i', trim($ttl), $mat)) {
            $s = ['y' => 86400 * 365, 'm' => 86400 * 30, 'w' => 86400 * 7, 'd' => 86400, 'h' => 3600][strtolower($mat[2])];
            $ttl = (intval($mat[1]) * $s) + time();
        }
        if (is_array($value)) $value = json_encode($value, 256);
        return setcookie(strtolower($key), $value, $ttl, '/', self::domain(), _HTTPS, true);
    }

    public static function domain()
    {
        $cookies = $GLOBALS['cookies'] ?? [];
        $config = ($cookies['default'] ?? []) + ['run' => 1, 'domain' => 'host'];
        if (isset($cookies[_VIRTUAL])) $config = $cookies[_VIRTUAL] + $config;
        if (isset($cookies[_HOST])) $config = $cookies[_HOST] + $config;
        if (isset($cookies[_DOMAIN])) $config = $cookies[_DOMAIN] + $config;
        $domain = getenv('HTTP_HOST');
        return $config['domain'] === 'host' ? host($domain) : getenv('HTTP_HOST');
    }

    public static function del($key)
    {
        if (_CLI) return null;
        return setcookie(strtolower($key), null, -1, '/', self::domain(), _HTTPS, true);
    }

    public static function get($key = null, $autoValue = null)
    {
        if (_CLI) return null;
        if (is_null($key)) return $_COOKIE;
        return $_COOKIE[strtolower($key)] ?? $autoValue;
    }

    public static function disable()
    {
        $empty = empty($_COOKIE);
        if (!$empty) return false;
        setcookie('_c', null, -1, '/', self::domain(), _HTTPS, true);
        $empty = empty($_COOKIE);
        return $empty;
    }

}

