<?php
return [

    'index' => [
        'match' => '/',
        'method' => 'get,post',
        'route' => [
            'module' => 'index',
            'controller' => 'index',
            'action' => 'index'
        ],
        'map' => [
            'id' => 0
        ],
        'view' => [
            'path' => null,
        ],
        'cache' => false,
    ],

    'class' => [
        'match' => '/class',
        'method' => 'get,post',
        'route' => [
            'module' => 'index',
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