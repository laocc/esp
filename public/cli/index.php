<?php
ini_set('error_reporting', -1);
ini_set('display_errors', true);
ini_set('date.timezone', 'Asia/Shanghai');
define("_MODULE", 'cli');
define("_ROOT", dirname(__DIR__, 2));

is_readable($auto = (_ROOT . "/vendor/autoload.php")) ? include($auto) : exit('composer dump-autoload --optimize');

$option = include(_ROOT . '/public/config.php');
//(new esp\core\Dispatcher($option))->bootstrap()->run();
esp\core\Dispatcher::run($option);