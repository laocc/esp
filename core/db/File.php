<?php

namespace esp\core\db;


final class File
{
    private $path;

    public function __construct($conf)
    {
        $conf['path'] = preg_replace_callback('/\{(_[A-Z_]+)\}/', function ($matches) {
            return defined($matches[1]) ? constant($matches[1]) : $matches[1];
        }, $conf['path']);

        $this->path = ($conf['path']);
        if (!is_dir($this->path)) mkdir($this->path, 0740, true);
        $this->path = rtrim($this->path, '/');
    }

    public function set(string $key, $value)
    {
        return file_put_contents("{$this->path}/{$key}.tmp", serialize($value));
    }

    public function get(string $key)
    {
        if (!is_file("{$this->path}/{$key}.tmp")) return null;
        $val = file_get_contents("{$this->path}/{$key}.tmp");
        return unserialize($val);
    }

    public function flush()
    {
        return false;
    }
}