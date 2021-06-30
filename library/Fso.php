<?php

namespace esp\library;


class Fso
{

    /**
     * 目录
     * @param string $path
     * @param bool $fullPath
     * @return array
     */
    public function path(string $path, bool $fullPath = false)
    {
        if (!is_dir($path)) return [];
        $array = array();
        $dir = new \DirectoryIterator($path);
        foreach ($dir as $f) {
            if (!$f->isDir()) continue;
            $name = $f->getFilename();
            if ($name === '.' or $name === '..') continue;
            if ($fullPath) {
                $array[] = $f->getPathname();
            } else {
                $array[] = $name;
            }
        }
        return $array;
    }

    /**
     * 文件
     * @param string $path
     * @param  $ext
     * @return array
     */
    public function file(string $path, $ext = null)
    {
        if (!is_dir($path)) return [];
        $array = array();
        $dir = new \DirectoryIterator($path);
        if (is_string($ext)) $ext = [$ext];
        if (!empty($ext)) foreach ($ext as $i => $t) $ext[$i] = ltrim($t, '.');
        foreach ($dir as $f) {
            if (!$f->isFile()) continue;
            if ($ext) {
                if (in_array($f->getExtension(), $ext)) $array[] = $f->getFilename();
            } else {
                $array[] = $f->getFilename();
            }
        }
        return $array;
    }

}