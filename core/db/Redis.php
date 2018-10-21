<?php

namespace esp\core\db;

use esp\core\db\ext\KeyValue;
use esp\core\db\ext\RedisHash;
use esp\core\db\ext\RedisList;

/**
 * Class Redis
 * @package db
 * http://doc.redisfans.com/
 */
final class Redis implements KeyValue
{
    private $redis;
    private $table = null;//哈希表
    private $ttl = 0;
    public $index = 0;//当前库ID
    public $key;//用以区分同一服务器上的不同站点

    /**
     * Redis constructor.
     * @param null $conf
     * @param int $db
     * @throws \Exception
     */
    public function __construct(array $conf = null, int $db = null)
    {
        if (is_int($conf)) {
            list($conf, $db) = [['host' => '/tmp/redis.sock'], $conf];
        }
        if (!is_array($conf) or !isset($conf['host'])) {
            throw new \Exception('Redis配置信息错误');
        }
        if (is_null($db)) $db = intval($conf['db'] ?? 1);
        if (!isset($conf['_from'])) {
            if (!($db > 0 and $db <= 16)) {
                throw new \Exception('Redis库ID选择错误，0库为系统库不可直接调用，暂不支持大于16的库');
            }
        }

        $this->redis = new \Redis();
        if (isset($conf['pconnect']) and $conf['pconnect']) {
            if (!isset($conf['port']) or intval($conf['port']) === 0) {
                if (!$this->redis->pconnect($conf['host'])) {
                    throw new \Exception("Redis服务器【{$conf['host']}】无法连接。");
                }
            } else if (!$this->redis->pconnect($conf['host'], $conf['port'])) {
                throw new \Exception("Redis服务器【{$conf['host']}:{$conf['port']}】无法连接。");
            }
        } else {
            if (!isset($conf['port']) or intval($conf['port']) === 0) {
                if (!$this->redis->connect($conf['host'])) {
                    throw new \Exception("Redis服务器【{$conf['host']}】无法连接。");
                }
            } else if (!$this->redis->connect($conf['host'], $conf['port'])) {
                throw new \Exception("Redis服务器【{$conf['host']}:{$conf['port']}】无法连接。");
            }
        }

        //用密码登录
        if (isset($conf['password']) and !$this->redis->auth($conf['password'])) {
            throw new \Exception("Redis密码错误，无法连接服务器。");
        }

        if (isset($conf['timeout'])) {
            $this->redis->setOption(\Redis::OPT_READ_TIMEOUT, intval($conf['timeout']));
        }

        if (!$this->redis->select((int)$db)) {
            throw new \Exception("Redis选择库【{$db}】失败。");
        }
        if (isset($conf['flush']) and $conf['flush']) $this->redis->flushDB();

        if (isset($conf['ttl'])) $this->ttl = (int)$conf['ttl'];

        $this->index = $db;
        $this->key = $conf['key'] ?? '';

        $this->table = null;
    }


    /**
     * 创建一个LIST集合
     * @param string|null $tabName
     * @return RedisList
     */
    public function list(string $tabName)
    {
        static $list = Array();
        if (!isset($list[$tabName])) {
            $list[$tabName] = new RedisList($this->redis, $tabName);
        }
        return $list[$tabName];
    }

    /**
     * 创建一个hash表
     * @param string $tabName
     * @return RedisHash
     */
    public function hash(string $tabName)
    {
        static $list = Array();
        if (!isset($list[$tabName])) {
            $list[$tabName] = new RedisHash($this->redis, $tabName);
        }
        return $list[$tabName];
    }


    /**
     * 设定哈希表
     * @param $tabName
     * @return $this
     */
    public function table(string $tabName = null)
    {
        $this->table = $tabName;
        return $this;
    }

    /**
     * 保存
     * @param $key
     * @param $value
     * @param int $ttl ：=null以config为准，=0不过期，>0指定时间
     * @return bool
     */
    public function set(string $key, $value, int $ttl = null)
    {
        if (!!$this->table) {//指定哈希表
            return $this->redis->hSet($this->table, $key, serialize($value));
        }

        //普通值
        $ttl = $ttl ?: $this->ttl;
        if ($ttl) {
            return $this->redis->setex($key, $ttl, serialize($value));
        } else {
            return $this->redis->set($key, serialize($value));
        }
    }

    //========================================有序集合=====================================

    /**
     * 添加有序集合
     */
    public function zAdd($value)
    {
        //创建一个有序集合记录的唯一键
        $timeKey = function ($i = 0) {
            $v = mt_rand(1000, 9999);
            list($s, $m) = explode('.', microtime(1));
            return intval((intval($s) - 1450000000) . str_pad(intval($i), 2, 0) . str_pad($m, 4, 0) . $v);
        };

        if (is_array($value)) {
            $nVal = Array();
            foreach ($value as $k => &$v) {
                $nVal[] = $timeKey($k);
                $nVal[] = serialize($v);
            }
            return call_user_func_array([$this->redis, 'zAdd'], array_merge([$this->table], $nVal));
        } else {
            return $this->redis->zAdd($this->table, $timeKey(), serialize($value));
        }
    }

    /**
     * @param int $count
     * @param string $order
     * @param bool $kill
     * @return mixed
     */
    public function zGet($count = 1, $order = 'asc', $kill = true)
    {
        if (is_bool($order)) {
            $kill = $order;
            $order = 'asc';
        }
        $count -= 1;

        if ($order == 'asc') {//顺序
            $val = $this->redis->zRange($this->table, 0, $count);
        } else {//倒序
            $val = $this->redis->zRevRange($this->table, 0 - $count, -1);
        }

        if (!!$kill) {
            if ($order === 'asc') {//按位置删除
                $this->redis->zRemRangeByRank($this->table, 0, $count);
            } else { //按值删除
                call_user_func_array([$this->redis, 'zRem'], array_merge([$this->table], $val));
            }

        }
        return unserialize($val);
    }


    //========================================有序集合 END===无序集合==================================


    //添加一个值到table
    public function sAdd($value)
    {
        if (is_array($value)) {
            foreach ($value as $k => &$val) {
                $val = serialize($val);
            }
            return $this->redis->sAdd($this->table, ...$value);
//            return call_user_func_array([$this->redis, 'sAdd'], array_merge([$this->table], $value));
        } else {
            return $this->redis->sAdd($this->table, serialize($value));
        }
    }

    /**
     * 从无序集中读取N个结果
     * @param int $count
     * @param bool|true $kill 是否读出来后就删除
     * @return array|string
     */
    public function sGet($count = 1, $kill = false)
    {
        if ($count === 1 and !!$kill) {//只要一条，且删除，则直接用spop
            $val = $this->redis->sPop($this->table);
            return ($val == false) ? [] : [unserialize($val)];
        }
        $value = $this->redis->sRandMember($this->table, $count);
        if (!!$kill) {
            call_user_func_array([$this->redis, 'sRem'], array_merge([$this->table], $value));
        }
        foreach ($value as $k => &$val) {
            $val = unserialize($val);
        }
        return $value;
    }

    /**
     * 清空
     * @return bool|mixed
     */
    public function flush(bool $flushAll = false)
    {
//        if ($flushAll) return $this->redis->flushAll();
        return $this->redis->flushDB();
    }


    /**
     * 发送订阅，需要在swoole\redis中接收
     * @param string $action
     * @param array $value
     * @return int
     */
    public function publish(string $action, array $value)
    {
        return $this->redis->publish($action, serialize($value));
    }


    //========================================无序集合 END=====================================

    /**
     * 设定过期时间
     * @param $key
     * @param $ttl
     */
    public function expire(string $key, int $ttl = null)
    {
        if ($ttl === null) {//只是查询过期时间
            return $this->redis->ttl($key);
        } elseif ($ttl === -1) {//设为永不过期
            return $this->redis->persist($key);
        } else {//设定过期时间
            return $this->redis->expire($key, $ttl);
        }
    }

    /**
     * 查询剩余有效期
     * 当 key 不存在时，返回 -2 。 当 key 存在但没有设置剩余生存时间时，返回 -1 。 否则，以秒为单位，返回 key 的剩余生存时间。
     * @param $key
     */
    public function ttl($key, $ttl = null)
    {
        return $this->expire($key, $ttl);
    }

    /**
     * 给某个键改名
     * 当 key 和 newkey 相同，或者 key 不存在时，返回一个错误。
     * 当 newkey 已经存在时， RENAME 命令将覆盖旧值。
     * @param $srcKey
     * @param $dstKey
     * 改名成功时提示 OK ，失败时候返回一个错误
     */
    public function rename($srcKey, $dstKey)
    {
        return $this->redis->rename($srcKey, $dstKey);
    }


    /**
     * 最近一次持久化保存的时间
     */
    public function lastSave($date = true)
    {
        $time = (int)$this->redis->lastSave();
        return $date ? date('Y-m-d H:i:s', $time) : $time;
    }

    /**
     * 持久化保存，RDB
     * @param bool|false $now ，是否立即保存，注意：此操作会中断其他用户，
     *                          建议由后台保存
     * @return bool
     */
    public function save($now = false)
    {
        return !!$now ? $this->redis->save() : $this->redis->bgsave();
    }


    /**
     * 返回当前所有键的数量
     */
    public function dbSize()
    {
        return $this->redis->dbSize();
    }

    public function close()
    {
        $this->redis->close();
    }

    /**
     * 返回服务器时间
     * @return mixed
     * @throws \Exception
     * 一个包含两个字符串的列表： 第一个字符串是当前时间(以 UNIX 时间戳格式表示)，而第二个字符串是当前这一秒钟已经逝去的微秒数。
     */
    public function time()
    {
        return $this->redis->time();

    }


    /**
     * 读取信息，但好象读不到
     * @param null $option
     * @return mixed
     * @throws \Exception
     */
    public function info($option = null)
    {
        return $this->redis->info($option);
    }


    /**
     * 查询某键是否存在
     * @param $key
     */
    public function exists($key)
    {
        return $this->redis->exists($key);

    }

    /**
     * 读取
     * @param $keys
     * @return bool|string|array
     */
    public function get(string $key = null, $try = 0)
    {
        if (!!$this->table) {//读哈希表,或无序集合
            if ($key === null or $key === '*') {
                $value = $this->redis->hGetAll($this->table);
                foreach ($value as &$val) {
                    $val = unserialize($val);
                }
                return $value;
            } else {//从哈希表读一个出来
                return unserialize($this->redis->hGet($this->table, $key));
            }
        } else {
            if ($key === null or $key === '*') {
                $RS = $this->redis->keys('*');
                $val = Array();
                foreach ($RS as $i => &$rs) {
                    $val[$rs] = ['ttl' => $this->redis->ttl($rs), 'value' => unserialize($this->redis->get($rs))];
                }
                return $val;
            } else {
                return unserialize($this->redis->get($key));
            }
        }
    }

    /**
     * 返回所有键，这个可以用通配符'user*'
     * @param string $keys
     * @return mixed
     * @throws \Exception
     */
    public function keys($keys = '*')
    {
        return $this->redis->keys($keys);
    }

    public function delete(...$keys)
    {
        return $this->redis->delete($keys);
    }


    /**
     * @param string[] ...$keys
     * @return bool|int
     */
    public function del(string ...$keys)
    {
        if (!!$this->table) {//指定哈希表
            if (is_array($keys)) {
                return $this->redis->hDel($this->table, ...$keys);
            } else {
                return $this->redis->hDel($this->table, $keys);
            }
        }

        if (empty($keys)) return false;
        if (is_array($keys)) {
            return $this->redis->del(...$keys);
        }
        return $this->redis->del($keys);
    }


    public function type($key)
    {
        return $this->redis->type($key);
    }

    /**
     * @return mixed
     * 正常情况会返回：+PONG
     */
    public function ping()
    {
        return $this->redis->ping() === '+PONG';
    }

    /**
     * 计数器
     * 如果值包含错误的类型，或字符串类型的值不能表示为数字，那么返回一个错误。
     * @param string $key
     * @param int $inc
     * @return bool|int
     */
    public function counter(string $key = 'count', int $inc = 1)
    {
        if (is_float($inc)) {
            if ($this->table) {
                if ($inc > 0) {
                    return $this->redis->hIncrByFloat($this->table, $key, $inc);
                } else {
                    return $this->redis->hIncrByFloat($this->table, $key, $inc);
                }
            } else {
                if ($inc > 0) {
                    return $this->redis->incrByFloat($key, $inc);
                } else {
                    return $this->redis->incrByFloat($key, $inc);
                }
            }
        } else {
            $inc = intval($inc);
            if ($this->table) {
                if ($inc > 0) {
                    return $this->redis->hIncrBy($this->table, $key, $inc);
                } else {
                    return $this->redis->hIncrBy($this->table, $key, $inc);
                }
            } else {
                if ($inc === 1) {
                    return $this->redis->incr($key);
                } elseif ($inc === -1) {
                    return $this->redis->decr($key);
                } elseif ($inc > 0) {
                    return $this->redis->incrBy($key, $inc);
                } elseif ($inc < 0) {
                    return $this->redis->decrBy($key, abs($inc));
                } else {
                    return false;
                }
            }
        }
    }


    /**
     * 标记事务开始
     * @return \Redis
     */
    public function trans_star()
    {
        return $this->redis->multi();
    }

    /**
     *
     * 取消事务，放弃执行事务块内的所有命令。
     * 如果正在使用 WATCH 命令监视某个(或某些) key，那么取消所有监视，等同于执行命令 UNWATCH 。
     */
    public function trans_back()
    {
        $this->redis->discard();
    }

    /**
     * 提交事务
     * 假如某个(或某些) key 正处于 WATCH 命令的监视之下，且事务块中有和这个(或这些) key 相关的命令，那么 EXEC 命令只在这个(或这些) key 没有被其他命令所改动的情况下执行并生效，否则该事务被打断(abort)。
     */
    public function trans_push()
    {
        return $this->redis->exec();
    }


    /**
     * 监视一个(或多个) key ，如果在事务执行之前这个(或这些) key 被其他命令所改动，那么事务将被打断。
     * @param $key
     */
    public function watch($key)
    {
        $this->redis->watch($key);
    }


    /**
     * 取消 WATCH 命令对所有 key 的监视。
     */
    public function unwatch()
    {
        $this->redis->unwatch();
    }


    public function lRem($key, $val, $count)
    {
        return $this->redis->lRem($key, $val, $count);
    }


    public function lRange($key, $start, $end)
    {
        return $this->redis->lRange($key, $start, $end);
    }


    public function lPush($key, ...$val)
    {
        return $this->redis->lPush($key, ...$val);
    }


    public function rPush($key, ...$val)
    {
        return $this->redis->rPush($key, ...$val);
    }


    public function hSet($key, $hasKey, $val)
    {
        return $this->redis->hSet($key, $hasKey, $val);
    }

    public function hGet($key, $hasKey)
    {
        return $this->redis->hGet($key, $hasKey);
    }

    public function hGetAll($key)
    {
        return $this->redis->hGetAll($key);
    }

    public function hDel($key, ...$hasKey)
    {
        return $this->redis->hDel($key, ...$hasKey);
    }

    public function rPop($key)
    {
        return $this->redis->rPop($key);
    }


}

