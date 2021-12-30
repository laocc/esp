<?php
declare(strict_types=1);

namespace esp\core;

use esp\core\db\Redis;

/**
 * Model是此类的子类，实际业务中所创建的类可以直接引用此类
 *
 * Class Library
 * @package esp\core
 */
abstract class Library
{
    /**
     * @var $_controller Controller
     */
    public $_controller;
    /**
     * @var $_config Configure
     */
    public $_config;
    /**
     * @var $_redis Redis
     */
    public $_redis;
    /**
     * @var $_debug Debug
     */
    public $_debug;

    public function __construct(...$param)
    {
        foreach (debug_backtrace() as $i => $trace) {
            if (!isset($trace['object'])) continue;
            if ($trace['object'] instanceof Controller) {
                $this->_controller = $trace['object'];
                break;
            } else if (($trace['object'] instanceof Library) and $trace['object']->_controller) {
                $this->_controller = $trace['object']->_controller;
                break;
            }
        }

        $this->_config = $this->_controller->_config;
        $this->_debug = $this->_controller->_debug;
        $this->_redis = $this->_controller->_redis;

        if (method_exists($this, '_init') and is_callable([$this, '_init'])) {
            call_user_func_array([$this, '_init'], $param);
        }
    }

    /**
     * @return Controller
     */
    final public function Controller(): Controller
    {
        return $this->_controller;
    }

    /**
     * @param $value
     * @param $prevTrace
     * @return bool|Debug
     */
    final public function debug($value = '_Debug_Object', $prevTrace = 0)
    {
        if (_CLI or is_null($this->_debug)) return null;
        if ($value === '_Debug_Object') return $this->_debug;

        if (!(is_int($prevTrace) or is_array($prevTrace))) $prevTrace = 0;
        if (is_int($prevTrace)) {
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, ($prevTrace + 1));
            $trace = $trace[$prevTrace] ?? [];
        } else {
            $trace = $prevTrace;
        }
        return $this->_debug->relay($value, $trace);
    }

    /**
     * @param string $lockKey
     * @param callable $callable
     * @param mixed ...$args
     * @return null
     */
    public function locked(string $lockKey, callable $callable, ...$args)
    {
        return $this->_controller->locked($lockKey, $callable, $args);
    }


    /**
     * 设置并返回debug文件名
     * @param string|null $filename
     * @return string
     */
    final public function debug_file(string $filename = null): string
    {
        if (is_null($this->_debug)) return 'null';
        return $this->_debug->filename($filename);
    }


    final protected function config(...$key)
    {
        return $this->_config->get(...$key);
    }

    /**
     * 发送通知信息到redis管道，一般要在swoole中接收
     *
     * 建议不同项目定义不同_PUBLISH_KEY
     *
     * @param string $action
     * @param array $value
     * @return int
     */
    final public function publish(string $action, array $value): int
    {
        $channel = defined('_PUBLISH_KEY') ? _PUBLISH_KEY : 'REDIS_ORDER';
        return $this->_redis->publish($channel, $action, $value);
    }

    /**
     *
     * 发送到队列，一般不建议在web环境中用队列，根据生产环境测试，经常发生堵塞
     *
     * @param string $action
     * @param array $data
     * @return int
     *
     * 用下面方法读取
     * while ($data = $this->_redis->lPop($queKey)){...}
     */
    final public function queue(string $action, array $data): int
    {
        $key = defined('_QUEUE_TABLE') ? _QUEUE_TABLE : 'REDIS_QUEUE';
        return $this->_redis->push($key, $data + ['_action' => $action]);
    }

    /**
     * 注册关门后操作
     * @param callable $fun
     * @param mixed ...$parameter
     * @return $this
     */
    final public function shutdown(callable $fun, ...$parameter): Library
    {
        register_shutdown_function($fun, ...$parameter);
        return $this;
    }

    public function redirect(string $url)
    {
        $this->_controller->redirect($url);
    }


}