<?php
return [

    'class' => [
        'match' => '/class',
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