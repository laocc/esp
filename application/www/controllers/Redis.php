<?php

namespace www;


use esp\core\Config;
use esp\core\Controller;
use esp\core\db\Redis;

class RedisController extends Controller
{

    private function RedisHash($tabKey)
    {
        static $redis;
        if (is_null($redis)) {
            $_conf = ['pconnect' => 1];
            $conf = Config::get('database.redis');
            $redis = new Redis($_conf + $conf, 11);
        }
        return $redis->hash($tabKey);
    }

    public function indexAction()
    {
        $a = serialize('od');
        var_dump($a);
        $a = serialize('123');
        var_dump($a);
        $a = serialize(123);
        var_dump($a);
        $a = serialize(123.12);
        var_dump($a);
        $a = serialize(['d' => 'od']);
        var_dump($a);
        $a = serialize($this);
        var_dump($a);

        $Friend = $this->RedisHash("Friend_13205534482");
        $Friend->set('temp', time());

        $temp = $Friend->get('temp');
        var_dump($temp);
        $ord = $Friend->get('ordersss');
        var_dump($ord);

        exit;

    }
}