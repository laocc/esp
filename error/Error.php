<?php
declare(strict_types=1);

namespace esp\error;

use Exception;

class Error extends Exception
{
    protected $context = null;

    public function __construct($message, int $trace = 1, int $code = 500)
    {
        $this->code = $code;
        if (is_array($message)) {
            $this->file = $message['file'] ?? '';
            $this->line = $message['line'] ?? 0;
            $this->message = $message['message'] ?? (json_encode($message, 256 | 64 | 128));
        } else {
            if ($trace > 10) $trace = 1;
            $pre = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $trace + 1)[$trace] ?? [];
            $this->file = $pre['file'] ?? '';
            $this->line = $pre['line'] ?? 0;
            if ($message[0] === '{') {
                $err = json_decode($message, true);
                if (is_array($err) and !empty($err) and isset($err[2])) {
                    $this->message = $err[2];
                }
            } else {
                $this->message = $message;
            }
        }

//        parent::__construct($this->message, 0, $trace);
    }

    public function display(): array
    {
        $err = array();
        $err['success'] = 0;
        $err['error'] = $this->code;
        $err['message'] = $this->message;
        $err['file'] = $this->file();
        if ($err['error'] > 1) {
            $err['trace'] = array_map(function ($e) {
                if (isset($e['file'])) {
                    return "{$e['file']}({$e['line']})";

                } else if (isset($e['class'])) {
                    if (isset($e['function'])) {
                        return "{$e['class']}->{$e['function']}()";
                    }
                    return "{$e['class']}";

                } else if (isset($e['function'])) {
                    return "{$e['function']}()";
                }
                return $e;
            }, $this->getTrace());
        }

        if ($err['message'][0] === '{') {
            $ems = json_decode($err['message'], true);
            if (isset($ems[2])) $err['message'] = $ems[2];
        }

        return $err;
    }


    public function setContext($cont): Error
    {
        $this->context = $cont;
        return $this;
    }

    public function getContext()
    {
        return $this->context;
    }

    public function message(): string
    {
        return $this->message;
    }

    public function file(): string
    {
        return $this->file . '(' . $this->line . ')';
    }


    /**
     * @param bool $json
     * @return array|false|string
     */
    public function debug(bool $json = false)
    {
        $err = array();
        $err['success'] = 0;
        $err['error'] = $this->code;
        $err['message'] = $this->message;
        $err['file'] = $this->file();
        if ($err['error'] > 1) $err['trace'] = $this->getTrace();
        if ($json) return json_encode($err, 320);
        return $err;
    }

}