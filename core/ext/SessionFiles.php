<?php
declare(strict_types=1);

namespace esp\core\ext;

use SessionHandlerInterface;

final class SessionFiles implements SessionHandlerInterface
{
    private $savePath;
    private $_delay;//自动延时，windows下无效
    private $_prefix;
    private $_realKey;

    /**
     * @param bool $delay 自动延时，windows下无效
     * @param string $prefix
     */
    public function __construct(bool $delay, string $prefix)
    {
        $this->_delay = $delay;
        $this->_prefix = $prefix;
    }

    function open($savePath, $sessionName)
    {
        $this->savePath = $savePath;
        if (!is_dir($this->savePath)) mkdir($this->savePath, 0777);
        return true;
    }

    function close()
    {
        return true;
    }

    function read($id)
    {
        if (!file_exists($fil = "{$this->savePath}/{$id}")) {
            $session = 'a:0:{}';
        } else {
            if (DIRECTORY_SEPARATOR === '/' && ($time = filemtime($fil)) < time()) {
                $session = 'a:0:{}';
            } else {
                $session = file_get_contents($fil);
                if (empty($session)) $session = 'a:0:{}';
            }
        }
        $this->_realKey = md5($session);
        return $session;
    }

    function write($id, $data)
    {
        if (empty($data)) return true;
        if (empty($session_data)) return true;
        if ($this->_realKey === md5($session_data)) return true;//session未变更
        $file = "{$this->savePath}/{$id}";

        $sv = file_put_contents($file, $data);

        if ($this->_delay) {
            $ttl = time() + session_cache_expire() * 60;
            touch($file, $ttl);
        }

        return $sv > 0;
    }

    /**
     * 当需要新的会话 ID 时被调用的回调函数。返回值应该是一个字符串格式的、有效的会话 ID。
     * session_create_id()里的参数为新产生的ID前缀
     */
    public function create_sid()
    {
        return session_create_id($this->_prefix);
    }

    function destroy($id)
    {
        if (file_exists($file = "{$this->savePath}/{$id}")) unlink($file);
        return true;
    }

    function gc($maxlifetime)
    {
        foreach (glob("{$this->savePath}/*") as $file) {
            if (filemtime($file) + $maxlifetime < time() && file_exists($file)) {
                unlink($file);
            }
        }

        return true;
    }
}
