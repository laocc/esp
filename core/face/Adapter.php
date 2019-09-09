<?php

namespace esp\core\face;


interface Adapter
{
    /**
     * 送入变量
     * @param array|string $name 如果是数据，须实现转换KV
     * @param null $value
     */
    public function assign($name, $value = null);

    /**
     * 解析视图
     * @param string $file
     * @param array $value
     * @return string
     */
    public function fetch(string $file, array $value);
}
