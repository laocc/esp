<?php

class Memcached
{

    public function __construct($persistent_id, $callback)
    {

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
     *  检索一个元素
     */
    public function get($key, callable $cache_cb = null, &$cas_token = null)
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
     * 在项目上设置新的过期时间
     */
    public function touch($key, $expiration)
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
     * 向一个新的key下面增加一个元素
     */
    public function add($key, $value, $expiration = null)
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
     *增加数值元素的值
     */
    public function increment($key, $offset = 1, $initial_value = 0, $expiry = 0)
    {
    }

    /**
     * 减少数值元素的值
     */
    public function decrement($key, $offset = 1, $initial_value = 0, $expiry = 0)
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
