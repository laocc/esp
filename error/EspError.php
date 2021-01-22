<?php
declare(strict_types=1);

namespace esp\error;

class EspError extends \ErrorException
{

    public function __construct($message, int $trace = 1, int $code = 500)
    {
        if (is_array($message)) {
            $filename = $message['file'] ?? '';
            $line = $message['line'] ?? 0;
            $message = $message['message'] ?? (json_encode($message, 256 | 64 | 128));
        } else {
            if ($trace > 10) $trace = 1;
            $pre = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $trace + 1)[$trace] ?? [];
            $filename = $pre['file'] ?? '';
            $line = $pre['line'] ?? 0;
        }
        $severity = 1;
        if (empty($message)) return;
        if ($message[0] === '{') {
            $err = json_decode($message, true);
            if (is_array($err) and !empty($err) and isset($err[2])) $message = $err[2];
        }
        parent::__construct($message, $code, $severity, $filename, $line);
    }

    public function debug()
    {
        $err = array();
        $err['success'] = 0;
        $err['error'] = $this->getCode();
        $err['message'] = $this->getMessage();
        $err['file'] = $this->file();
        $err['trace'] = $this->getTrace();
        return $err;
    }

    public function display()
    {
        $err = array();
        $err['success'] = 0;
        $err['error'] = $this->getCode();
        $err['message'] = $this->getMessage();
        $err['file'] = $this->file();
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

        if ($err['message'][0] === '{') {
            $ems = json_decode($err['message'], true);
            if (isset($ems[2])) $err['message'] = $ems[2];
        }

        return $err;
    }

    private $context = null;

    public function setContext($cont)
    {
        $this->context = $cont;
        return $this;
    }

    public function getContext()
    {
        return $this->context;
    }

    public function message()
    {
        return $this->getMessage();
    }

    public function file()
    {
        return $this->getFile() . '(' . $this->getLine() . ')';
    }

}