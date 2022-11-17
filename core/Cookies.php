<?php
declare(strict_types=1);

namespace esp\core;

use esp\error\Error;
use function esp\helper\str_rand;

final class Cookies
{
    public $domain;

    public function __construct(array $cookies)
    {
        $this->domain = ($cookies['domain'] === 'host') ? _HOST : _DOMAIN;
    }

    public function get($key = null, $autoValue = null)
    {
        if (_CLI) return null;
        if (is_null($key)) return $_COOKIE;
        return $_COOKIE[strtolower($key)] ?? $autoValue;
    }

    /**
     * @param string $key
     * @param $value
     * @param null $ttl
     * @return bool
     * @throws Error
     */
    public function set(string $key, $value, $ttl = null): bool
    {
        if (_CLI) return false;

        $this->checkHeader();

        if (is_null($ttl)) $ttl = time() - 1;
        else if (is_string($ttl) and preg_match('/^(\d+)\s?([ymDhw])$/i', trim($ttl), $mat)) {
            $s = ['y' => 'year', 'm' => 'month', 'w' => 'week', 'd' => 'day', 'h' => 'hour'][strtolower($mat[2])] ?? 'day';
            $ttl = strtotime("+{$mat[1]} {$s}");
        }
        if (is_array($value)) $value = json_encode($value, 256);

        $option = [];
        $option['domain'] = $this->domain;
        $option['expires'] = intval($ttl);
        $option['path'] = '/';
        $option['secure'] = _HTTPS;//仅https
        $option['httponly'] = true;
        $option['samesite'] = 'Lax';
        return setcookie(strtolower($key), strval($value), $option);
    }

    /**
     * 删除某一项
     * @param $key
     * @return bool
     * @throws Error
     */
    public function del($key): bool
    {
        if (_CLI) return false;
        $this->checkHeader();

        $option = [];
        $option['domain'] = $this->domain;
        $option['expires'] = -1;
        $option['path'] = '/';
        $option['secure'] = _HTTPS;//仅https
        $option['httponly'] = true;
        $option['samesite'] = 'Lax';
        return setcookie(strtolower($key), '', $option);
    }

    /**
     * 删除当前所有cookies
     *
     * @return bool
     * @throws Error
     */
    public function disable(): bool
    {
        if (empty($_COOKIE)) return false;
        $this->checkHeader();

        $option = [];
        $option['domain'] = $this->domain;
        $option['expires'] = -1;
        $option['path'] = '/';
        $option['secure'] = _HTTPS;//仅https
        $option['httponly'] = true;
        $option['samesite'] = 'Lax';
        setcookie('_c', '', $option);
        return empty($_COOKIE);
    }

    /**
     * 创建客户端唯一ID
     *
     * @param string $key
     * @param bool $number
     * @return string
     * @throws Error
     */
    public function cid(string $key = '_SSI', bool $number = false): string
    {
        $key = strtolower($key);
        $unique = $_COOKIE[$key] ?? null;
        if (!$unique) {
            $unique = $number ? mt_rand() : str_rand(20);
            $this->set($key, $unique, '100y');
        }
        return (string)$unique;
    }

    /**
     * @throws Error
     */
    private function checkHeader()
    {
        if (headers_sent($file, $line)) {
            $err = ['message' => "Header be Send:{$file}[{$line}]", 'code' => 500, 'file' => $file, 'line' => $line];
            throw new Error($err);
        }
    }

    /**
     * echo
     *
     * @return string
     */
    public function __toString()
    {
        return print_r($this, true);
    }

    /**
     * var_dump
     * @return array
     */
    public function __debugInfo()
    {
        return [__CLASS__];
    }


}

