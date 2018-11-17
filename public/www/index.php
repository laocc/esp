<?php
ini_set('error_reporting', -1);
ini_set('display_errors', true);
ini_set('date.timezone', 'Asia/Shanghai');
define("_MODULE", 'www');
define("_ROOT", dirname(__DIR__, 2));

is_readable($auto = (_ROOT . '/vendor/autoload.php')) ? include($auto) : exit("\ncomposer dump-autoload --optimize\n");

$option = include('../config.php');
$option['config'][] = '/config/token.php';
$option['config'][] = '/config/test.json';

//$option['debug']['run'] = false;

//(new esp\core\Dispatcher($option))->bootstrap()->run();
esp\core\Dispatcher::run($option);