<?php
declare(strict_types=1);

namespace esp\core\ext;


class SessionFiles implements \SessionHandlerInterface
{
    private $savePath;
    private $_update = false;
    private $_delay = false;
    private $_prefix = '';

    public function __construct(bool $delay = false, string $prefix = '')
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
        if (!file_exists($fil = "{$this->savePath}/{$id}")) return 'a:0:{}';
        return (string)@file_get_contents($fil);
    }

    function write($id, $data)
    {
        if (!$this->_update or $data === 'a:0:{}' or empty($data)) return true;

        return file_put_contents("{$this->savePath}/{$id}", $data) === false ? false : true;
    }

    public function update(bool $update)
    {
        $this->_update = $update;
        return true;
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
