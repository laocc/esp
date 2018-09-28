<?php

namespace esp\core;

class Mistake
{
    private $request;
    private $debug;

    private $_font_size;
    private $_show_route;

    public function __construct(Request $request, Debug $debug, array $display)
    {
        $this->request = $request;
        $this->debug = $debug;
        $this->_show_route = true;
        $this->_font_size = '100%';

    }

    /**
     *
     * 产生一个错误信息，具体处理，由\plugins\Mistake处理
     * @param $str
     * @param int $level 错误级别，012，
     *
     * 0：系统停止执行，严重级别
     * 1：提示错误，继续运行
     * 2：警告级别，在生产环境中不提示，仅发给管理员
     */
    public static function try_error($str, $level = 0, $trace = null)
    {
        if ($level < 0) $level = 0;
        if ($level > 2) $level = 2;
        $level = 256 << $level;
        if (is_string($str)) {
            $err = $trace ?: debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
            unset($err['function']);
            $err['error'] = $str;
            $err['code'] = $level;
            $str = json_encode($err, 256);
        }
        //产生一个用户级别的 error/warning/notice 信息
        trigger_error($str, $level);
        exit;
    }


}