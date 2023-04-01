<?php
declare(strict_types=1);

namespace esp\core;

use Redis;
use DirectoryIterator;
use function esp\helper\root;

/**
 * Class Config
 * @package esp\core
 */
final class Configure
{
    public int $RedisDbIndex = 0;
    public string $driver;//驱动方式，供dispatcher中读取用于session的驱动方式
    private array $_CONFIG_ = [];

    public Redis $_Redis;
    public array $_redis_conf;

    const awakenURI = '/_esp_config_awaken_';
    const userAgent = 'espConfigAwaken';

    /**
     * @param array $conf
     */
    public function __construct(array $conf)
    {
        $conf += ['path' => '/common/config', 'type' => 'redis'];
        $conf['path'] = root($conf['path']);
        $this->driver = $conf['type'];
        if (!is_dir($conf['path'])) return;

        $fun = "load_{$conf['type']}";
        $this->{$fun}($conf);
    }

    /**
     * 子节点服务器请求config唤醒
     *
     * @param bool $json
     * @return bool|mixed|string|null
     */
    private function asyncRPC(bool $json)
    {
        /**
         * 若在子服务器里能进入到这里，说明redis中没有数据，
         * 则向主服务器发起一个请求，此请求仅仅是唤起主服务器重新初始化config
         * 并且主服务器返回的是`_UNIQUE_KEY`，如果返回的不是这个，就是出错了。
         * 然后，再次goto trySelf;从redis中读取config
         * 这里请求$awakenURI，在主服务器中实际上会被当前文件也就是当前构造函数中最后一行拦截并返回success
         * 需要在nginx中建立一个绑定了$host['host']和port的虚拟机，指向任意虚拟机即可
         */
        if (!defined('_RPC')) return null;
        if (is_array(_RPC)) {
            $host = _RPC;
        } else {
            $host = ['host' => 'rpc.esp', 'port' => 800, 'ip' => _RPC];
        }
        $url = sprintf('%s://%s/%s', 'http', $host['host'], ltrim(self::awakenURI, '/'));
        $_resolve = ["{$host['host']}:{$host['port']}:{$host['ip']}"];

        $cOption = [];
        $cOption[CURLOPT_URL] = $url;                               //接收页
        $cOption[CURLOPT_RESOLVE] = $_resolve;                      //host必须是array("example.com:80:127.0.0.1")格式
        $cOption[CURLOPT_HTTPGET] = true;                           //Get方式
        $cOption[CURLOPT_USERAGENT] = self::userAgent;              //UA信息
        $cOption[CURLOPT_HTTP_VERSION] = CURL_HTTP_VERSION_NONE;    //自动选择http版本
        $cOption[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;            //指定使用IPv4解析
        $cOption[CURLOPT_CONNECTTIMEOUT] = 10;                      //在发起连接前等待的时间，如果设置为0，则无限等待
        $cOption[CURLOPT_TIMEOUT] = 5;                              //允许执行的最长秒数，若用毫秒级，用TIMEOUT_MS
        $cOption[CURLOPT_FRESH_CONNECT] = true;                     //强制新连接，不用缓存中的

        $cURL = curl_init();
        curl_setopt_array($cURL, $cOption);
        $html = curl_exec($cURL);
        curl_close($cURL);

        if (!$json) return $html;
        return json_decode($html, true);
    }

    /**
     * @param array $conf
     * @param int $db
     * @return Redis
     */
    private function connectRedis(array $conf, int $db): Redis
    {
        $conf += ['host' => '/tmp/redis.sock', 'port' => 0, 'db' => 1];
        $redis = new \Redis();
        $tryCont = 0;
        try {
            tryCont:
            if ($conf['host'][0] === '/') {
                if (!file_exists($conf['host'])) {
                    esp_error('Configure', "Redis服务器【{$conf['host']}】通道文件不存在，请检查redis.conf中设置。");
                }
                if (!$redis->connect($conf['host'])) {
                    esp_error('Configure', "Redis服务器【{$conf['host']}】无法连接。");
                }
            } else if (!$redis->connect($conf['host'], intval($conf['port']))) {
                esp_error('Configure', "Redis服务器【{$conf['host']}:{$conf['port']}】无法连接。");
            }
        } catch (\Error $e) {

            if ($tryCont++ > 2) {
                $err = base64_encode(print_r($conf, true));
                esp_error('Configure', $e->getMessage(), $err);
            }
            usleep(1000);
            goto tryCont;
        }
        if (isset($conf['timeout'])) {
            $redis->setOption(\Redis::OPT_READ_TIMEOUT, strval($conf['timeout']));
        }
        if (!isset($conf['nophp'])) {
            $redis->setOption(\Redis::OPT_SERIALIZER, strval(\Redis::SERIALIZER_PHP));//序列化方式
        }
        if (!$redis->select($db)) {
            esp_error('Configure', "Redis选择库【{$db}】失败。");
        }

        $this->_redis_conf = $conf;
        return $redis;
    }

    /**
     * @param array $conf
     */
    private function load_redis(array $conf)
    {
        $isMaster = is_file(_RUNTIME . '/master.lock');

        if (isset($conf['database'])) {
            if (!is_readable($conf['database'])) esp_error('Configure', "指定的database配置文件不存在或不可读");
            $bFile = $conf['database'];
        } else {
            $bFile = "{$conf['path']}/database.ini";
            if (!is_readable($bFile)) $bFile = "{$conf['path']}/database.json";
            if (!is_readable($bFile)) $bFile = "{$conf['path']}/database.php";
            if (!is_readable($bFile)) esp_error('Configure', "database配置文件只能是[.ini/.json/.php]格式，且只能置于{$conf['path']}目录");
        }

        $dbConf = $this->loadFile($bFile, 'database');
        if (empty($dbConf)) esp_error('Configure', '读取database失败，配置文件可能是空文件');

        if (isset($conf['folder'])) {
            $bFile = str_replace('/database.', "/{$conf['folder']}/database.", $bFile);
            if (is_readable($bFile)) {
                $siteConf = $this->loadFile($bFile, 'database');
                $dbConf = array_replace_recursive($dbConf, $siteConf);
            }
        }
        $rdsConf = $dbConf['database']['redis'] ?? [];
        if (is_array($rdsConf['db'])) $rdsConf['db'] = ($rdsConf['db']['config'] ?? 1);
        $this->RedisDbIndex = intval($rdsConf['db']);
        $this->_Redis = $this->connectRedis($rdsConf, $this->RedisDbIndex);

        //没有强制从文件加载
        if (!_CLI and (!defined('_CONFIG_LOAD') or !_CONFIG_LOAD) and !isset($_GET['_config_load'])) {
            $get = $this->_Redis->get(_UNIQUE_KEY . '_CONFIG_');
            if (!empty($get)) {
                if (is_string($get)) $get = json_decode($get, true) ?: [];
                $this->_CONFIG_ = $get;
                goto end;
            }
        }

        if (!_DEBUG and !_CLI and !$isMaster and defined('_RPC')) {

            $tryCount = 0;
            tryReadRedis:
            /**
             * 先读redis，若读不到，再进行后面的，这个虽然在前面也有读取，但是，若在从服务器，且也符合强制从文件加载时，上面的是不会执行的
             * 所在在这里要先读redis，也就是说，从服务器无论什么情况，都是先读redis，读不到时请求rpc往redis里写
             */
            $this->_CONFIG_ = $this->_Redis->get(_UNIQUE_KEY . '_CONFIG_') ?: [];
            if (!empty($this->_CONFIG_)) return;

            /**
             * 若在子服务器里能进入到这里，说明redis中没有数据，
             * 则向主服务器发起一个请求，此请求仅仅是唤起主服务器重新初始化config
             * 并且主服务器返回的是`_UNIQUE_KEY`，如果返回的不是这个，就是出错了。
             * 然后，再次goto trySelf;从redis中读取config
             * 这里请求$awakenURI，在主服务器中实际上会被当前文件也就是当前构造函数中最后一行拦截并返回success
             */
            $get = $this->asyncRPC(false);
            if ($get !== _UNIQUE_KEY) {
                if ($tryCount++ > 1) esp_error('Configure', "多次请求RPC获取到数据不合法", "期望值(" . _UNIQUE_KEY . ")，实际获取:{$get}");
            }

            goto tryReadRedis;
        }
        $this->mergeConfig($conf);
        if (!_CLI) $this->_Redis->set(_UNIQUE_KEY . '_CONFIG_', $this->_CONFIG_);

        end:
        //负载从服务器唤醒，直接退出
        if (_VIRTUAL === 'rpc' && _URI === self::awakenURI) exit(_UNIQUE_KEY);

        if (_CLI and substr(_URI, 0, 13) === '/_redis/flush') {
            $flush = $this->flush(intval(substr(_URI, 14)), '');
            echo "redis 缓存清理成功\n";
            print_r($flush);
            exit();
        }


    }


    private function load_file(array $conf)
    {
        $cnfFile = _RUNTIME . "/" . _UNIQUE_KEY . "_CONFIG_.json";
        $isMaster = is_file(_RUNTIME . '/master.lock');

        //没有强制从文件加载
        if (!_CLI and (!defined('_CONFIG_LOAD') or !_CONFIG_LOAD) and !isset($_GET['_config_load'])
            and is_readable($cnfFile)) {
            $json = file_get_contents($cnfFile);
            $this->_CONFIG_ = json_decode($json, true) ?: [];
            if (!empty($this->_CONFIG_)) goto end;
        }

        if (!_DEBUG and !_CLI and !$isMaster and defined('_RPC')) {
            /**
             * 若在子服务器里能进入到这里，说明redis中没有数据，
             * 则向主服务器发起请求，这里请求$awakenURI，在主服务器拦截并返回config
             */
            $get = $this->asyncRPC(true);
            if (!empty($get)) {
                $this->_CONFIG_ = $get;
                return;
            }
        }

        $this->mergeConfig($conf);

        if (!_CLI) @file_put_contents($cnfFile, json_encode($this->_CONFIG_, 448));

        end:
        //负载从服务器唤醒，直接退出
        if (_VIRTUAL === 'rpc' && _URI === self::awakenURI) {
            echo json_encode($this->_CONFIG_, 320);
            exit;
        }
    }

    private function mergeConfig(array $conf)
    {
        $config = [];
        $dir = new DirectoryIterator($conf['path']);
        foreach ($dir as $f) {
            if ($f->isFile()) {
                $fn = $f->getFilename();
                $config[] = ['file' => $f->getPathname(), 'name' => $fn];
            }
        }
        if (isset($conf['extra'])) {
            if (!is_array($conf['extra'])) $conf['extra'] = [$conf['extra']];
            foreach ($conf['extra'] as $ext) {
                if ($ext[0] !== '/') $ext = "{$conf['path']}/{$ext}";
                $dir = new DirectoryIterator($ext);
                foreach ($dir as $f) {
                    if ($f->isFile()) {
                        $fn = $f->getFilename();
                        $config[] = ['file' => $f->getPathname(), 'name' => $fn];
                    }
                }
            }
        }

        $this->_CONFIG_ = array();
        $this->_CONFIG_['_lastLoad'] = date('Y-m-d H:i:s');
        foreach ($config as $fn => $cf) {
            $_config = $this->loadFile($cf['file'], $fn);
            //查找子目录下同名文件，如果存在，则覆盖相关值
            if ($conf['folder'] ?? '') {
                if ($conf['folder'][0] === '/') {
                    $tmp = "{$conf['folder']}/{$cf['name']}";
                } else {
                    $tmp = "{$conf['path']}/{$conf['folder']}/{$cf['name']}";
                }
                if (is_readable($tmp)) {
                    $_config = array_replace_recursive($_config, $this->loadFile($tmp, $fn));
                }
            }
            if (!empty($_config)) {
                $this->_CONFIG_ = array_merge($this->_CONFIG_, $_config);
            }
        }

        if (isset($conf['merge']) and !empty($conf['merge'])) {
            $this->_CONFIG_ = array_merge($this->_CONFIG_, $conf['merge']);
        }

        if (isset($conf['replace']) and !empty($conf['replace'])) {
            $this->_CONFIG_ = array_replace_recursive($this->_CONFIG_, $conf['replace']);
        }

        $this->_CONFIG_ = $this->re_arr($this->_CONFIG_);
    }

    /**
     * 重新连接redis，这一般发生在CLI环境中，长时间过行，有可能redis会断线
     */
    public function reConnectRedis()
    {
        $rdsConf = $this->get('database.redis');
        if (is_array($rdsConf['db'])) $rdsConf['db'] = ($rdsConf['db']['config'] ?? 1);
        $this->RedisDbIndex = intval($rdsConf['db']);
        $this->_Redis = $this->connectRedis($rdsConf, $this->RedisDbIndex);
    }

    /**
     * 切换库，不传dbID时为切换回config所在的库
     * @param int $dbID
     * @return bool
     */
    public function select(int $dbID = -1)
    {
        if ($dbID < 0) return $this->_Redis->select($this->RedisDbIndex);
        return $this->_Redis->select($dbID);
    }

    public function flush(int $lev = 1, string $safe = ''): array
    {
        $value = [];
        if (!$lev) $lev = 1;
        if ($lev & 1) $value[1] = $this->_Redis->del(_UNIQUE_KEY . '_CONFIG_');
        if ($lev & 2) $value[2] = $this->_Redis->del(_UNIQUE_KEY . '_MYSQL_CACHE_');
        if ($lev & 4) $value[4] = $this->_Redis->del(_UNIQUE_KEY . '_RESOURCE_RAND_');

        if ($lev & 256) {            //清空整个redis表，保留_RESOURCE_RAND_
            $rand = $this->_Redis->get(_UNIQUE_KEY . '_RESOURCE_RAND_');
            $value[256] = $this->_Redis->flushDB();
            $this->_Redis->set(_UNIQUE_KEY . '_RESOURCE_RAND_', $rand);
        }

        if ($lev & 1024) {            //清空整个redis
            if ($safe === 'flushAll') {
                $value[1024] = $this->_Redis->flushAll();
            } else {
                echo "请在第2个参数输入flushAll以确认执行的是此命令\n";
            }
        }
        return $value;
    }


    public function all(bool $showAll = false): array
    {
        $config = $this->_Redis->keys('*');
        $db1Value = [];
        $v = ['NULL', 'STRING', 'SET', 'LIST', 'ZSET', 'HASH'];
        foreach ($config as $key) {
            if ($key === (_UNIQUE_KEY . '_CONFIG_')) {
                continue;
            }
            $type = $this->_Redis->type($key);
            if ($showAll) {
                switch ($type) {
                    case 1://STRING
                        $val = $this->_Redis->get($key);
                        break;
                    case 2://SET
                        $val = $this->_Redis->SINTER($key);
                        break;
                    case 3://LIST
                        $val = $this->_Redis->LLEN($key);
                        break;
                    case 5://HASH
                        $val = $this->_Redis->hGetAll($key);
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
     * 将一级键名中带.号的，转换为数组，如将：abc.xyz=123转换为abc[xyz]=123
     * 将值符合[a,b,c]的转换为数组，不支持json格式
     * 最大支持6级，即5个点
     * @param array $array
     * @return array
     */
    private function expIniArray(array $array): array
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) $array[$key] = $value = $this->expIniArray($value);
            else if (is_string($value)) {
                $array[$key] = $value = trim($value);
                if (strpos($value, ',') > 0 and preg_match('/^\[(.+)\]$/', $value, $mth)) {
                    $array[$key] = $value = explode(',', $mth[1]);
                }
            }

            if (!is_string($key) or strpos($key, '.') === false) continue;
            $tmp = explode('.', $key, 6);
            switch (count($tmp)) {
                case 6:
                    $array[$tmp[0]][$tmp[1]][$tmp[2]][$tmp[3]][$tmp[4]][$tmp[5]] = $value;
                    break;
                case 5:
                    $array[$tmp[0]][$tmp[1]][$tmp[2]][$tmp[3]][$tmp[4]] = $value;
                    break;
                case 4:
                    $array[$tmp[0]][$tmp[1]][$tmp[2]][$tmp[3]] = $value;
                    break;
                case 3:
                    $array[$tmp[0]][$tmp[1]][$tmp[2]] = $value;
                    break;
                default:
                    $array[$tmp[0]][$tmp[1]] = $value;
            }
            unset($array[$key]);
        }

        return $array;
    }

    /**
     * @param string $file
     * @param string|int|null $byKey
     * string:以此为主键返回数组
     * int或数字，则取文件名作为主键
     * null：返回内容本身
     * @return array
     */
    public function loadFile(string $file, $byKey = null): array
    {
        if (!is_readable($file)) return [];
        $info = pathinfo($file);
        switch ($info['extension']) {
            case 'ini':
                $_config = parse_ini_file($file, true);
                if (!is_array($_config) or empty($_config)) return [];
                $_config = $this->expIniArray($_config);
                break;
            case 'json':
                $_config = file_get_contents($file);
                $_config = json_decode($_config, true);
                break;
            case 'php':
                $_config = include($file);
                break;
            case 'yaml':
                $_config = yaml_parse_file($file);
                break;
            default:
                return [];
        }
        if (!is_array($_config) or empty($_config)) $_config = [];

        if (isset($_config['include'])) {
            $include = $_config['include'];
            unset($_config['include']);
            foreach ($include as $key => $fil) {
                if (is_array($fil)) {
                    $_config[$key] = array();
                    foreach ($fil as $l => $f) {
                        $_inc = $this->loadFile(root($f), $l);
                        if (!empty($_inc)) $_config[$key] = $_inc + $_config[$key];
                    }
                } else {
                    $_inc = $this->loadFile(root($fil), $key);
                    if (!empty($_inc)) $_config = $_inc + $_config;
                }
            }
        }

        if (is_null($byKey)) return $_config;
        if (is_int($byKey) or is_numeric($byKey)) {
            $byKey = $info['filename'];
        }

        //empty($_config) ? [] :
        return [$byKey => $_config];
    }

    /**
     * 加载在format时没载入的，不经过缓存
     * @param string $file
     * @param string|null $key
     * @param null $auto
     * @return array|mixed|null
     */
    public function load(string $file, string $key = null, $auto = null)
    {
        $conf = parse_ini_file(root($file), true);
        $conf = $this->re_arr($conf);
        if (is_null($key)) {
            return $conf;
        }

        $key = preg_replace('/[\.\,\/]+/', '.', strtolower($key));
        if (strrpos($key, '.')) {
            $keys = explode('.', trim($key, '.'));
            $_config = $conf;
            foreach ($keys as $k) {
                $_config = $_config[$k] ?? null;
                if (is_null($_config)) {
                    return $auto;
                }
            }
            return $_config;
        }
        return $conf[$key] ?? $auto;
    }


    private function re_key($value)
    {
        $value = preg_replace_callback('/\{(_[A-Z_]+)\}/', function ($matches) {
            $search = array('_TIME', '_DATE', '_NOW', '_ROOT', '_RUNTIME', '_DOMAIN', '_HOST');
            $replace = array(date('H:i:s'), date('Ymd'), time(), _ROOT, _RUNTIME, _DOMAIN, _HOST);
            $re = str_ireplace($search, $replace, $matches[1]);
            if ($re !== $matches[1]) {
                return $re;
            }
            return defined($matches[1]) ? constant($matches[1]) : $matches[1];
        }, strval($value));

        if (substr($value, 0, 1) === '[' and substr($value, -1, 1) === ']') {
            $arr = json_decode($value, true);
            if (is_array($arr)) $value = $arr;

        } elseif (is_numeric($value) and strlen($value) < 10) {
            if (strpos($value, '.') > 0) {
                $value = floatval($value);
            } else {
                $value = intval($value);
            }
        }
        return $value;
    }

    private function re_arr(array $array): array
    {
        $val = array();
        foreach ($array as $k => $arr) {
            if (is_array($arr)) {
                $val[strtolower(strval($k))] = $this->re_arr($arr);
            } else {
                $val[strtolower(strval($k))] = $this->re_key($arr);
            }
        }
        return $val;
    }

    /**
     * 读取config，可以用get('key1.key2')的方式读取多维数组值
     * 第2个参数可以指定返回数据格式，默认string
     * 若第2个是整型，返回的也是整型，
     *
     * @param mixed ...$key
     * @return array|mixed|null
     */
    public function get(...$key)
    {
        if (empty($key)) return null;
//        if ($key === ['*']) return $this->_CONFIG_;
        $conf = $this->_CONFIG_;
        foreach (explode('.', strtolower($key[0])) as $k) {
            if ($k === '' or $k === '*' or !isset($conf[$k])) return null;
            $conf = &$conf[$k];
        }
        if (!isset($key[1])) return $conf;
        if (is_int($key[1])) return intval($conf);
        if (is_float($key[1])) return floatval($conf);
        if (is_bool($key[1])) return boolval($conf);
        if (is_array($key[1]) and is_string($conf)) return json_decode($conf, true);
        return $conf;
    }

    public function allConfig()
    {
        return $this->_CONFIG_;
    }


    /**
     * var_export
     *
     * @return string
     */
    public static function __set_state(array $data)
    {
        return __CLASS__;
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
        return [__CLASS__, $this->_CONFIG_['_lastLoad'] ?? '', array_keys($this->_CONFIG_)];
    }


}
