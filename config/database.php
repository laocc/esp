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
        'maxDb' => 16,
        'username' => 'useEsp',
        'password' => 'password',
    ],

    'memcache' => [
        'pConnect' => true,     //是否启用持久化
        'host' => 'localhost',  //主机地址。可以指定为其他传输方式比如unix:///path/to/memcached.sock 来使用Unix域套接字，使用这种方式port参数必须设置为0。
        'port' => 6379,         //端口，默认6379
        'timeout' => 1,         //连接持续（超时）时间，单位秒。默认值1秒，修改此值之前请三思，过长的连接持续时间可能会导致失去所有的缓存优势。
        'table' => _MODULE,     //Memcache实际上没有表概念，但可以在每个字段前加这么个标识，可以避免不同主题数据键冲突
    ],

    'memcached' => [
        'pConnect' => true,     //是否启用持久化
        'host' => 'localhost',  //主机地址。可以指定为其他传输方式比如unix:///path/to/memcached.sock 来使用Unix域套接字，使用这种方式port参数必须设置为0。
        'port' => 6379,         //端口，默认6379
        'timeout' => 1,         //连接持续（超时）时间，单位秒。默认值1秒，修改此值之前请三思，过长的连接持续时间可能会导致失去所有的缓存优势。
        'table' => _MODULE,     //Memcache实际上没有表概念，但可以在每个字段前加这么个标识，可以避免不同主题数据键冲突
    ],
];