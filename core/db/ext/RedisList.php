<?php

namespace esp\core\db\ext;


use \Redis;

class RedisList
{
    private $redis;
    private $key;

    /**
     * BLPOP                BLPOP 是列表的阻塞式(blocking)弹出原语。
     * BRPOP                BRPOP 是列表的阻塞式(blocking)弹出原语
     *
     * LINDEX       get     返回列表 key 中，下标为 index 的元素。
     * LSET         set     将列表 key 下标为 index 的元素的值设置为 value
     * LLEN         len     返回列表 key 的长度
     * LRANGE       all     返回列表 key 中指定区间内的元素，区间以偏移量 start 和 stop 指定
     *
     * LPOP         left     移除并返回列表 key 的头元素
     * LPUSH        push     将一个或多个值 value 插入到列表 key 的表头
     * LREM         del      根据参数 count 的值，移除列表中与参数 value 相等的元素
     * LTRIM        trim     对一个列表进行修剪(trim)，就是说，让列表只保留指定区间内的元素，不在指定区间之内的元素都将被删除
     * RPOP         right    移除并返回列表 key 的尾元素
     * RPUSH        append   将一个或多个值 value 插入到列表 key 的表尾(最右边)
     * LINSERT      insert  将值 value 插入到列表 key 当中，位于值 pivot 之前或之后。
     *
     * RPOPLPUSH    pp      命令 RPOPLPUSH 在一个原子时间内，执行以下两个动作：
     *                      将列表 source 中的最后一个元素(尾元素)弹出，并返回给客户端，
     *                      将 source 弹出的元素插入到列表 destination ，作为 destination 列表的的头元素
     *                      相当于：rPop+lPush
     * BRPOPLPUSH           BRPOPLPUSH 是 RPOPLPUSH 的阻塞版本，当给定列表 source 不为空时， BRPOPLPUSH 的表现和 RPOPLPUSH 一样。
     *
     * LPUSHX               将值 value 插入到列表 key 的表头，当且仅当 key 存在并且是一个列表
     * RPUSHX               将值 value 插入到列表 key 的表尾，当且仅当 key 存在并且是一个列表
     */
    /**
     * RedisList constructor.
     * @param Redis $redis
     * @param string $key
     */
    public function __construct(Redis $redis, string $key)
    {
        $this->redis = $redis;
        $this->key = $key;
    }

    /**
     * @param string $key
     * @return $this
     */
    public function key(string $key)
    {
        $this->key = $key;
        return $this;
    }

    /**
     * 返回列表的第index个值
     * @param int $index
     * @return String
     */
    public function get(int $index = 0)
    {
        return $this->redis->lIndex($this->key, $index);
    }

    /**
     * 同时操作两步：
     * 1，删除并返回尾元素，相当于right操作
     * 2，将value插入头元素，相当于push操作
     * @param $value
     * @return string
     */
    public function pp($value)
    {
        return $this->redis->rpoplpush($this->key, $value);
    }

    /**
     * 返回列表里总数
     * @return int
     */
    public function len()
    {
        return $this->redis->lLen($this->key);
    }

    /**
     * 删除
     * @param $key
     * @return int
     */
    public function del($key, int $count = 0)
    {
        return $this->redis->lRem($this->key, $key, $count);
    }

    /**
     * 将第几个元素赋值
     * @param $index
     * @param $value
     * @return bool
     */
    public function set(int $index, int $value)
    {
        return $this->redis->lSet($this->key, $index, $value);
    }

    /**
     * 移除并返回列表 key 的头元素
     * @return string 列表的头元素。    当 key 不存在时，返回 nil 。
     */
    public function left()
    {
        return $this->redis->lPop($this->key);
    }

    /**
     * 移除并返回列表 key 的尾元素
     * @return string
     */
    public function right()
    {
        return $this->redis->rPop($this->key);
    }


    /**
     * 对一个列表进行修剪(trim)，就是说，让列表只保留指定区间内的元素，不在指定区间之内的元素都将被删除
     * @param $star
     * @param $stop
     * @return array
     */
    public function trim($star, $stop)
    {
        return $this->redis->lTrim($this->key, $star, $stop);
    }

    /**
     * 返回列表 key 中指定区间内的元素，区间以偏移量 start 和 stop 指定
     * @param $star
     * @param $stop
     * @return array
     */
    public function all(int $star = 0, int $stop = -1)
    {
        return $this->redis->lRange($this->key, $star, $stop);
    }


    /**
     * 将一个或多个值 value 插入到列表 key 的表尾(最右边)。
     * 如果 key 不存在，一个空列表会被创建并执行 RPUSH 操作。
     * @param $value
     * @return int 执行 RPUSH 操作后，表的长度。
     */
    public function append(...$value)
    {
        return $this->redis->rPush($this->key, ...$value);
    }

    /**
     * 将一个或多个值 value 插入到列表 key 的表头
     * 如果 key 不存在，一个空列表会被创建并执行 LPUSH 操作。
     * @param $value
     * @return int 执行 LPUSH 命令后，列表的长度。
     */
    public function push(...$value)
    {
        return $this->redis->lPush($this->key, ...$value);
    }

    /**
     * 在key之后插入value
     * @param string $key 某个值
     * @param string $value
     * @param int $pos =1之后插，-1之前插
     * @return int 如果命令执行成功，返回插入操作完成之后，列表的长度。
     */
    public function insert(string $key, string $value, int $pos = 1)
    {
        $position = ($pos < 0) ? 'before' : 'after';
        return $this->redis->lInsert($this->key, $position, $key, $value);
    }

}