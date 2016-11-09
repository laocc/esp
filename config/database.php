<?php
return [
    'mysql' => [
        'master' => 'localhost',
        'port' => 3306,
        'db' => 'espDemo',
        'username' => 'useEsp',
        'password' => 'password',
        'charset' => 'utf8',
        'collation' => 'utf8_general_ci',
        'prefix' => ''
    ],

    'mongodb' => [
        'host' => 'localhost',
        'port' => 6379,
        'db' => 1,
        'username' => 'useEsp',
        'password' => 'password',
    ],

    'redis' => [
        'host' => '127.0.0.1',
        'port' => 6379,
        'db' => 1,
        'username' => 'useEsp',
        'password' => 'password',
    ],

    'memcache' => [
        'host' => 'localhost',
        'port' => 6379,
        'db' => 1,
        'username' => 'useEsp',
        'password' => 'password',
    ],
];