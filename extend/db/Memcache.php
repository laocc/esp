<?php
namespace esp\extend\db;

use esp\core\Config;

/**
 * Class Memcache
 * @package esp\extend\db
 *
 * http://pecl.php.net/package/memcache
 * 函数表在PHP手册中可找到
 */
class Memcache implements ext\Nosql
{
    private $conn;
    private $tab = 'Temp';
    private $host = '';
    const _TRY = 5;//出错时，尝试次数

    public function __construct($conf = null, $table = null)
    {
        if (!class_exists('\memcache')) error('无法创建Memcache');
        if (is_string($conf)) list($conf, $table) = [null, $conf];
        if (!is_array($conf)) $conf = [];
        $conf += Config::get('memcache');

        $this->conn = new \Memcache();
        $this->host = "{$conf['host']}:{$conf['port']}";
        $conn = (isset($conf['pConnect']) and $conf['pConnect']) ? 'pconnect' : 'connect';
        if (!$this->conn->{$conn}($conf['host'], $conf['port'], $conf['timeout'])) {
            error('Memcache 连接失败');
        }
        $this->tab = ($table ?: $conf['table']) ?: 'Temp';
    }

    /**
     * 指定表
     * @param $table
     * @return $this
     */
    public function table($table)
    {
        if (empty($table)) error('DB_MemCache ERROR: Table不可以是空值');
        $this->tab = $table;
        return $this;
    }

    private function check_table()
    {
        if (empty($this->tab)) error('DB_MemCache ERROR: Table未定义');
    }

    /**
     * 读取【指定表】的所有行键，由于memcached有时读不出getExtendedStats，所以需要允许重试几次
     * @param $table
     * @return array
     */
    public function keys($try = self::_TRY)
    {
        $this->check_table();

        $all_items = $this->conn->getExtendedStats('items');
        if (!$all_items and $try > 0) {
            usleep(100);//没读取来，重试一次，等100微秒
            return $this->keys($try - 1);
        }

        $keys = [];
        foreach ($all_items as $host => &$client) {
            if ($host === $this->host and isset($client['items'])) {
                foreach ($client['items'] as $area => &$array) {
                    $allKeys = $this->conn->getExtendedStats('cachedump', $area, 0);
                    foreach ($allKeys as $i => &$value) {
                        foreach ($value as $key => &$val) {
                            list($tab, $k) = explode('.', $key . '.');
                            if ($tab === $this->tab) $keys[] = trim($k);
                        }
                    }
                }
            }
        }
        return $keys;
    }

    /**
     * 存入【指定表】【行键】【行值】
     * @param $table
     * @param $key
     * @param $array
     * @param int $ttl 生存期
     * @return bool
     */
    public function set($key, $array, $ttl = 0)
    {
        $this->check_table();
        if (is_array($key)) {
            $ttl = intval($array);
            $cnt = 1;
            foreach ($key as $k => &$v) {
                $cnt *= $this->set($k, serialize($v), $ttl) ? 1 : 0;
            }
            return $cnt;
        }
        return $this->conn->set($this->tab . '.' . $key, serialize($array), MEMCACHE_COMPRESSED, $ttl);
    }

    /**
     * 更新值
     * @param $key
     * @param array $value
     * @param int $ttl
     * @return bool
     */
    public function update($key, array $value, $ttl = 0)
    {
        $this->check_table();
        $val = $this->get($key);
        $array = array_merge($val, $value);//合并数组，用新数据替换旧数据
        return $this->conn->replace($this->tab . '.' . $key, serialize($array), MEMCACHE_COMPRESSED, $ttl);
    }

    /**
     * 读取【指定表】的【行键】数据
     * @param $key
     * @return array
     */
    public function get($key = null, $try = self::_TRY)
    {
        $this->check_table();
        if ($key === null or $key === '*') return $this->all();
        $val = $this->conn->get($this->tab . '.' . $key);
        pre($this->tab . '.' . $key);
        if ($val === false and $try > 0) {
            return $this->get($key, 0);//只尝试一次
        }
        return unserialize($val);
    }

    /**
     * 读取【指定表】【指定键值】的记录
     * @param $table
     * @param $recode
     * @return array
     */
    public function all($keys = null, $whereKey = null, $whereType = '=', $whereValue = null)
    {
        $this->check_table();

        if (is_string($keys)) {//未输入Keys，各参数往前提一格
            list($whereKey, $whereType, $whereValue, $keys) = [$keys, $whereKey, $whereType, $this->keys()];
        }

        $data = [];
        $value = [];
        $keys = $keys ?: $this->keys();

        foreach ($keys as $i => &$key) {
            if (!!$key) $data[$key] = $this->get($key);
        }
        if ($whereKey === null) return $data;

        foreach ($data as $k => &$v) {
            if (!isset($v[$whereKey])) continue;

            if ($whereType === '=' and $v[$whereKey] == $whereValue) {
                $value[$k] = $v;
            } elseif ($whereType === 'in' and in_array($v[$whereKey], $whereValue)) {
                $value[$k] = $v;
            } elseif ($whereType === 'out' and !in_array($v[$whereKey], $whereValue)) {
                $value[$k] = $v;
            } elseif ($whereType === '>' and $v[$whereKey] > $whereValue) {
                $value[$k] = $v;
            } elseif ($whereType === '<' and $v[$whereKey] < $whereValue) {
                $value[$k] = $v;
            } elseif ($whereType === '>=' and $v[$whereKey] >= $whereValue) {
                $value[$k] = $v;
            } elseif ($whereType === '<=' and $v[$whereKey] <= $whereValue) {
                $value[$k] = $v;
            } elseif ($whereType === '!=' and $v[$whereKey] != $whereValue) {
                $value[$k] = $v;
            }
        }
        return $value;
    }


    /**
     * 清空所有内存，慎用
     */
    public function flush()
    {
        $this->conn->flush();
    }


    /**
     * 删除key或清空表
     * @param $key
     * @return bool
     */
    public function del($key = null)
    {
        $this->check_table();

        $timeout = 0;//指定多久后删除
        if ($key === null) {
            $recode = $this->keys();
            $i = 1;
            foreach ($recode as &$key) {
                $i *= $this->conn->delete($this->tab . '.' . $key, 0) ? 1 : 0;
            }
            return $i === 1;
        } else {
            return $this->conn->delete($this->tab . '.' . $key, $timeout);
        }
    }


    /**
     * 计数器，只可是整型，
     * >0   加
     * <0   减
     * =0   获取值
     * @param string $TabKey 表名.键名，但这儿的键名要是预先定好义的
     * @param int $incrby 可以是正数、负数，或0，=0时为读取值
     * @return bool
     */
    public function add($TabKey = 'count', $incrby = 1)
    {
        $this->check_table();

        if ($incrby >= 0) {
            return $this->conn->increment($this->tab . '.' . $TabKey, $incrby);
        } else {
            return $this->conn->decrement($this->tab . '.' . $TabKey, 0 - $incrby);
        }
    }

    /**
     * 计算某表行数
     * @param string $TabKey
     * @return bool
     */
    public function len($TabKey = 'count')
    {
        $this->check_table();

        return $this->add($this->tab . '.' . $TabKey, 0);
    }

    /**
     *  关闭
     */
    public function close()
    {
        $this->conn->close();
    }


    public function ping()
    {
        return is_object($this->conn);
    }

}