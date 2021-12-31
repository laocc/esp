<?php
declare(strict_types=1);

namespace esp\core\ext;

use Redis;
use esp\error\EspError;
use SessionHandlerInterface;

final class SessionRedis implements SessionHandlerInterface
{
    private $_Redis;
    private $_delay;
    private $_prefix;
    private $_realKey;

    /**
     * SessionRedis constructor.
     * @param bool $delay
     * @param string $prefix
     * @param Redis|null $redis
     */
    public function __construct(bool $delay, string $prefix, Redis $redis = null)
    {
        $this->_delay = $delay;
        $this->_prefix = $prefix;
        if (!is_null($redis)) $this->_Redis = &$redis;
    }


    /**
     * 第一个被调用
     * open 回调函数类似于类的构造函数，在会话打开的时候会被调用。
     * 这是自动开始会话或者通过调用 session_start() 手动开始会话之后第一个被调用的回调函数。
     * 此回调函数操作成功返回 TRUE，反之返回 FALSE。
     * @param string $save_path
     * @param string $session_name 此值是cookies name
     * @return bool
     */
    public function open($save_path, $session_name)
    {
        if (!is_null($this->_Redis)) return true;

        $conf = unserialize($save_path);

        $this->_Redis = new Redis();
        if ($conf['host'][0] === '/') {
            if (!$this->_Redis->connect($conf['host'])) {//sock方式
                throw new EspError("Redis服务器【{$conf['host']}】无法连接。");
            }
        } else if (!$this->_Redis->connect($conf['host'], $conf['port'])) {
            throw new EspError("Redis服务器【{$conf['host']}:{$conf['port']}】无法连接。");
        }

        //用密码登录
        if (isset($conf['password']) and !empty($conf['password']) and !$this->_Redis->auth($conf['password'])) {
            throw new EspError("Redis密码错误，无法连接服务器。");
        }

        $select = $this->_Redis->select(intval($conf['db']));
        if (!$select) {
            throw new EspError("Redis选择库【{$conf['db']}】失败。" . json_encode($conf, 256 | 64));
        }

        return true;
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
        $session = (!$dataString) ? 'a:0:{}' : $dataString;
        $this->_realKey = md5($session);
        return $session;
    }


    /**
     * 当需要新的会话 ID 时被调用的回调函数。返回值应该是一个字符串格式的、有效的会话 ID。
     * session_create_id()里的参数为新产生的ID前缀
     */
    public function create_sid()
    {
        return session_create_id($this->_prefix);
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
        $d = $this->_Redis->del($session_id);
        return boolval($d);
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
        return 1;
    }


    /**
     * 设置或读取过期时间
     * @param int|null $ttl
     * @return bool|int
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
     * 基本上是倒数第二个被调用
     * 如果session_abort()被调用过，则不会调用此方法
     * @param string $session_id
     * @param string $session_data
     * @return bool 会话存储的返回值（通常成功返回 0，失败返回 1）。
     *
     * 在会话保存数据时会调用 write 回调函数。
     * 此回调函数接收当前会话 ID 以及 $_SESSION 中数据序列化之后的字符串作为参数。
     * 序列化会话数据的过程由 PHP 根据 session.serialize_handler 设定值来完成。
     *
     * 序列化后的数据将和会话 ID 关联在一起进行保存。
     * 当调用 read 回调函数获取数据时，所返回的数据必须要和传入 write 回调函数的数据完全保持一致。
     *
     * PHP 会在脚本执行完毕调用 session_write_close() 时调用此回调函数。
     * 注意，在调用完此回调函数之后，PHP 内部会调用 close 回调函数。
     *
     */
    /**
     * @param string $session_id
     * @param string $session_data
     * @return bool
     */
    public function write($session_id, $session_data)
    {
        if (empty($session_data)) return true;
        if ($this->_realKey === md5($session_data)) return true;//session未变更

        if ($this->_delay) {
            $ttl = session_cache_expire() * 60;
        } else {
            $ttl = $this->_Redis->ttl($session_id);
            if ($ttl < 0) $ttl = session_cache_expire() * 60;
        }

        $save = $this->_Redis->set($session_id, $session_data, $ttl);
        return boolval($save);
    }


    /**
     * 最后一个被调用     * 或当执行session_abort时就立即执行
     * 当调用 session_write_close() 并执行 write 回调函数调用之后调用close。
     * @return bool
     * close 回调函数类似于类的析构函数。
     * 此回调函数操作成功返回 TRUE，反之返回 FALSE。
     */
    public function close()
    {
        try {
            $this->_Redis->close();
        } catch (EspError $e) {
            return false;
        }
        return true;
    }

    /**
     * @return Redis
     */
    public function Redis()
    {
        return $this->_Redis;
    }

}