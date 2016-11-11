<?php
return [

    'class' => [
        'match' => '/\/tmp.+/',
        'method' => 'get,post',
        'route' => [
            'module' => 'www',
            'controller' => 'index',
            'action' => 'index'
        ],
        'map' => [
            'id' => 1
        ],
        'view' => [
            'path' => null,
        ],
        'cache' => false,
    ],



];