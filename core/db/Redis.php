<?php
declare(strict_types=1);

namespace esp\core\db;

use esp\core\db\ext\KeyValue;
use esp\core\db\ext\RedisHash;
use esp\core\db\ext\RedisList;
use esp\error\Error;

/**
 * Class Redis
 * @package db
 * http://doc.redisfans.com/
 */
final class Redis implements KeyValue
{
    /**
     * @var $redis \Redis
     */
    public $redis;

    private $host;
    private $conf;
    private $dbIndex = 0;

    /**
     * Redis constructor.
     * @param array $conf
     * @param int|null $db
     * @throws Error
     */
    public function __construct(array $conf = [], int $db = null)
    {
        $conf += ['host' => '/tmp/redis.sock', 'port' => 0, 'db' => 1];
        if (is_null($db)) $db = intval($conf['db'] ?? 1);
        if (!($db >= 0 and $db <= intval($conf['maxDb'] ?? 16))) {
            throw new Error('Redis库ID选择错误，0库为系统库不可直接调用，不得大于最大库ID', 1, 0);
        }
        $this->dbIndex = $db;

        if (isset($conf['self']) and getenv('REMOTE_ADDR') === '127.0.0.1') {
            $conf['host'] = $conf['self'];
            unset($conf['port']);
        }

        $this->conf = $conf;
        $this->redis = new \Redis();
        $tryCont = 0;
        try {
            tryCont:
            if (isset($conf['pconnect']) and $conf['pconnect']) {
                if ($conf['host'][0] === '/') {
                    if (!$this->redis->pconnect($conf['host'])) {
                        throw new Error("Redis服务器【{$conf['host']}】无法连接。", 1, 1);
                    }
                } else if (!$this->redis->pconnect($conf['host'], intval($conf['port']))) {
                    throw new Error("Redis服务器【{$conf['host']}:{$conf['port']}】无法连接。", 1, 1);
                }
            } else {
                if ($conf['host'][0] === '/') {
                    if (!$this->redis->connect($conf['host'])) {
                        throw new Error("Redis服务器【{$conf['host']}】无法连接。", 1, 1);
                    }
                } else if (!$this->redis->connect($conf['host'], intval($conf['port']))) {
                    throw new Error("Redis服务器【{$conf['host']}:{$conf['port']}】无法连接。", 1, 1);
                }
            }
        } catch (Error $e) {
            if ($tryCont++ > 2) {
                $err = base64_encode(print_r($conf, true));
                throw new Error($e->getMessage() . '/' . $err, $e->getCode(), 1, 1);
            }
            usleep(10000);
            goto tryCont;
        }
        $this->host = [$conf['host'], intval($conf['port'])];

        if (!empty($conf['password'] ?? '') and !$this->redis->auth($conf['password'])) {
            throw new Error("Redis密码错误，无法连接服务器。", 1, 1);
        }
        if (isset($conf['timeout'])) {
            $this->redis->setOption(\Redis::OPT_READ_TIMEOUT, strval($conf['timeout']));
        }
        if (!empty($conf['prefix'] ?? '')) {
            $this->redis->setOption(\Redis::OPT_PREFIX, strval($conf['prefix']));
        }
        if (!isset($conf['nophp'])) {
            $this->redis->setOption(\Redis::OPT_SERIALIZER, strval(\Redis::SERIALIZER_PHP));//序列化方式
        }
        if (!$this->redis->select($this->dbIndex)) {
            throw new Error("Redis选择库【{$this->dbIndex}】失败。", 1, 1);
        }
        if ($conf['flush'] ?? false) $this->redis->flushDB();
    }

    /**
     * 重新选择库ID
     * @param int|null $db
     * @return \Redis
     */
    public function select(int $db = null)
    {
        if (is_null($db)) $db = $this->dbIndex;
        $this->redis->select($db);
        return $this->redis;
    }


    /**
     * 创建一个LIST集合
     * @param string|null $tabName
     * @return RedisList
     */
    public function list(string $tabName)
    {
        if (!isset($this->tmpList[$tabName])) {
            $this->tmpList[$tabName] = new RedisList($this->redis, $tabName);
        }
        return $this->tmpList[$tabName];
    }

    private $tmpList = [];
    private $tmpHash = [];

    /**
     * 创建一个hash表
     * @param string $tabName
     * @return RedisHash
     */
    public function hash(string $tabName)
    {
        if (!isset($this->tmpHash[$tabName])) {
            $this->tmpHash[$tabName] = new RedisHash($this->redis, $tabName);
        }
        return $this->tmpHash[$tabName];
    }

    public function hGet(string $table, string $hashKey)
    {
        $val = $this->redis->hGet($table, $hashKey);
        if (empty($val)) return null;
        return ($val);
    }

    public function hSet(string $table, string $hashKey, $value)
    {
        return $this->redis->hSet($table, $hashKey, $value);
    }

    public function hDel(string $table, string ...$hashKey)
    {
        return $this->redis->hDel($table, ...$hashKey);
    }

    public function hIncrBy(string $table, string $hashKey, int $value)
    {
        return $this->redis->hIncrBy($table, $hashKey, $value);
    }

    public function hGetAll($table)
    {
        return $this->redis->hGetAll($table);
    }

    /**
     * 主机地址
     * @return array
     */
    public function host()
    {
        return $this->host;
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
        if ($ttl) {
            return $this->redis->setex($key, $ttl, $value);
//            return $this->redis->setex($key, $value, "EX {$ttl}");
        } else {
            return $this->redis->set($key, $value);
        }
    }

    public function update(string $key, $value, int $ttl = null)
    {
        $val = $this->get($key);
        if (!$val) return false;
        if (is_array($val)) $value += $val;
        $exp = $this->ttl($key);
        if ($exp > 0) $ttl = $exp;
        return $this->set($key, $value, $ttl);
    }

    /**
     * 清空
     * @param bool $flushAll
     * @return bool|mixed
     */
    public function flush(bool $flushAll = false)
    {
//        if ($flushAll) return $this->redis->flushAll();
        return $this->redis->flushDB();
    }


    /**
     * 发送订阅，需要在swoole中接收
     *
     * @param string $channel
     * @param string $action
     * @param $message
     * @return int
     */
    public function publish(string $channel, string $action, $message)
    {
        $value = [];
        $value['action'] = $action;
        $value['message'] = $message;
        return $this->redis->publish($channel, serialize($value));
    }


    /**
     * 设定过期时间
     * @param string $key
     * @param int|null $ttl
     * @return bool|int
     */
    public function expire(string $key, int $ttl = null)
    {
        if ($ttl === null) {//只是查询过期时间
            return $this->redis->ttl($key);
        } elseif ($ttl === -1) {//设为永不过期
            return $this->redis->persist($key);
        } elseif ($ttl > time()) {//设置指定过期时间戳
            return $this->redis->expireAt($key, $ttl);
        } else {//设定过期时间
            return $this->redis->expire($key, $ttl);
        }
    }

    /**
     * 查询剩余有效期
     * 当 key 不存在时，返回 -2 。 当 key 存在但没有设置剩余生存时间时，返回 -1 。 否则，以秒为单位，返回 key 的剩余生存时间。
     * @param string $key
     * @param int|null $ttl
     * @return bool|int
     */
    public function ttl(string $key, int $ttl = null)
    {
        return $this->expire($key, $ttl);
    }

    /**
     * 给某个键改名
     * 当 key 和 newkey 相同，或者 key 不存在时，返回一个错误。
     * 当 newkey 已经存在时， RENAME 命令将覆盖旧值。
     *
     * @param string $srcKey
     * @param string $dstKey
     * @return bool 改名成功时提示 OK ，失败时候返回一个错误
     */
    public function rename(string $srcKey, string $dstKey)
    {
        return $this->redis->rename($srcKey, $dstKey);
    }


    /**
     * 最近一次持久化保存的时间
     * @param bool $date
     * @return false|int|string
     */
    public function lastSave(bool $date = true)
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
    public function save(bool $now = false)
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
     */
    public function info($option = null)
    {
        return $this->redis->info($option);
    }


    /**
     * 查询某键是否存在
     * @param $key
     * @return bool
     */
    public function exists($key)
    {
        return $this->redis->exists($key);

    }

    /**
     * 读取
     * @param string $key
     * @return array|bool|string
     */
    public function get(string $key)
    {
        return $this->redis->get($key);
    }

    /**
     * 返回所有键，这个可以用通配符'user*'
     * 不过，D_2020_05_1* 匹配不到数据，暂不清楚什么原因，所以加$filter进行过滤
     * @param string|null $keys
     * @param string|null $filter
     * @return array
     */
    public function keys(string $keys = null, string $filter = null)
    {
        $iterator = null;
        $value = [];
        if ($keys === '*') $keys = null;
        while ($val = $this->redis->scan($iterator, $keys)) {
            array_push($value, ...$val);
        }
        if ($filter) {
            $value = array_filter($value, function ($v) use ($filter) {
                return (strpos($v, $filter) === 0);
            });
        }
        return $value;
    }

    /**
     * @param string ...$keys
     * @return int
     */
    public function delete(string ...$keys)
    {
        return $this->redis->delete($keys);
    }


    /**
     * @param string[] ...$keys
     * @return bool|int
     */
    public function del(string ...$keys)
    {
        return $this->redis->del($keys);
    }


    /**
     * @param $key
     * @return int     * $v = ['NULL', 'STRING', 'SET', 'LIST', 'ZSET', 'HASH'];
     */
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

    public function table(string $table)
    {
        // TODO: Implement table() method.
    }


    public function push(string $table, $value)
    {
        return $this->redis->rPush($table, $value);
    }

    public function pop(string $table)
    {
        return $this->redis->lPop($table);
    }

    public function __call($name, $arguments)
    {
        return call_user_func_array([$this->redis, $name], $arguments);
//        return $this->redis->{$name}(...$arguments);
    }

    public function __toString()
    {
        return json_encode($this->conf, 256);
    }


}

