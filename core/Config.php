<?php

namespace esp\core;

use esp\core\db\Redis;

/**
 * Class Config
 * @package esp\core
 */
final class Config
{
    static private $_CONFIG_ = null;

    /**
     * @param array $config
     */
    public static function _init(array &$config)
    {
        self::$_CONFIG_ = Buffer::get('_CONFIG_');
        if (!empty(self::$_CONFIG_)) return;

        $config[] = __DIR__ . '/config/mime.ini';
        $config[] = __DIR__ . '/config/state.ini';
        $config[] = __DIR__ . '/config/ua.ini';

        self::$_CONFIG_ = Array();
        foreach ($config as $i => $file) {
            $_config = self::loadFile($file, $i);
            if (!empty($_config)) self::$_CONFIG_ = array_merge(self::$_CONFIG_, $_config);
        }
        self::$_CONFIG_ = self::re_arr(self::$_CONFIG_);
        Buffer::set('_CONFIG_', self::$_CONFIG_);
    }

    /**
     * @param string $file
     * @param string $byKey
     * @return array
     * @throws \Exception
     */
    private static function loadFile(string $file, $byKey = null): array
    {
        $fullName = root($file);
        if (!is_readable($fullName)) {
            throw new \Exception("配置文件{$file}不存在", 404);
        };
        $info = pathinfo($fullName);

        if ($info['extension'] === 'php') {
            $_config = include($fullName);
            if (!is_array($_config)) $_config = [];

        } elseif ($info['extension'] === 'ini') {
            $_config = parse_ini_file($fullName, true);
            if (!is_array($_config)) $_config = [];

        } elseif ($info['extension'] === 'json') {
            $_config = file_get_contents($fullName);
            $_config = json_decode($_config, true);
            if (!is_array($_config)) $_config = [];
        }

        if (isset($_config['include'])) {
            $include = $_config['include'];
            unset($_config['include']);
            foreach ($include as $key => $fil) {
                if (is_array($fil)) {
                    $_config[$key] = Array();
                    foreach ($fil as $l => $f) {
                        $_inc = self::loadFile(root($f), $l);
                        if (!empty($_inc)) $_config[$key] = $_inc + $_config[$key];
                    }
                } else {
                    $_inc = self::loadFile(root($fil), $key);
                    if (!empty($_inc)) $_config = $_inc + $_config;
                }
            }
        }
        if (is_null($byKey) or is_int($byKey) or is_numeric($byKey)) $byKey = $info['filename'];

        return empty($_config) ? [] : [$byKey => $_config];
    }

    /**
     * 加载在format时没载入的，不经过缓存
     * @param $key
     * @param null $auto
     * @return array|mixed|null
     */
    public static function load($file, $key = null, $auto = null)
    {
        $conf = parse_ini_file(root($file), true);
        $conf = self::re_arr($conf);
        if (is_null($key)) return $conf;

        $key = preg_replace('/[\.\,\/]+/', '.', strtolower($key));
        if (strrpos($key, '.')) {
            $keys = explode('.', trim($key, '.'));
            $_config = $conf;
            foreach ($keys as $k) {
                $_config = isset($_config[$k]) ? $_config[$k] : null;
                if (is_null($_config)) return $auto;
            }
            return $_config;
        }
        return isset($conf[$key]) ? $conf[$key] : $auto;
    }


    private static function re_key($val)
    {
        $search = array('{_HOST}', '{_ROOT}', '{_DOMAIN}', '{_TIME}', '{_DATE}');
        $replace = array(_HOST, _ROOT, _DOMAIN, _TIME, date('YmdHis', _TIME));
        $value = str_ireplace($search, $replace, $val);
        if (substr($value, 0, 1) === '[' and substr($value, -1, 1) === ']') {
            $arr = json_decode($value, true);
            if (is_array($arr)) $value = $arr;
        } else if (is_numeric($value)) {
            $value = intval($value);
        }
        return $value;
    }

    private static function re_arr($array)
    {
        $val = Array();
        foreach ($array as $k => $arr) {
            if (is_array($arr)) {
                $val[strtolower($k)] = self::re_arr($arr);
            } else {
                $val[strtolower($k)] = self::re_key($arr);
            }
        }
        return $val;
    }

    /**
     * 读取config，可以用get('key1.key2')的方式读取多维数组值
     * @param array ...$key
     * @return null|array|string
     */
    public static function get(...$key)
    {
        if (empty($key)) return self::$_CONFIG_;
        $conf = &self::$_CONFIG_;
        foreach (explode('.', implode('.', $key)) as $k) {
            if ($k === '' or $k === '*') return $conf;
            if (!isset($conf[$k])) return null;
            $conf = &$conf[$k];
        }
        return $conf;
    }

    public static function set($key, $value)
    {
        self::$_CONFIG_[$key] = $value;
    }


    /**
     * @param $type
     * @return string
     */
    public static function mime(string $type): string
    {
        $mime = self::get('mime', $type);
        if (!$mime) $mime = 'text/html';
        return $mime;
    }

    /**
     * @param $code
     * @return null|string
     */
    public static function states(int $code): string
    {
        $state = self::get('state', $code);
        if (!$state) $state = 'Unexpected';
        return $state;
    }

    /**
     * @param string $type
     * @return string
     */
    public static function ua(string $type): string
    {
        $ua = self::get('ua', $type);
        if (is_array($ua)) $ua = json_encode($ua, 256);
        if (!$ua) $ua = '';
        return $ua;
    }

}