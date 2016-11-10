<?php
namespace www;

use esp\core\Controller;
use esp\plugins\Debug;

abstract class BaseController extends Controller
{
    protected function debug($msg)
    {
        $prev = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT | DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];

        $class = $this->getPlugin('Debug');
        if (!is_object($class) or (!$class instanceof Debug))
            error('这不是一个已注册的插件，或不是Debug的实例');

        $class->relay($msg, $prev);
    }
}