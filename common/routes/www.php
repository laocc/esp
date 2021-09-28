<?php
return array(

    /**
     * 拦载所有.txt请求，比如微信域名验证
     * 在这里转发到控制器
     */
    'auth' => array(
        'match' => '#/(.+)\.txt$#i',
        'method' => 'get',
        'route' => array(
            'module' => '',
            'controller' => 'index',
            'action' => 'auth'
        ),
        'map' => array(
            'siteID' => 1,
        ),
        'view' => array(
            'path' => null,
        ),
        'cache' => false,
    ),


);