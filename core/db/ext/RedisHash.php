<?php

namespace esp\core\db\ext;


use \Redis;

class RedisHash
{
    private $redis;
    private $key;

    public function __construct(Redis $redis, string $key)
    {
        $this->redis = $redis;
        $this->key = $key;
    }


}