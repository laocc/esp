<?php
declare(strict_types=1);

namespace esp\core;

use Throwable;

class Handler
{

    public function __construct(array $option, callable $ignoreCallback)
    {
        $this->register_handler($option);
    }

    /**
     * 简单处理出错信息
     */
    private function register_handler(array $option): void
    {
        set_error_handler(function (...$err) {
            //被 @ 抑制，或被 error_reporting 屏蔽的错误，不响应
            if (!(error_reporting() & $err[0])) return;

            http_response_code(500);
            header("Status: 500 Internal Server Error", true);
            echo("[{$err[0]}]{$err[1]}");
        });
        set_exception_handler(function (Throwable $error) {
            http_response_code(500);
            header("Status: 500 Internal Server Error", true);
            echo("[{$error->getCode()}]{$error->getMessage()}");
        });
    }

}