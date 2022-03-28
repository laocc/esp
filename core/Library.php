<?php
declare(strict_types=1);

namespace esp\core;

use esp\dbs\Pool;
use esp\debug\Debug;
use esp\helper\library\Error;

/**
 * Model是此类的子类，实际业务中所创建的类可以直接引用此类
 *
 * Library主要提供工作类与主控制器之间的通信桥梁作用
 * 在工作类中可以直接调用$this->_controller
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

    final public function __construct(...$param)
    {
        $fstController = false;
        /**
         * 在有些情况下，需要主动用参数形式传入主控制的或任意一个Library的子实类
         * 主要是CLI环境下需要这么做
         */
        if (isset($param[0])) {
            if ($param[0] instanceof Controller) {
                $this->_controller = &$param[0];
                $fstController = true;
            } else if ($param[0] instanceof Library) {
                $this->_controller = &$param[0]->_controller;
                $fstController = true;
            }
        }

        if (is_null($this->_controller)) {
            foreach (debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT | DEBUG_BACKTRACE_IGNORE_ARGS) as $trace) {
                if (!isset($trace['object'])) continue;
                if ($trace['object'] instanceof Controller) {
                    $this->_controller = &$trace['object'];
                    break;
                } else if (($trace['object'] instanceof Library) and isset($trace['object']->_controller)) {
                    $this->_controller = &$trace['object']->_controller;
                    break;
                }
            }
        }

        if (is_null($this->_controller)) {
            throw new Error("未获取到控制器，若本对象是在某插件的回调中创建(例如swoole的tick或task中)，请将创建对像的第一个参数调为\$this，如：new MainModel(\$this)");
        }

        if (method_exists($this, '_init') and is_callable([$this, '_init'])) {
            call_user_func_array([$this, '_init'], $fstController ? array_slice($param, 1) : $param);
        }

        if (is_null($this->_controller->_pool) and isset($this->_dbs_label_)) {
            $conf = $this->database_config ?? $this->_controller->_config->get('database');
            $this->_controller->_pool = new Pool($conf, $this->_controller);
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
     * @param $data
     * @param int $lev
     * @return Debug|false|null
     */
    final public function debug($data = '_R_DEBUG_', int $lev = 1)
    {
        return $this->_controller->_dispatcher->debug($data, $lev + 1);
    }

    /**
     * @param string $lockKey
     * @param callable $callable
     * @param mixed ...$args
     * @return null
     */
    final public function locked(string $lockKey, callable $callable, ...$args)
    {
        return $this->_controller->_dispatcher->locked($lockKey, $callable, ...$args);
    }

    final public function config(...$key)
    {
        return $this->_controller->_config->get(...$key);
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
        return $this->_controller->publish($action, $value);
    }

    /**
     *
     * 发送到队列，不建议在web环境中用队列，根据生产环境测试，经常发生堵塞
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
        return $this->_controller->queue($action, $data);
    }

    /**
     * 注册关门后操作
     * @param callable $fun
     * @param mixed ...$params
     * @return $this
     */
    final public function shutdown(callable $fun, ...$params): Library
    {
//        $this->_controller->_dispatcher->shutdown($fun, ...$params);
        register_shutdown_function($fun, ...$params);
        return $this;
    }

    final public function redirect(string $url)
    {
        $this->_controller->redirect($url);
    }

    /**
     * 注册屏蔽的错误
     *
     * @param string $file
     * @param int $line
     * @return $this
     */
    final function ignoreError(string $file, int $line): Library
    {
        $this->_controller->_dispatcher->ignoreError($file, $line);
        return $this;
    }

    /**
     * var_export
     *
     * @return string
     */
    public static function __set_state()
    {
        return __CLASS__;
    }

    /**
     * echo
     *
     * @return string
     */
    public function __toString()
    {
        return print_r($this, true);
    }

    /**
     * var_dump
     * @return array
     */
    public function __debugInfo()
    {
        return [__CLASS__];
    }

}