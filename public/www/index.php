<?php
ini_set('error_reporting', E_ALL);
ini_set('display_errors', true);
ini_set('date.timezone', 'Asia/Shanghai');


define("_MODULE", 'www');
define("_ROOT", realpath(__DIR__ . '/../../') . '/');
if (!@include __DIR__ . "/../../vendor/autoload.php") {
    exit('请先运行[composer install]');
}
(new esp\core\Kernel())
    ->bootstrap()
    ->shutdown()
    ->run();
