<?php
$option = include_once('../option.php');
(new \esp\core\Dispatcher($option, 'master'))->run();

