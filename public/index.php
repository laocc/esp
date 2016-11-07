<?php
define("_SITE", 'index');
define("_ROOT", realpath(__DIR__ . '/../') . '/');
if (!require_once __DIR__ . "/../vendor/autoload.php") {
    exit('请先运行[composer install]');
}

(new wbf\core\Kernel())
    ->bootstrap()
    ->shutdown()
    ->run();
