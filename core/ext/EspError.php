<?php

namespace esp\core\ext;


class EspError extends \ErrorException
{

    public function debug()
    {
        $err = Array();
        $err['code'] = $this->getCode();
        $err['message'] = $this->getMessage();
        $err['file'] = $this->file();
        $err['trace'] = $this->getTrace();
        return $err;
    }

    public function display()
    {
        $err = Array();
        $err['code'] = $this->getCode();
        $err['message'] = $this->getMessage();
        $err['file'] = $this->file();
        $err['trace'] = array_map(function ($e) {
            return "{$e['file']}({$e['line']})";
        }, $this->getTrace());

        if ($err['message'][0] === '{') {
            $ems = json_decode($err['message'], true);
            if (isset($ems[2])) $err['message'] = $ems[2];
        }

        return $err;
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