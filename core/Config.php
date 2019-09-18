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
    static private $_Redis;
    static private $_token;

    /**
     * @param array $config
     * @throws \Exception
     */
    public static function _init(array $conf)
    {
        self::$_token = md5(_ROOT);
        $conf += ['path' => '/config'];
        $conf['path'] = root($conf['path']);
        $_rdsConf = parse_ini_file("{$conf['path']}/buffer.ini", true);
//        $_rdsConf = $_config = include("{$conf['path']}/buffer.php");;
        if (defined('_SYSTEM')) $_rdsConf = $_rdsConf[_SYSTEM];
        self::$_Redis = new Redis($_rdsConf);

        $tryCount = 0;
        tryGet:

        //没有强制从文件加载
        if (!_CLI and !defined('_CONFIG_LOAD')) {
            self::$_CONFIG_ = self::$_Redis->get(self::$_token . '_CONFIG_');
            if (!empty(self::$_CONFIG_)) {
                self::$_CONFIG_ = unserialize(self::$_CONFIG_);
                if (!empty(self::$_CONFIG_)) return;
            }
        }

        if (!_DEBUG and !_CLI and defined('_RPC') and _RPC and _RPC['ip'] !== getenv('SERVER_ADDR')) {
            /**
             * 若在子服务器里能进入到这里，说明redis中没有数据，
             * 则向主服务器发起一个请求，此请求仅仅是唤起主服务器重新初始化config
             * 并且主服务器返回的是`success`，如果返回的不是这个，就是出错了。
             * 然后，再次goto tryGet;从redis中读取config
             */
            $get = RPC::get('/debug/config',false);
            if ($get === 'success') {
                if ($tryCount > 1) throw new \Exception("系统出错." . $get, 505);
                $tryCount++;
                goto tryGet;
            } else {
                throw new \Exception("系统出错." . var_export($get, true), 505);
            }
        }

        $config = [];
        $dir = new \DirectoryIterator($conf['path']);
        foreach ($dir as $f) {
            if ($f->isFile()) $config[] = $f->getPathname();
        }
        $config[] = __DIR__ . '/config/mime.ini';
        $config[] = __DIR__ . '/config/state.ini';
//        $config[] = __DIR__ . '/config/ua.ini';

        self::$_CONFIG_ = Array();
        self::$_CONFIG_[] = date('Y-m-d H:i:s');
        foreach ($config as $i => $file) {
            $_config = self::loadFile($file, $i);
            //查找子目录下相同文件，如果存在，则覆盖相关值
            if (isset($conf['folder'])) {
                $tmp = explode('/', $file);
                $tmp[count($tmp) - 1] = $conf['folder'] . '/' . $tmp[count($tmp) - 1];
                $tmp = implode('/', $tmp);
                if (is_readable($tmp)) {
                    $_config = array_replace_recursive($_config, self::loadFile($tmp, $i));
                }
            }
            if (!empty($_config)) self::$_CONFIG_ = array_merge(self::$_CONFIG_, $_config);
        }
        self::$_CONFIG_ = self::re_arr(self::$_CONFIG_);
        if (!_CLI) self::$_Redis->set(self::$_token . '_CONFIG_', serialize(self::$_CONFIG_));
    }

    public static function flush(int $lev = 0)
    {
        $rds = self::Redis();

        if ($lev === 0) {
            //清空config本身
            $rds->set(self::$_token . '_CONFIG_', null);

        } else {
            //清空整个redis表
            $rand = $rds->get('resourceRand');
            $rds->flush();
            $rds->set('resourceRand', $rand);
        }
    }

    public static function all(bool $showAll = false)
    {
        $rds = self::Redis();
        $config = $rds->keys('*');
        $db1Value = [];
        $v = ['NULL', 'STRING', 'SET', 'LIST', 'ZSET', 'HASH'];
        foreach ($config as $key) {
            if ($key === (self::$_token . '_CONFIG_')) continue;
            $type = $rds->type($key);
            if ($showAll) {
                switch ($type) {
                    case 1://STRING
                        $val = $rds->get($key);
                        break;
                    case 2://SET
                        $val = $rds->SINTER($key);
                        break;
                    case 3://LIST
                        $val = $rds->LLEN($key);
                        break;
                    case 4://ZSET
                        $val = $rds->ZINTERSTORE($key);
                        break;
                    case 5://HASH
                        $val = $rds->hGetAll($key);
                        break;
                    default:
                        $val = null;
                        break;
                }
            } else {
                $val = '';
            }
            $db1Value[$key] = ['type' => $v[$type], 'value' => $val];
        }
        return $db1Value;
    }


    /**
     * @return Redis
     */
    public static function Redis()
    {
        return self::$_Redis;
    }

    /**
     * @param string $file
     * @param string $byKey
     * @return array
     * @throws \Exception
     */
    public static function loadFile(string $file, $byKey = null): array
    {
        if (!is_readable($file)) {
//            throw new \Exception("配置文件{$fullName}不存在", 404);
            return [];
        };
        $info = pathinfo($file);

        if ($info['extension'] === 'php') {
            $_config = include($file);
            if (!is_array($_config)) $_config = [];

        } elseif ($info['extension'] === 'ini') {
            $_config = parse_ini_file($file, true);
            if (!is_array($_config)) $_config = [];

        } elseif ($info['extension'] === 'json') {
            $_config = file_get_contents($file);
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


    private static function re_key($value)
    {
        $value = preg_replace_callback('/\{(_[A-Z_]+)\}/', function ($matches) {
            $search = array('_TIME', '_DATE', '_NOW');
            $replace = array(date('H:i:s'), date('Ymd'), time());
            $re = str_ireplace($search, $replace, $matches[1]);
            if ($re !== $matches[1]) return $re;
            return constant($matches[1]);
        }, $value);

        if (substr($value, 0, 1) === '[' and substr($value, -1, 1) === ']') {
            $arr = json_decode($value, true);
            if (is_array($arr)) $value = $arr;
        } else if (is_numeric($value) and strlen($value) < 10) {
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
        if (empty($key)) return null;
        if ($key === ['*']) return self::$_CONFIG_;
        $conf = self::$_CONFIG_;
        foreach (explode('.', strtolower(implode('.', $key))) as $k) {
            if ($k === '' or $k === '*') return null;
            if (!isset($conf[$k])) return null;
            $conf = &$conf[$k];
        }
        return $conf;
    }

//
//    public static function set($key, $value)
//    {
//        self::$_CONFIG_[$key] = $value;
//    }
//

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

}