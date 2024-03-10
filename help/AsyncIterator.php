<?php

namespace esp\help;

use DirectoryIterator;
use Iterator;

class AsyncIterator implements Iterator
{
    private array $files;
    private int $position;

    /**
     * @param string|null $path
     * 在上面的代码中，我们首先将需要遍历的文件保存到 $files 数组中。
     * 在构造函数中，我们使用 DirectoryIterator 对象遍历目录中的所有文件，并将文件路径保存到 $files 数组中。
     * 然后，我们实现 Iterator 接口中的方法。
     * 在 rewind() 方法中，将迭代器指针重置为数组的开头；
     * 在 valid() 方法中，判断迭代器指针是否在数组范围内；
     * 在 current() 方法中，返回指针位置处的文件内容；
     * 在 key() 方法中，返回指针位置；在 next() 方法中，将指针位置加1。
     * 使用这个迭代器，我们可以像使用其他迭代器一样遍历文件内容，例如：
     */
    public function __construct(string $path = null)
    {
        if (is_null($path)) $path = _RUNTIME . "/async";
        $dir = new DirectoryIterator($path);
        $this->files = [];
        foreach ($dir as $f) {
            if ($f->isDot() || $f->isDir()) continue;
            $name = $f->getFilename();
            $nPath = "{$path}/{$name}";
            $this->files[] = $nPath;
        }
        $this->position = 0;
    }

    public function demo()
    {
        $iterator = new AsyncIterator();
        foreach ($iterator as $fileContent) {
            echo $fileContent;
        }
    }

    public function rewind(): void
    {
        $this->position = 0;
    }

    public function valid(): bool
    {
        return isset($this->files[$this->position]);
    }

    public function current(): mixed
    {
        $path = $this->files[$this->position];
        return file_get_contents($path);
    }

    public function key(): mixed
    {
        return $this->position;
    }

    public function next(): void
    {
        ++$this->position;
    }
}