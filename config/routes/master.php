<?php
return array(

    'qrCode' => array(
        'match' => '/\/(qr)\/(\w+)\/?/i',
        'method' => 'post,get',
        'route' => array(
            'module' => 'api',
            'controller' => 'qrcode',
            'action' => 'index'
        ),
        'map' => array(
            'test' => 2,
        ),
        'view' => array(
            'path' => null,
        ),
        'cache' => false,
    ),

    'income' => array(
        'match' => '/\/(income)\/?/i',
        'method' => 'post',
        'route' => array(
            'module' => 'api',
            'controller' => 'pay',
            'action' => 'income'
        ),
        'map' => array(
//            'test' => 0,
        ),
        'view' => array(
            'path' => null,
        ),
        'cache' => false,
    ),


);