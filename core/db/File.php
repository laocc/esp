<?php

namespace esp\core\db;

use esp\error\EspError;

/**
 * 简单文件存储缓存
 * Class File
 * @package esp\core\db
 */
final class File
{
    private $path;
    private $ext = 'TEMP';

    public function __construct(array $conf)
    {
        $this->path = preg_replace_callback('/\{(_[A-Z_]+)\}/', function ($matches) {
            return defined($matches[1]) ? constant($matches[1]) : $matches[1];
        }, ($conf['path'] ?? '/tmp'));

        if (!is_dir($this->path)) mkdir($this->path, 0740, true);
        $this->path = rtrim($this->path, '/');
    }

    public function set(string $key, $value, $ttl = 0)
    {
        return file_put_contents("{$this->path}/{$key}.{$this->ext}", serialize($value));
    }

    public function get(string $key)
    {
        if (!is_file("{$this->path}/{$key}.{$this->ext}")) return null;
        $val = file_get_contents("{$this->path}/{$key}.{$this->ext}");
        return unserialize($val);
    }

    public function del(string $key)
    {
        $file = "{$this->path}/{$key}.{$this->ext}";
        if (is_file($file)) return unlink($file);
        return false;
    }

    public function ttl($key)
    {
        return 86400 * 365;
    }

    public function flush()
    {
        $dir = new \DirectoryIterator($this->path);
        foreach ($dir as $f) {
            if ($f->getExtension() === $this->ext) {
                unlink($this->path . '/' . $f->getFilename());
            }
        }
        return true;
    }

    /**
     * @param string $table
     * @return File
     */
    public function hash(string $table)
    {
        $conf = ['path' => "{$this->path}/{$table}"];
        return new File($conf);
    }

    public function host()
    {
        throw new EspError("当前系统只是简单文件存储服务，请改用Redis服务", 1);
    }

    public function publish(string $channel, string $action, $message)
    {
        throw new EspError("当前系统只是简单文件存储服务，请改用Redis服务", 1);
    }

}