<?php
declare(strict_types=1);

namespace esp\core;

use esp\core\db\Redis;
use esp\helper\library\Error;

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

    public function __construct(...$param)
    {
        if (isset($param[0])) {
            if ($param[0] instanceof Controller) {
                $this->_controller =& $param[0];
                unset($param[0]);
            } else if ($param[0] instanceof Library) {
                $this->_controller = &$param[0]->_controller;
                unset($param[0]);
            }
        }

        if (is_null($this->_controller)) {
            foreach (debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT | DEBUG_BACKTRACE_IGNORE_ARGS) as $trace) {
                if (!isset($trace['object'])) continue;
                if ($trace['object'] instanceof Controller) {
                    $this->_controller = &$trace['object'];
                    break;
                } else if (($trace['object'] instanceof Library) and $trace['object']->_controller) {
                    $this->_controller = &$trace['object']->_controller;
                    break;
                }
            }
        }

        if (is_null($this->_controller)) {
            throw new Error("未获取到控制器，若本对象是在某插件的回调中创建(例如swoole的tick或task中)，请将创建对像的第一个参数调为\$this，如：new MainModel(\$this)");
        }

        if (method_exists($this, '_init') and is_callable([$this, '_init'])) {
            call_user_func_array([$this, '_init'], $param);
        }
    }

    public function __destruct()
    {
        foreach ($this->_controller->_PdoPool as $b => $branch) {
            foreach ($branch as $r => &$connect) {
                foreach ($connect as $c => &$pdo) $pdo = null;
                $connect = [];
            }
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
        if (_CLI or is_null($this->_controller->_debug)) return null;
        if ($value === '_Debug_Object') return $this->_controller->_debug;

        if (!(is_int($prevTrace) or is_array($prevTrace))) $prevTrace = 0;
        if (is_int($prevTrace)) {
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, ($prevTrace + 1));
            $trace = $trace[$prevTrace] ?? [];
        } else {
            $trace = $prevTrace;
        }
        return $this->_controller->_debug->relay($value, $trace);
    }

    /**
     * @param string $lockKey
     * @param callable $callable
     * @param mixed ...$args
     * @return null
     */
    public function locked(string $lockKey, callable $callable, ...$args)
    {
        return $this->_controller->_dispatcher->locked($lockKey, $callable, ...$args);
    }


    /**
     * 设置并返回debug文件名
     * @param string|null $filename
     * @return string
     */
    final public function debug_file(string $filename = null): string
    {
        if (is_null($this->_controller->_debug)) return 'null';
        return $this->_controller->_debug->filename($filename);
    }


    final protected function config(...$key)
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
        $channel = defined('_PUBLISH_KEY') ? _PUBLISH_KEY : 'REDIS_ORDER';
        return $this->_controller->_redis->publish($channel, $action, $value);
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
        return $this->_controller->_redis->push($key, $data + ['_action' => $action]);
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
     * @param array $_conf
     * @param int $traceLevel
     * @return Redis
     */
    final public function Redis(array $_conf = [], int $traceLevel = 0): Redis
    {
        $conf = $this->_controller->_config->get('database.redis');
        $dbConfig = $conf['db'];
        if (is_array($dbConfig)) $dbConfig = $dbConfig['config'] ?? 1;

        $conf = $_conf + $conf + ['db' => 0];
        if (is_array($conf['db'])) $conf['db'] = $conf['db']['model'] ?? 0;

        if ($conf['db'] === 0 or $conf['db'] === $dbConfig) return $this->_controller->_config->_Redis;

        if (!_CLI and isset($this->_controller->_Redis[$conf['db']])) {
            return $this->_controller->_Redis[$conf['db']];
        }

        $this->_controller->_Redis[$conf['db']] = new Redis($conf);
        $this->debug("New Redis({$conf['db']});", $traceLevel + 1);
        return $this->_controller->_Redis[$conf['db']];
    }


    /**
     * 创建哈希表
     *
     * @param string $table
     * @return db\ext\RedisHash
     */
    final public function Hash(string $table): db\ext\RedisHash
    {
        return $this->Redis()->hash($table);
    }



}