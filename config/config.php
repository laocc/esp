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

    'resource' => [
        'rand' => true,
        'concat' => false,
        'domain' => 'http://' . _DOMAIN,
        'jquery' => 'js/jquery-2.1.4.min.js',
        'favicon' => 'data:image/bmp;base64,AAAQEP',
        'title' => 'WBF',
        'keywords' => 'WBF',
        'description' => 'WBF',
    ],

    'adapter' => [
        'driver' => 'smarty',
        'compile_dir' => 'smarty/compile',
        'cache_dir' => 'smarty/cache',
    ],

];