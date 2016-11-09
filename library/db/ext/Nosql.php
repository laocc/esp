<?php
namespace db\ext;

interface Nosql
{

    /**
     * 指定表
     * @param $table
     * @return $this
     */
    public function table($table);

    /**
     * 读取【指定表】的所有行键，由于memcached有时读不出getExtendedStats，所以需要允许重试几次
     * @param $table
     * @return array
     */
    public function keys($try = 0);


    /**
     * 存入【指定表】【行键】【行值】
     * @param $table
     * @param $key
     * @param $array
     * @param int $ttl 生存期
     * @return bool
     */
    public function set($key, $array, $ttl = 0);


    /**
     * 读取【指定表】的【行键】数据
     * @param $key
     * @return array
     */
    public function get($key = null, $try = 0);


    /**
     * 删除key或清空表
     * @param $key
     * @return bool
     */
    public function del($key = null);


    /**
     * 计数器，只可是整型，
     * >0   加
     * <0   减
     * =0   获取值
     * @param string $TabKey 表名.键名，但这儿的键名要是预先定好义的
     * @param int $incrby 可以是正数、负数，或0，=0时为读取值
     * @return bool
     */
    public function add($TabKey = 'count', $incrby = 1);


    /**
     *  关闭
     */
    public function close();


    public function ping();

}