<?php
declare(strict_types=1);

namespace esp\core\db;

use esp\error\EspError;
use esp\core\db\ext\KeyValue;
use esp\helper\library\Error;
use function esp\helper\mk_dir;

/**
 * 简单文件存储缓存
 * Class File
 */
final class File implements KeyValue
{
    private $path;
    private $ext = 'TEMP';

    /**
     * File constructor.
     * @param array $conf
     * @throws Error
     */
    public function __construct(array $conf)
    {
        $this->path = preg_replace_callback('/\{(_[A-Z_]+)\}/', function ($matches) {
            return defined($matches[1]) ? constant($matches[1]) : $matches[1];
        }, ($conf['path'] ?? '/tmp'));
        mk_dir($this->path);
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

    /**
     * 删除key
     * @param $key
     * @return bool
     */
    public function del(string ...$key)
    {
        $file = "{$this->path}/{$key[0]}.{$this->ext}";
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


    /**
     * 指定表，也就是指定键前缀
     * @param $table
     * @return $this
     */
    public function table(string $table)
    {
        return $this;
    }

    /**
     * 读取【指定表】的所有行键
     * @return array
     */
    public function keys()
    {
        return [];
    }


    /**
     * 计数器，只可是整型，
     * >0   加
     * <0   减
     * =0   获取值
     * @param string $key 表名.键名，但这儿的键名要是预先定好义的
     * @param int $incrby 可以是正数、负数，或0，=0时为读取值
     * @return bool
     */
    public function counter(string $key = 'count', int $incrby = 1)
    {
        return true;
    }

    /**
     *  关闭
     */
    public function close()
    {
    }

    /**
     * @return bool
     */
    public function ping()
    {
        return true;
    }
}