<?php

namespace esp\help;

use esp\core\Library;
use esp\error\Error;

class Locked extends Library
{
    private int $option;
    private string $lockKey;//锁标识
    private bool $isRedis;
    private bool $isGo;

    public function _init(int $option, string $lockKey)
    {
        if (!preg_match('/^[\w\-\.]{1,50}$/', $lockKey)) {
            throw new Error('锁名不可含特殊字符，限1-50字符');
        }

        $this->option = $option;
        $this->lockKey = $lockKey;

        $this->isRedis = str_ends_with($lockKey, 'redis');
        $this->isGo = str_ends_with($lockKey, 'go');
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
        $this->isGo = str_ends_with($lockKey, 'go');
        return $this;
    }

    public function run(callable $callable, ...$args): mixed
    {
        if ($this->isRedis) {
            return $this->redis($callable, ...$args);
        }

        if ($this->isGo) {
            return $this->go($callable, ...$args);
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
     * 用go锁(由常驻的go服务统一持有锁)
     *
     * 与file()、redis()的区别：锁不落在本进程，而是交给 /tmp/locked_pipe 上的
     * 常驻服务管理，因此只走"申请-使用-释放"三步，不再做本地重试等待。
     *
     * @param callable $callable 待执行的回调函数
     * @param mixed ...$args 回调函数参数
     * @return mixed 回调执行结果 | 'locked error'（申请锁失败或go服务不可用）
     */
    public function go(callable $callable, ...$args): mixed
    {
        $pipe = "/tmp/locked_pipe";

        /**
         * 连接失败时 stream_socket_client() 返回 false，
         * 原实现未判断即直接 fwrite()，会抛出 TypeError；
         * 这里与file()保持一致，以 'locked error' 返回，不做抛异常处理。
         */
        $socket = @stream_socket_client("unix://{$pipe}", $errno, $errstr, 1);
        if (!$socket) {
            $this->debug("[red;lockedGo({$this->lockKey}) connect pipe failed: {$errstr}]");
            return 'locked error';
        }

        $result = 'locked error';
        try {
            $acquire = json_encode(['action' => 'acquire', 'key' => $this->lockKey]) . "\n";
            if (fwrite($socket, $acquire) === false) return $result;

            /**
             * 读应答(读到换行为止)
             *
             * 原实现是 fread($socket, 1024) 定长读，应答一旦超过1024字节就被截断，
             * json_decode 得到 null，接着读 null 的属性又会报错。
             * 注意 fgets($socket, 1024) 同样会在1023字节处截断（此时结尾没有换行符），
             * 所以这里用循环按"行结束符"判断读完，而不是靠单次读取的长度。
             * 单行上限 1MB，防止服务端异常时无限占用内存。
             */
            $buffer = '';
            $readErr = false;
            while (!str_contains($buffer, "\n")) {
                $chunk = fgets($socket, 1024);
                if ($chunk === false or $chunk === '') {
                    $readErr = true;
                    break;
                }
                $buffer .= $chunk;
                if (strlen($buffer) > 1048576) {
                    $readErr = true;
                    break;
                }
            }

            $response = $readErr ? null : json_decode($buffer, true);
            if (!is_array($response) or empty($response['success'])) {
                $this->debug("[red;lockedGo({$this->lockKey}) acquire refused]");
                return $result;
            }

            try {
                $this->debug("[red;in lockedGo({$this->lockKey})>>>>>>>>]");
                $val = $callable(...$args);
                $this->debug("[red;out lockedGo({$this->lockKey})<<<<<<<]");
                //业务返回值原样保留，由外层 finally 负责释放锁
                $result = $val;

            } catch (\Throwable $error) {
                $err = [];
                $err['file'] = $error->getFile();
                $err['line'] = $error->getLine();
                $err['message'] = $error->getMessage();
                $this->debug()->error($err);
                //锁已申请到，业务异常说明这次执行失败，返回固定标识，
                //避免像file()那样把异常信息拼成字符串（锁内返回值不得是字符串）
                $result = 'locked error';
            }

            //释放锁：独立于业务，保证业务正常或异常都会走到
            $release = json_encode(['action' => 'release', 'key' => $this->lockKey]) . "\n";
            @fwrite($socket, $release);

            return $result;

        } finally {
            /**
             * 这里只做收尾，绝对不能 return：
             * 原实现在 finally 里写了 return ""，会把 try 里的返回值整个吃掉，
             * 结果是go锁下业务回调的返回值永远丢失、永远返回空串。
             * 断开socket即等同于放弃锁（服务端可据此回收），故fclose不可省略。
             */
            @fclose($socket);
        }
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