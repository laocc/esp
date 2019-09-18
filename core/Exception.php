<?php

namespace esp\core;


class Exception extends \Exception
{
    public function display()
    {
        $this->recode();
        print_r($this->getMessage());
        exit;
    }

    private function recode()
    {
        $debug = Debug::class();
        $info = [
            'time' => date('Y-m-d H:i:s'),
            'HOST' => getenv('SERVER_ADDR'),
            'Url' => _HTTP_ . _DOMAIN . _URI,
            'Debug' => !is_null($debug) ? $debug->filename() : '',
            'Error' => $this->getMessage(),
            'Server' => $_SERVER,
            'Post' => file_get_contents("php://input"),
        ];
        $path = _RUNTIME . "/error";
        $filename = 'YmdHis';

        $filename = $path . "/" . date($filename) . mt_rand() . '.md';
        if (RPC::post('/debug', $filename, $info)) return;

        if (!is_dir($path)) mkdir($path, 0740, true);
        if (is_readable($path)) file_put_contents($filename, json_encode($info, 64 | 128 | 256), LOCK_EX);
    }

}