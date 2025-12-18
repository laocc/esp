<?php

namespace esp\help;

use esp\core\Library;
use esp\error\Error;

class Locked extends Library
{
    private int $option;
    private string $lockKey;//锁标识
    private bool $isRedis;

    public function _init(int $option, string $lockKey)
    {
        if (!preg_match('/^[\w\-\.]{1,50}$/', $lockKey)) {
            throw new Error('锁名不可含特殊字符，限1-50字符');
        }

        $this->option = $option;
        $this->lockKey = $lockKey;
        $this->isRedis = str_ends_with($lockKey, 'redis');
    }

    public function setOption(int $option): Locked
    {
        $this->option = $option;
        return $this;
    }

    public function setKey(string $lockKey): Locked
    {
        if (!preg_match('/^[\w\-\.]{1,50}$/', $lockKey)) {
            throw new Error('锁名不可含特殊字符，限1-50字符');
        }

        $this->lockKey = $lockKey;
        $this->isRedis = str_ends_with($lockKey, 'redis');
        return $this;
    }

    public function locked(string $lockKey, callable $callable, ...$args): mixed
    {
        if ($this->isRedis) {
            return $this->redis($callable, ...$args);
        }

        return $this->file($callable, ...$args);
    }

    /**
     * @param callable $callable 待执行的回调函数
     * @param mixed ...$args 回调函数参数
     * @return mixed 回调执行结果 | 'locked'（获取锁失败）
     */
    public function redis(callable $callable, ...$args): mixed
    {
        $redisKey = "locked.{$this->lockKey}";
        $maxWait = 50; // 默认5秒
        if ($this->option & 2) {
            $maxWait = 100; // 10秒
        } elseif ($this->option & 4) {
            $maxWait = 200; // 20秒
        }
        $maxWait = max(1, min($maxWait, 300)); // 限制最大等待30秒，最小1次

        // 3. 生成唯一锁值（用于释放锁时校验，避免误删其他进程的锁）
        $lockValue = uniqid('lock_', true) . getmypid(); // 唯一标识 + 进程ID
        $lockExpire = intval($maxWait * 0.1 + 2); // 锁过期时间（比最大等待多1秒，避免死锁）

        for ($i = 0; $i < $maxWait; $i++) {
            $set = $this->_controller->_redis->set($redisKey, $lockValue, ['NX', 'EX' => $lockExpire]);

            if ($set) {
                try {

                    $this->debug("[red;in lockedRedis({$redisKey})>>>>>>>>]");
                    $val = $callable(...$args);
                    $this->debug("[red;out lockedRedis({$redisKey})<<<<<<<]");
                    return $val;

                } finally {
                    if ($this->option & 8) {
                        $script = <<<LUA
                        if redis.call('get', KEYS[1]) == ARGV[1] then
                            return redis.call('del', KEYS[1])
                        else
                            return 0
                        end
                    LUA;
                        $lVal = 's:' . strlen($lockValue) . ':"' . $lockValue . '";';
                        $this->_controller->_redis->eval($script, [$redisKey, $lVal], 1);
                    } else {
                        $this->_controller->_redis->del($redisKey);
                    }
                }
            }

            if ($this->option & 1) return 'locked'; // 非等待锁：直接返回失败

            // 6. 指数退避重试（避免请求风暴）：0.1秒 → 0.15秒 → 0.2秒... 最大1秒
            $sleepUs = 100000 + min($i * 50000, 900000);
//            if (_CLI) echo "usleep({$sleepUs})\n";
            usleep($sleepUs);
        }

        return 'locked';
    }


    /**
     * @param callable $callable
     * @param ...$args
     * @return mixed
     */
    public function file(callable $callable, ...$args): mixed
    {
        $operation = ($this->option & 1) ? (LOCK_EX | LOCK_NB) : LOCK_EX;
        $fn = fopen(($lockFile = "/tmp/flock_{$this->lockKey}.flock"), 'a');
        if (!$fn) return "{$lockFile} flock error";

        try {
            if (flock($fn, $operation)) {           //加锁
                $this->debug("[red;in lockedFile({$this->lockKey})>>>>>>>>]");
                $rest = $callable(...$args);    //执行
                $this->debug("[red;out lockedFile({$this->lockKey})<<<<<<<]");
                return $rest;
            } else {
                return "locked: Running";
            }

        } catch (\Error|\Exception $error) {
            $err = [];
            $err['file'] = $error->getFile();
            $err['line'] = $error->getLine();
            $err['message'] = $error->getMessage();
            $this->debug()->error($err);
            return "locked:{$err['message']}";

        } finally {

            if (is_resource($fn)) {
                flock($fn, LOCK_UN);//解锁
                fclose($fn);
            }

            $this->ignoreError(__FILE__, __LINE__ + 1);//忽略下一行可能的出错
            if (!($this->option & 2) && is_readable($lockFile)) @unlink($lockFile);
        }

    }

}