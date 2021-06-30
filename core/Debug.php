<?php
declare(strict_types=1);

namespace esp\core;

class Debug
{

    public function __construct(array $conf)
    {
    }

    public function error($error, $tract = null)
    {
        return $this;
    }

    public function warn($error, $tract = null)
    {
        return $this;
    }

    public function setRouter(array $request)
    {
        return $this;
    }

    public function setResponse(array $result)
    {
        return $this;
    }

    /**
     * @param string $filename
     * @param string $data
     * @return string
     */
    public function save_file(string $filename, string $data)
    {
        return $this;
    }

    /**
     * 保存记录到的数据
     * @param string $pre
     * @return string
     */
    public function save_logs(string $pre = '')
    {
        return '';
    }

    /**
     * 禁用debug
     * @param int $mt 禁用几率，
     * 0    =完全禁用
     * 1-99 =1/x几率启用
     * 1    =1/2机会
     * 99   =1%的机会启用
     * 100  =启用
     * @return $this
     */
    public function disable(int $mt = 0)
    {
        return $this;
    }

    /**
     * 启动，若程序入口已经启动，这里则不需要执行
     * @param null $pre
     * @return $this
     */
    public function star($pre = null)
    {
        return $this;
    }

    /**
     * 停止记录，只是停止记录，不是禁止
     * @param null $pre
     * @return $this|null
     */
    public function stop($pre = null)
    {
        return $this;
    }


    /**
     * 创建一个debug点
     *
     * @param $msg
     * @param array|null $prev 调用的位置，若是通过中间件调用，请在调用此函数时提供下面的内容：
     * @return $this|bool
     */
    public function relay($msg, array $prev = null)
    {
        return $this;
    }

    public function filename(string $file = null)
    {
        return '';
    }

}