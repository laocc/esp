<?php

namespace esp\core;


use Throwable;

class Error extends \Exception
{
    public function __construct(string $message = "", int $code = 0, Throwable $previous = null)
    {
        if (1) {
            print_r([
                'code' => $code,
                'message' => $message,
                'previous' => $this->getTrace(),
            ]);
        }
        exit;
//        parent::__construct($message, $code, $previous);
    }
}