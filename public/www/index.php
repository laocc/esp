<?php
define('_STAR', [microtime(true), memory_get_usage()]);

ini_set('error_reporting', -1);
ini_set('display_errors', true);
ini_set('date.timezone', 'Asia/Shanghai');
define("_MODULE", 'www');
define("_ROOT", dirname(dirname(__DIR__)));

is_readable($auto = (_ROOT . "/vendor/autoload.php")) ? include($auto) : exit('composer dump-autoload --optimize');

$option = include('../config.php');
(new esp\core\Dispatcher($option))->bootstrap()->run();
