<?php

$api = 'http://www.rpc.com/';
$cli = new \esp\core\rpc\Client($api);

//$cli->async();



$cli->set([
    'token' => 'myToken',
    'agent' => 'myAgent',
    'sign' => $cli::SIGN_C_S | $cli::SIGN_S_C,
    'fork' => false,
]);


$time = microtime(true);

$success = function ($index, $value) {
    if ($value instanceof \Error) {
        throw new \Exception($value->getMessage());
    } else {
        print_r(['index' => $index, 'value' => $value]);
    }
};

$cli->task('http://rpc.kaibuy.top/server.php?task', 'test', [1], $success);
//$cli->task('http://192.168.1.11:80/server.php?task', 'test', [1, $str], $success);
//$cli->call('http://rpc.kaibuy.top/server.php?call', 'test', [2]);
//$cli->task($url, 'test', [1], $success);
//$cli->task($url, 'test', [2]);

$cli->send($success);


