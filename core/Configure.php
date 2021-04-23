<?php
declare(strict_types=1);

namespace esp\core;

use esp\http\Http;
use esp\core\db\File;
use esp\core\db\Redis;
use esp\error\EspError;
use function esp\helper\root;

/**
 * Class Config
 * @package esp\core
 */
final class Configure
{
    private $_CONFIG_ = null;
    public $_Redis;
    public $_rpc;
    public $_token;

    /**
     * @param array $conf
     */
    public function __construct(array $conf)
    {
        $this->_token = md5(__FILE__);
        $conf += ['path' => '/common/config'];
        $conf['path'] = root($conf['path']);
        if (isset($conf['buffer'])) {
            $bFile = root($conf['buffer']);
            if (!is_readable($bFile)) throw new EspError("指定的buffer文件({$bFile})不存在");
        } else {
            $bFile = "{$conf['path']}/buffer.ini";
            if (!is_readable($bFile)) $bFile = "{$conf['path']}/buffer.json";
            if (!is_readable($bFile)) $bFile = "{$conf['path']}/buffer.php";
            if (!is_readable($bFile)) throw new EspError("未定义buffer文件");
        }

        $_bufferConf = $this->loadFile($bFile, 'buffer');
        $_bufferConf = $_bufferConf['buffer'] ?? [];

        if (_DEBUG and isset($_bufferConf['debug'])) {
            $_bufferConf = $_bufferConf['debug'];
        } else if (isset($conf['folder']) and isset($_bufferConf[$conf['folder']])) {
            $_bufferConf = $_bufferConf[$conf['folder']];
        }

        if (($_bufferConf['medium'] ?? 'redis') === 'file') {
            $this->_Redis = new File($_bufferConf);
        } else {
            $this->_Redis = new Redis($_bufferConf);
        }

        if (defined('_RPC')) {
            $this->_rpc = _RPC;
        } else {
            $this->_rpc = $_bufferConf['rpc'] ?? null;
        }
        if ($this->_rpc) {
            $this->_token = md5("{$this->_rpc['host']}{$this->_rpc['port']}{$this->_rpc['ip']}");
        }

        //没有强制从文件加载
        if (!_CLI
            and (!defined('_CONFIG_LOAD') or !_CONFIG_LOAD)
            and (!isset($_bufferConf['cache']) or $_bufferConf['cache'])
            and (!isset($conf['cache']) or $conf['cache'])
        ) {
            $this->_CONFIG_ = $this->_Redis->get($this->_token . '_CONFIG_');
            if (!empty($this->_CONFIG_)) return;
        }

        $awakenURI = '/_esp_config_awaken_';
        if (!_DEBUG and !_CLI and $this->_rpc and !is_file(_RUNTIME . '/master.lock')) {
            $tryCount = 0;
            tryReadRedis:
            /**
             * 先读redis，若读不到，再进行后面的，这个虽然在前面也有读取，但是，若在从服务器，且也符合强制从文件加载时，上面的是不会执行的
             * 所在在这里要先读redis，也就是说，从服务器无论什么情况，都是先读redis，读不到时请求rpc往redis里写
             */
            $this->_CONFIG_ = $this->_Redis->get($this->_token . '_CONFIG_');
            if (!empty($this->_CONFIG_)) return;

            /**
             * 若在子服务器里能进入到这里，说明redis中没有数据，
             * 则向主服务器发起一个请求，此请求仅仅是唤起主服务器重新初始化config
             * 并且主服务器返回的是`success`，如果返回的不是这个，就是出错了。
             * 然后，再次goto trySelf;从redis中读取config
             * 这里请求$awakenURI，在主服务器中实际上会被当前文件也就是当前构造函数中最后一行拦截并返回success
             */
            $rpcObj = new Http();
            $get = $rpcObj->rpc($this->_rpc)->encode('text')->get($awakenURI)->html();
            if ($tryCount++ > 1) throw new EspError("多次请求RPC获取到数据不合法，期望值({$this->_token})，实际获取:{$get}");

            goto tryReadRedis;
        }

        $config = [];
        $dir = new \DirectoryIterator(_ESP_ROOT . "/common/config");
        foreach ($dir as $f) {
            if ($f->isFile()) {
                $fn = $f->getFilename();
                $config[] = ['file' => $f->getPathname(), 'name' => $fn];
            }
        }
        $dir = new \DirectoryIterator($conf['path']);
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
                $dir = new \DirectoryIterator($ext);
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
            if (isset($conf['folder'])) {
                if ($conf['folder'][0] === '/') {
                    $tmp = "{$conf['folder']}/{$cf['name']}";
                } else {
                    $tmp = "{$conf['path']}/{$conf['folder']}/{$cf['name']}";
                }
                if (is_readable($tmp)) $_config = array_replace_recursive($_config, $this->loadFile($tmp, $fn));
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
        if (!_CLI and (!isset($conf['cache']) or $conf['cache'])) {
            $this->_Redis->set($this->_token . '_CONFIG_', $this->_CONFIG_);
        }

        //负载从服务器唤醒，直接退出
        if (_VIRTUAL === 'rpc' && _URI === $awakenURI) exit($this->_token);
    }

    public function flush(int $lev = 0): void
    {
        $rds = $this->Redis();

        if ($lev === 0) {
            //清空config本身
            $rds->set($this->_token . '_CONFIG_', null);

        } else {
            //清空整个redis表
            $rand = $rds->get('resourceRand');
            $rds->flush();
            $rds->set('resourceRand', $rand);
        }
    }

    public function all(bool $showAll = false): array
    {
        $rds = $this->Redis();
        $config = $rds->keys('*');
        $db1Value = [];
        $v = ['NULL', 'STRING', 'SET', 'LIST', 'ZSET', 'HASH'];
        foreach ($config as $key) {
            if ($key === ($this->_token . '_CONFIG_')) {
                continue;
            }
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
    public function Redis(): Redis
    {
        return $this->_Redis;
    }

    /**
     * @param string $file
     * @param string $byKey
     * @return array
     */
    public function loadFile(string $file, $byKey = null): array
    {
        if (!is_readable($file)) return [];
        $info = pathinfo($file);

        if ($info['extension'] === 'php') {
            $_config = include($file);
            if (!is_array($_config)) $_config = [];

        } elseif ($info['extension'] === 'ini') {
            $_config = parse_ini_file($file, true);
            if (!is_array($_config)) $_config = [];
            foreach ($_config as $k => $v) {
                if (!is_string($k)) continue;
                if (strpos($k, '.')) {
                    $tm = explode('.', $k, 2);
                    $_config[$tm[0]][$tm[1]] = $v;
                    unset($_config[$k]);
                }
            }

        } elseif ($info['extension'] === 'json') {
            $_config = file_get_contents($file);
            $_config = json_decode($_config, true);
            if (!is_array($_config)) $_config = [];

        } else {
            throw new EspError("未知的配置文件类型({$info['extension']})");
        }

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
        if (is_null($byKey) or is_int($byKey) or is_numeric($byKey)) {
            $byKey = $info['filename'];
        }

        return empty($_config) ? [] : [$byKey => $_config];
    }

    /**
     * 加载在format时没载入的，不经过缓存
     * @param string $file
     * @param string $key
     * @param null $auto
     * @return array|bool|mixed|null
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
                $_config = isset($_config[$k]) ? $_config[$k] : null;
                if (is_null($_config)) {
                    return $auto;
                }
            }
            return $_config;
        }
        return isset($conf[$key]) ? $conf[$key] : $auto;
    }


    private function re_key($value)
    {
        $value = preg_replace_callback('/\{(_[A-Z_]+)\}/', function ($matches) {
            $search = array('_TIME', '_DATE', '_NOW', '_ROOT', '_RUNTIME', '_DOMAIN', '_HOST');
            $replace = array(date('H:i:s'), date('Ymd'), _TIME, _ROOT, _RUNTIME, _DOMAIN, _HOST);
            $re = str_ireplace($search, $replace, $matches[1]);
            if ($re !== $matches[1]) {
                return $re;
            }
            return defined($matches[1]) ? constant($matches[1]) : $matches[1];
        }, $value);

        if (substr($value, 0, 1) === '[' and substr($value, -1, 1) === ']') {
            $arr = json_decode($value, true);
            if (is_array($arr)) {
                $value = $arr;
            }
        } elseif (is_numeric($value) and strlen($value) < 10) {
            $value = intval($value);
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
     * @param mixed ...$key
     * @return array|mixed|null
     */
    public function get(...$key)
    {
        if (empty($key)) return null;
        if ($key === ['*']) return $this->_CONFIG_;
        $conf = $this->_CONFIG_;
        foreach (explode('.', strtolower(implode('.', $key))) as $k) {
            if ($k === '' or $k === '*' or !isset($conf[$k])) return null;
            $conf = &$conf[$k];
        }
        return $conf;
    }


    /**
     * @param $type
     * @return string
     */
    private function mime(string $type): string
    {
        $mime = $this->get("mime.{$type}");
        if (is_array($mime)) $mime = json_encode($mime, 256 | 64);
        return $mime ?: 'text/html';
    }

    /**
     * @param $code
     * @return null|string
     */
    private function states(int $code): string
    {
        $state = $this->get("state.{$code}");
        if (is_array($state)) $state = json_encode($state, 256 | 64);
        return $state ?: 'Unexpected';
    }
}
