<?php
define("_MODULE", 'www');
define("_ROOT", realpath(__DIR__ . '/../../') . '/');
if (!@include_once __DIR__ . "/../../vendor/autoload.php") {
    exit('请先运行[composer install]');
}
(new wbf\core\Kernel())
    ->bootstrap()
    ->shutdown()
    ->run();
