<?php
declare(strict_types=1);

namespace esp\core;

final class Cookies
{
    public $domain;

    public function __construct()
    {
        if (_CLI) return;
        $this->domain = $this->getDomain();
    }

    public function set($key, $value, $ttl = null)
    {
        if (_CLI) return null;
        if (!is_int($ttl) and preg_match('/^(\d+)\s?([ymDhw])$/i', trim($ttl), $mat)) {
            $s = ['y' => 86400 * 365, 'm' => 86400 * 30, 'w' => 86400 * 7, 'd' => 86400, 'h' => 3600][strtolower($mat[2])];
            $ttl = (intval($mat[1]) * $s) + time();
        }
        if (is_array($value)) $value = json_encode($value, 256);

        if (version_compare(PHP_VERSION, '7.3', '>')) {
            $option = [];
            $option['domain'] = $this->domain;
            $option['expires'] = $ttl;
            $option['path'] = '/';
            $option['secure'] = _HTTPS;//仅https
            $option['httponly'] = true;
            $option['samesite'] = 'Lax';
            return setcookie(strtolower($key), $value, $option);
        }

        //function setcookie ($name, $value = "", $expire = 0, $path = "", $domain = "", $secure = false, $httponly = false) {}
        return setcookie(strtolower($key), $value, $ttl, '/', $this->domain, _HTTPS, true);
    }

    private function getDomain()
    {
        $cookies = $GLOBALS['cookies'] ?? [];
        $config = ($cookies['default'] ?? []) + ['run' => 1, 'domain' => 'host'];
        if (isset($cookies[_VIRTUAL])) $config = $cookies[_VIRTUAL] + $config;
        if (isset($cookies[_HOST])) $config = $cookies[_HOST] + $config;
        if (isset($cookies[_DOMAIN])) $config = $cookies[_DOMAIN] + $config;
        $domain = getenv('HTTP_HOST');
        return $config['domain'] === 'host' ? \esp\helper\host($domain) : getenv('HTTP_HOST');
    }

    public function del($key)
    {
        if (_CLI) return null;
        if (version_compare(PHP_VERSION, '7.3', '>')) {
            $option = [];
            $option['domain'] = $this->domain;
            $option['expires'] = -1;
            $option['path'] = '/';
            $option['secure'] = _HTTPS;//仅https
            $option['httponly'] = true;
            $option['samesite'] = 'Lax';
            return setcookie(strtolower($key), null, $option);
        }
        return setcookie(strtolower($key), null, -1, '/', $this->domain, _HTTPS, true);
    }

    public function get($key = null, $autoValue = null)
    {
        if (_CLI) return null;
        if (is_null($key)) return $_COOKIE;
        return $_COOKIE[strtolower($key)] ?? $autoValue;
    }

    public function disable()
    {
        if (empty($_COOKIE)) return false;
        if (version_compare(PHP_VERSION, '7.3', '>')) {
            $option = [];
            $option['domain'] = $this->domain;
            $option['expires'] = -1;
            $option['path'] = '/';
            $option['secure'] = _HTTPS;//仅https
            $option['httponly'] = true;
            $option['samesite'] = 'Lax';
            setcookie('_c', null, $option);
        } else {
            setcookie('_c', null, -1, '/', $this->domain, _HTTPS, true);
        }
        return empty($_COOKIE);
    }

}

