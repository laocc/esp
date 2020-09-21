<?php
$file = realpath(dirname(__FILE__) . '/../option.php');
$option = include_once($file);
(new \esp\core\Dispatcher($option, 'cli'))->run();
