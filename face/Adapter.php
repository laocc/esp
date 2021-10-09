<?php

namespace esp\face;

interface Adapter
{
    /**
     * 送入变量
     * @param array|string $name 如果是数据，须实现转换KV
     * @param null $value
     */
    public function assign($name, $value = null);

    /**
     * 解析视图，返回解析内容
     * @param string $file
     * @param array $value
     * @return string
     */
    public function fetch(string $file, array $value);

    /**
     * 解析视图，直接打印解析内容
     * @param string $file
     * @param array $value
     * @return mixed
     */
    public function display(string $file, array $value);
}
