<?php

/**
 * 这个类没什么实际用处，也不能真的加载，否则会影响yac无法真正实例化
 * 只是为了让编辑器有个参照，免得下划线看着别扭
 */
class Yac
{
    public function __construct($fix = null)
    {

    }

    /**
     * @param $key
     * @return mixed
     */
    public function get($key)
    {
        return;
    }

    /**
     * @param $key
     * @param $val
     * @param null $ttl
     * @return bool
     */
    public function set($key, $val, $ttl = null)
    {
        return true;
    }

    /**
     * @return array
     */
    public function dump($limit = 100)
    {
        return [];
    }

    /**
     * @return array
     */
    public function info()
    {
        return [];
    }

    /**
     * @param $key
     * @return bool
     */
    public function delete($key)
    {
        return true;
    }

    /**
     * @return bool
     */
    public function flush()
    {
        return true;
    }
}