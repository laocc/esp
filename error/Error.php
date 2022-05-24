<?php
declare(strict_types=1);

namespace esp\error;

use Throwable;

class Error implements Throwable
{
    private $filename;
    private $line;
    private $message;
    private $code;
    private $context = null;

    public function __construct($message, int $trace = 1, int $code = 500)
    {
        $this->code = $code;
        if (is_array($message)) {
            $this->filename = $message['file'] ?? '';
            $this->line = $message['line'] ?? 0;
            $this->message = $message['message'] ?? (json_encode($message, 256 | 64 | 128));
        } else {
            if ($trace > 10) $trace = 1;
            $pre = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $trace + 1)[$trace] ?? [];
            $this->filename = $pre['file'] ?? '';
            $this->line = $pre['line'] ?? 0;
        }
        if (empty($message)) return;
        if ($message[0] === '{') {
            $err = json_decode($message, true);
            if (is_array($err) and !empty($err) and isset($err[2])) {
                $this->message = $err[2];
            }
        }
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


    public function setContext($cont)
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
        return $this->filename . '(' . $this->line . ')';
    }

    public function getMessage()
    {
        return $this->message;
    }

    public function getCode()
    {
        return $this->code;
    }

    public function getFile()
    {
        return $this->filename;
    }

    public function getLine()
    {
        return $this->line;
    }

    public function getTrace()
    {
        // TODO: Implement getTrace() method.
    }

    public function getTraceAsString()
    {
        // TODO: Implement getTraceAsString() method.
    }

    public function getPrevious()
    {
        // TODO: Implement getPrevious() method.
    }

    public function __toString()
    {
        return $this->debug(true);
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