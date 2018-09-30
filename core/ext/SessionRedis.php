<?php

namespace esp\core\ext;


class SessionRedis implements \SessionHandlerInterface
{
    private $_Redis;
    private $_conf;
    private $_update;


    /**
     * SessionRedis constructor.
     * @param array|null $config
     */
    public function __construct(array $config = null)
    {
        $this->_conf = $config;
        $this->_Redis = new \Redis();
    }


    public function update(bool $update)
    {
        $this->_update = $update;
        return true;
    }

    /**
     * 设置或读取过期时间
     * @return int|bool
     */
    public function ttl(int $ttl = null)
    {
        if ($ttl) {
            return $this->_Redis->expire(session_id(), $ttl);
        } else {
            return $this->_Redis->ttl(session_id());
        }
    }

    /**
     * 第一个被调用
     * open 回调函数类似于类的构造函数，在会话打开的时候会被调用。
     * 这是自动开始会话或者通过调用 session_start() 手动开始会话之后第一个被调用的回调函数。
     * 此回调函数操作成功返回 TRUE，反之返回 FALSE。
     * @param string $save_path
     * @param string $session_name
     * @return bool
     * @throws \Exception
     */
    public function open($save_path, $session_name)
    {
//        var_dump(['open', $save_path, $session_name, $this->_conf]);
        if (!isset($this->_conf['port']) or intval($this->_conf['port']) === 0) {
            if (!$this->_Redis->connect($this->_conf['host'])) {
                throw new \Exception("Redis服务器【{$this->_conf['host']}】无法连接。");
            }
        } else if (!$this->_Redis->connect($this->_conf['host'], $this->_conf['port'])) {
            throw new \Exception("Redis服务器【{$this->_conf['host']}:{$this->_conf['port']}】无法连接。");
        }

        //用密码登录
        if (isset($conf['password']) and !$this->_Redis->auth($this->_conf['password'])) {
            throw new \Exception("Redis密码错误，无法连接服务器。");
        }

        $select = $this->_Redis->select(intval($this->_conf['db']));
        if (!$select) {
            throw new \Exception("Redis选择库【{$this->_conf['db']}】失败。");
        }

        return $select;
    }

    /**
     * 第二个被调用
     * @param string $session_id
     * @return string
     * 如果会话中有数据，read 回调函数必须返回将会话数据编码（序列化）后的字符串。
     * 如果会话中没有数据，read 回调函数返回空字符串。
     * 在自动开始会话或者通过调用 session_start() 函数手动开始会话之后，PHP 内部调用 read 回调函数来获取会话数据。
     * 在调用 read 之前，PHP 会调用 open 回调函数。
     */
    public function read($session_id)
    {
        $dataString = $this->_Redis->get($session_id);
//        var_dump(['read' => $session_id, 'value' => $dataString]);
        return (!$dataString) ? '' : $dataString;
    }


    /**
     * 当需要新的会话 ID 时被调用的回调函数。返回值应该是一个字符串格式的、有效的会话 ID。
     * session_create_id()里的参数为新产生的ID前缀
     */
    public function create_sid()
    {
//        var_dump(['create_sid' => time()]);
//        return str_rand(10);
        $id = session_create_id($this->_conf['prefix']);
        return $id;
    }

    /**
     * 删除session
     * @param string $session_id
     * @return bool
     * 当调用 session_destroy() 函数，或者调用 session_regenerate_id() 函数并且设置 destroy 参数为 TRUE 时，会调用此回调函数。
     * 此回调函数操作成功返回 TRUE，反之返回 FALSE。
     * 必须返回true，否则在一个新的连接时，session_regenerate_id()总是出错
     */
    public function destroy($session_id)
    {
//        var_dump(['destroy' => $session_id]);
        $this->_Redis->del($session_id);
        return true;
    }

    /**
     * @param int $maxLifetime
     * @return bool
     * 为了清理会话中的旧数据，PHP 会不时的调用垃圾收集回调函数。
     * 调用周期由 session.gc_probability 和 session.gc_divisor 参数控制。
     * 传入到此回调函数的 lifetime 参数由 session.gc_maxlifetime 设置。
     * 此回调函数操作成功返回 TRUE，反之返回 FALSE。
     */
    public function gc($maxLifetime)
    {
//        var_dump(['gc' => $maxLifetime]);
        return true;
    }

    /**
     * 基本上是倒数第二个被调用,即session_write_close()之后
     * 如果session_abort()被调用过，则不会调用此方法
     * @param string $session_id
     * @param string $session_data
     * @return bool
     * 在会话保存数据时会调用 write 回调函数。
     * 此回调函数接收当前会话 ID 以及 $_SESSION 中数据序列化之后的字符串作为参数。
     * 序列化会话数据的过程由 PHP 根据 session.serialize_handler 设定值来完成。
     *
     * 序列化后的数据将和会话 ID 关联在一起进行保存。
     * 当调用 read 回调函数获取数据时，所返回的数据必须要和传入 write 回调函数的数据完全保持一致。
     *
     * PHP 会在脚本执行完毕或调用 session_write_close() 函数之后调用此回调函数。
     * 注意，在调用完此回调函数之后，PHP 内部会调用 close 回调函数。
     */
    public function write($session_id, $session_data)
    {
//        var_dump(['write_id' => $session_id, 'data' => $session_data, 'update' => $this->_update]);
        if ($this->_update) {
            $ttl = $this->_Redis->ttl($session_id);
            if ($ttl < 0) $ttl = session_cache_expire();
            $write = $this->_Redis->set($session_id, $session_data, $ttl);
        } else {
            $write = $this->_Redis->set($session_id, $session_data, session_cache_expire());
        }
        return $write;
    }


    /**
     * 最后一个被调用
     * @return bool
     * close 回调函数类似于类的析构函数。
     * 在 write 回调函数调用之后调用。
     * 当调用 session_write_close() 函数之后，也会调用 close 回调函数。
     * 此回调函数操作成功返回 TRUE，反之返回 FALSE。
     */
    public function close()
    {
        $this->_Redis->close();
        return true;
    }

}