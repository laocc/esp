<?php

namespace esp\core\db;

use esp\core\db\ext\KeyValue;

/**
 * Class Memcache
 * @package esp\extend\db
 *
 * http://pecl.php.net/package/memcached
 * 函数表在PHP手册中可找到
 */
class Memcached implements KeyValue
{
    private $server;

    public function __construct(array $conf = [])
    {
        $conf += ['id' => 'test', 'table' => 'test', 'host' => [['127.0.0.1', 11211]], 'option' => null];
        $options = is_array($conf['option']) ? $conf['option'] : [];
        $options += [
            [\Memcached::OPT_CONNECT_TIMEOUT => 300],//14,在非阻塞模式下这里设置的值就是socket连接的超时时间，单位毫秒
            [\Memcached::OPT_RETRY_TIMEOUT => 300],//15,等待失败的连接重试的时间，单位秒
            [\Memcached::OPT_SEND_TIMEOUT => 500],//19,发送超时时间，单位毫秒
            [\Memcached::OPT_RECV_TIMEOUT => 500],//20,读取超时时间，单位毫秒

            [\Memcached::OPT_SERVER_FAILURE_LIMIT => 3],//21,指定一个服务器连接的失败重试次数限制
            [\Memcached::OPT_DISTRIBUTION => 1],//0：余数法，1：基于libketama一致性分布算法分配机制

//            [\Memcached::OPT_LIBKETAMA_COMPATIBLE => true],//开启兼容的libketama类行为，采用MD5
//            [\Memcached::OPT_TCP_NODELAY => true],//开启已连接socket的无延迟特性
//            [\Memcached::OPT_NO_BLOCK => true],//开启异步I/O。这将使得存储函数传输速度最大化。
        ];

        $this->server = new \Memcached($conf['id']);
        $this->server->setOptions($options);
        $this->server->setOption(\Memcached::OPT_PREFIX_KEY, $conf['table'] . '_');
        $this->server->addServers($conf['host']);
        if (!$this->server->getStats()) {
//            throw new \Exception('Memcached 连接失败');
        }
    }

    /**
     * 键前缀，相当于指定表
     * @param $table
     * @return $this
     */
    public function table(string $table)
    {

        $this->server->setOption(\Memcached::OPT_PREFIX_KEY, $table . '_');
        return $this;
    }

    /**
     * 读取【指定表】的所有行键
     * @param $table
     * @return array
     */
    public function keys()
    {
        return $this->server->getAllKeys();
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
        return $this->server->set($key, $array, $ttl);
    }

    /**
     * 在项目上设置新的过期时间
     */
    public function ttl($key, $expiration)
    {
        return $this->server->touch($key, $expiration);
    }


    /**
     * 读取【指定表】的【行键】数据
     * @param $key
     * @return array
     */
    public function get(string $key = null, $try = 0)
    {
        return $this->server->get($key);
    }

    /**
     * 删除key或清空表
     * @param $key
     * @return bool
     */
    public function del(string ...$key)
    {
        if (is_array($key)) return $this->server->deleteMulti($key);
        return $this->server->delete($key);
    }


    /**
     * 计数器，只可是整型，
     * >0   加
     * <0   减
     * =0   获取值
     * @param string $TabKey 键名
     * @param int $incrby 可以是正数、负数，或0，=0时为读取值
     * @return bool
     */
    public function counter(string $key = 'count', int $incrby = 1, $ttl = 0)
    {
        if ($incrby >= 0) {
            return $this->server->increment($key, $incrby, $ttl);
        } else {
            return $this->server->decrement($key, 0 - $incrby, $ttl);
        }
    }

    /**
     *  关闭
     */
    public function close()
    {
        $this->server->quit();
    }


    /**
     * @return bool
     */
    public function ping()
    {
        return !empty($this->server->getStats());
    }


    /**
     * 返回最后一次操作的结果代码
     */
    public function getResultCode()
    {
    }

    /**
     * 返回最后一次操作的结果描述消息
     */
    public function getResultMessage()
    {
    }

    /**
     *检索多个元素
     */
    public function getMulti(array $keys, array &$cas_tokens = null, $flags = null)
    {
    }

    /**
     * 请求多个元素
     */
    public function getDelayed(array $keys, $with_cas = null, callable $value_cb = null)
    {
    }

    /**
     * 抓取下一个结果
     */
    public function fetch()
    {
    }

    /**
     *抓取所有剩余的结果
     */
    public function fetchAll()
    {
    }


    /**
     * 存储多个元素
     */
    public function setMulti(array $items, $expiration = null)
    {
    }

    /**
     *  比较并交换值
     */
    public function cas($cas_token, $key, $value, $expiration = null)
    {
    }

    /**
     * 向已存在元素后追加数据
     */
    public function append($key, $value)
    {
    }

    /**
     * 向一个已存在的元素前面追加数据
     */
    public function prepend($key, $value)
    {
    }

    /**
     * 替换已存在key下的元素
     */
    public function replace($key, $value, $expiration = null)
    {
    }

    /**
     *  获取服务器池中的服务器列表
     */
    public function getServerList()
    {
    }

    /**
     * 清除所有服务器
     */
    public function resetServerList()
    {
    }

    /**
     * 获取服务器池的统计信息
     */
    public function getStats()
    {
    }

    /**
     *  获取服务器池中所有服务器的版本信息
     */
    public function getVersion()
    {
    }


    /**
     * 作废缓存中的所有元素
     */
    public function flush($delay = 0)
    {
    }

    /**
     *  获取Memcached的选项值
     */
    public function getOption($option)
    {
    }

    /**
     * 检查来确定是否正在使用持久连接
     */
    public function isPersistent()
    {
    }

    /**
     * 检查是否最近创建的实例
     */
    public function isPristine()
    {
    }


    /**
     * 获取一个key所映射的服务器信息
     */
    public function getServerByKey($server_key)
    {
    }

    /**
     * 在指定服务器向一个已存在的元素前面追加数据
     */
    public function prependByKey($server_key, $key, $value)
    {
    }


    /**
     * 指定服务器替换已存在key下的元素
     */
    public function replaceByKey($server_key, $key, $value, $expiration = null)
    {
    }

    /**
     * 从指定的服务器删除一个元素
     */
    public function deleteByKey($server_key, $key, $time = 0)
    {
    }

    /**
     * 从指定的服务器删除多个元素
     */
    public function deleteMultiByKey($server_key, array $keys, $time = 0)
    {
    }

    /**
     * 指定服务器增加元素值
     */
    public function incrementByKey($server_key, $key, $offset = 1, $initial_value = 0, $expiry = 0)
    {
    }

    /**
     * 指定服务器减少元素值
     */
    public function decrementByKey($server_key, $key, $offset = 1, $initial_value = 0, $expiry = 0)
    {
    }

    /**
     * 在指定服务器项目上设置新的过期时间
     */
    public function touchByKey($server_key, $key, $expiration)
    {
    }

    /**
     *从特定的服务器检索元素
     */
    public function getByKey($server_key, $key, callable $cache_cb = null, &$cas_token = null)
    {
    }

    /**
     * 从特定服务器检索多个元素
     */
    public function getMultiByKey($server_key, array $keys, &$cas_tokens = null, $flags = null)
    {
    }

    /**
     *从指定的服务器上请求多个元素
     */
    public function getDelayedByKey($server_key, array $keys, $with_cas = null, callable $value_cb = null)
    {
    }

    /**
     * 在特定的服务器上存储一个项目
     */
    public function setByKey($server_key, $key, $value, $expiration = null)
    {
    }

    /**
     * 指定服务器存储多个元素
     */
    public function setMultiByKey($server_key, array $items, $expiration = null)
    {
    }

    /**
     *在指定服务器上比较并交换值
     */
    public function casByKey($cas_token, $server_key, $key, $value, $expiration = null)
    {
    }


    /**
     * 向指定服务器上已存在元素后追加数据
     */
    public function appendByKey($server_key, $key, $value)
    {
    }

    /**
     * 在指定服务器上的一个新的key下增加一个元素
     */
    public function addByKey($server_key, $key, $value, $expiration = null)
    {
    }

}