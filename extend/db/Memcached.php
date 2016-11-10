<?php
namespace esp\extend\db;

use esp\core\Config;

/**
 * Class Memcache
 * @package esp\extend\db
 *
 * http://pecl.php.net/package/memcached
 * 函数表在PHP手册中可找到
 */
class Memcached implements ext\Nosql
{
    private $server;
    private $_table;

    public function __construct()
    {
        $conf = Config::get('memcached');
        $options = [
            [\Memcached::OPT_RECV_TIMEOUT => 1000],//读取超时时间，单位毫秒
            [\Memcached::OPT_SEND_TIMEOUT => 1000],//发送超时时间，单位毫秒
            [\Memcached::OPT_SERVER_FAILURE_LIMIT => 5],//指定一个服务器连接的失败重试次数限制
            [\Memcached::OPT_CONNECT_TIMEOUT => 500],//在非阻塞模式下这里设置的值就是socket连接的超时时间，单位毫秒
            [\Memcached::OPT_RETRY_TIMEOUT => 300],//等待失败的连接重试的时间，单位秒
            [\Memcached::OPT_DISTRIBUTION => 1],//0：余数法，1：基于libketama一致性分布算法分配机制
            [\Memcached::OPT_LIBKETAMA_COMPATIBLE => true],//开启兼容的libketama类行为，采用MD5
            [\Memcached::OPT_TCP_NODELAY => true],//开启已连接socket的无延迟特性
            [\Memcached::OPT_NO_BLOCK => true],//开启异步I/O。这将使得存储函数传输速度最大化。
        ];

        $this->server = new \Memcached (_MODULE);
        $this->server->setOptions($options);
        $this->server->addServers($conf['server']);


        //OPT_PREFIX_KEY
    }


    /**
     * 指定表
     * @param $table
     * @return $this
     */
    public function table($table)
    {
        $this->_table = $table;
        $this->server->setOption(\Memcached::OPT_PREFIX_KEY, $table);
        return $this;
    }

    /**
     * 读取【指定表】的所有行键
     * @param $table
     * @return array
     */
    public function keys($try = 0)
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
    public function set($key, $array, $ttl = 0)
    {
        return $this->server->set($key, $array, $ttl);
    }


    /**
     * 读取【指定表】的【行键】数据
     * @param $key
     * @return array
     */
    public function get($key = null, $try = 0)
    {
        return $this->server->get($key);
    }


    /**
     * 删除key或清空表
     * @param $key
     * @return bool
     */
    public function del($key = null)
    {
        if (is_array($key)) return $this->server->deleteMulti($key);
        return $this->server->delete($key);
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
        return $this->server->increment($TabKey, $incrby);
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
}