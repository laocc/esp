<?php

namespace esp\core\db;

use esp\core\db\ext\KeyValue;
use esp\core\ext\EspError;

class Memcache implements KeyValue
{
    private $conn;
    private $table = 'Temp';
    private $host;
    const _TRY = 5;//出错时，尝试次数

    public function __construct(array $conf = null, $table = null)
    {
        $conf += ['host' => '127.0.0.1', 'port' => 11211, 'table' => $this->table];
        $this->conn = new \Memcache();
        if (!@$this->conn->connect($conf['host'], $conf['port'])) {
            throw new EspError('Memcache 连接失败');
        }
        $this->table = ($table ?: $conf['table']) ?: $this->table;
        $this->host = "{$conf['host']}:{$conf['port']}";
    }

    /**
     * 指定表
     * @param $table
     * @return $this
     */
    public function table(string $table)
    {
        if (empty($table) or !is_string($table)) throw new EspError('DB_MemCache ERROR: Table 不可为空，只可为字符串');
        $this->table = $table;
        return $this;
    }

    /**
     * 读取【指定表】的所有行键，由于memcached有时读不出getExtendedStats，所以可能需要允许重试几次
     * @param $table
     * @return array
     */
    public function keys($try = self::_TRY)
    {
        $all_items = $this->conn->getExtendedStats('items');
        if (!$all_items and $try > 0) {
            usleep(100);//没读取来，重试一次，但要等100微秒
            return $this->keys($try - 1);
        }

        $keys = Array();
        foreach ($all_items as $host => &$client) {
            if ($host === $this->host and isset($client['items'])) {
                foreach ($client['items'] as $area => &$array) {
                    $allKeys = $this->conn->getExtendedStats('cachedump', $area, 0);
                    foreach ($allKeys as $i => &$value) {
                        foreach ($value as $key => &$val) {
                            list($tab, $k) = explode('.', $key . '.');
                            if ($tab === $this->table) $keys[] = trim($k);
                        }
                    }
                }
            }
        }
        return $keys;
    }

    /**
     * 读取【指定表】【指定键值】的记录
     * @param $table
     * @param $recode
     * @return array
     */
    public function all($keys = null, $whereKey = null, $whereType = '=', $whereValue = null)
    {
        if (is_string($keys)) {//未输入Keys，各参数往前提一格
            list($whereKey, $whereType, $whereValue, $keys) = [$keys, $whereKey, $whereType, $this->keys()];
        }

        $data = Array();
        $value = Array();
        $keys = $keys ?: $this->keys();

        foreach ($keys as $i => &$key) {
            if (!!$key) $data[$key] = $this->get($key);
        }
        if ($whereKey === null) return $data;

        foreach ($data as $k => &$v) {
            if (!isset($v[$whereKey])) continue;

            if ($whereType === '=' and $v[$whereKey] == $whereValue) {
                $value[$k] = $v;
            } else if ($whereType === 'in' and in_array($v[$whereKey], $whereValue)) {
                $value[$k] = $v;
            } else if ($whereType === 'out' and !in_array($v[$whereKey], $whereValue)) {
                $value[$k] = $v;
            } else if ($whereType === '>' and $v[$whereKey] > $whereValue) {
                $value[$k] = $v;
            } else if ($whereType === '<' and $v[$whereKey] < $whereValue) {
                $value[$k] = $v;
            } else if ($whereType === '>=' and $v[$whereKey] >= $whereValue) {
                $value[$k] = $v;
            } else if ($whereType === '<=' and $v[$whereKey] <= $whereValue) {
                $value[$k] = $v;
            } else if ($whereType === '!=' and $v[$whereKey] != $whereValue) {
                $value[$k] = $v;
            }
        }
        return $value;
    }


    /**
     * 读取【指定表】的【行键】数据
     * @param $key
     * @return array|bool
     */
    public function get(string $key = null)
    {
        if ($key === null or $key === '*') return $this->all();

        $val = $this->conn->get($this->table . '.' . $key);
        return ($val === false) ? false : unserialize($val);
    }

    /**
     * 存入【指定表】【行键】【行值】
     * @param $table
     * @param $key
     * @param $array
     * @param int $ttl 生存期
     * @return bool
     */
    public function set(string $key, $array, int $ttl = 0)
    {
        if (is_array($key)) {
            $ttl = intval($array);
            $cnt = 1;
            foreach ($key as $k => &$v) {
                $cnt *= $this->set($k, serialize($v), $ttl) ? 1 : 0;
            }
            return $cnt;
        }
        return $this->conn->set($this->table . '.' . $key, serialize($array), MEMCACHE_COMPRESSED, $ttl);
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
        $val = $this->get($key);
        $array = array_merge($val, $value);//合并数组，用新数据替换旧数据
        return $this->conn->replace($this->table . '.' . $key, serialize($array), MEMCACHE_COMPRESSED, $ttl);
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
    public function del(string ...$key)
    {
        $timeout = 0;//指定多久后删除
        if ($key === null) {
            $recode = $this->keys();
            $i = 1;
            foreach ($recode as &$key) {
                $i *= $this->conn->delete($this->table . '.' . $key, 0) ? 1 : 0;
            }
            return $i === 1;
        } else {
            return $this->conn->delete($this->table . '.' . $key, $timeout);
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
    public function counter(string $TabKey = 'count', int $incrby = 1)
    {
        if (!is_int($incrby)) throw new EspError('DB_MemCache ERROR: incrby只能是整型');

        if ($incrby >= 0) {
            return $this->conn->increment($this->table . '.' . $TabKey, $incrby);
        } else {
            return $this->conn->decrement($this->table . '.' . $TabKey, 0 - $incrby);
        }
    }

    /**
     * 计算某表行数
     * @param string $TabKey
     * @return bool
     */
    public function len($TabKey = 'count')
    {
        return $this->counter($this->table . '.' . $TabKey, 0);
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