<?php
return [

    'database' => [
        'driver' => 'mysql',
        'host' => 'localhost',
        'database' => 'mffc',
        'username' => 'root',
        'password' => 'password',
        'charset' => 'utf8',
        'collation' => 'utf8_general_ci',
        'prefix' => ''
    ],

    'adapter' => [
        'driver' => 'smarty',
        'compile_dir' => 'smarty/compile',
        'cache_dir' => 'smarty/cache',
    ],

];