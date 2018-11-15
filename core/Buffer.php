<?php

namespace esp\core;


use esp\core\db\Mongodb;
use esp\core\db\Mysql;
use esp\core\db\Redis;
use esp\core\db\Yac;

class Buffer
{
    private static $_buffer;
    private static $_key;

    public static function _init(array $conf)
    {
        self::$_buffer = new Redis($conf + ['_from' => 'dispatcher'], 0);
        self::$_key = $conf['key'];
    }

    /**
     * @return Redis
     */
    private static function medium()
    {
        return self::$_buffer;
    }

    public static function flush()
    {
        return self::medium()->flush();
    }

    public static function get(string $key)
    {
        return self::medium()->get(self::$_key . $key);
    }

    public static function set(string $key, $value, int $ttl = null)
    {
        return self::medium()->set(self::$_key . $key, $value, $ttl);
    }

    public static function del(string $key)
    {
        return self::medium()->del(self::$_key . $key);
    }

    public static function hGet(string $key)
    {
        return self::medium()->hGet(self::$_key, $key);
    }

    public static function hSet(string $key, $value)
    {
        return self::medium()->hSet(self::$_key, $key, $value);
    }

    public static function publish(string $action, array $value)
    {
        return self::medium()->publish($action, $value);
    }


    //=========数据相关===========


    /**
     * @param string $tab
     * @return Yac
     */
    public static function Yac(string $tab = 'tmp')
    {
        static $yac = Array();
        if (!isset($yac[$tab])) {
            $yac[$tab] = new Yac($tab);
            Debug::relay("New Yac({$tab});");
        }
        return $yac[$tab];
    }

    /**
     * @param int $dbID
     * @param array $_conf
     * @return Redis
     */
    public static function Redis(int $dbID = 1, array $_conf = [])
    {
        static $redis = array();
        if (!isset($redis[$dbID])) {
            $conf = Config::get('database.redis');
            $redis[$dbID] = new Redis($_conf + $conf, $dbID);
            Debug::relay("create Redis({$dbID});");
        }
        return $redis[$dbID];
    }

    /**
     * 创建一个Mysql实例
     * @param int $tranID
     * @param array $_conf 如果要创建一个持久连接，则$_conf需要传入参数：persistent=true，
     * @return Mysql
     * @throws \Exception
     */
    public static function Mysql(int $tranID = 0, array $_conf = [])
    {
        static $mysql = Array();
        if (!isset($mysql[$tranID])) {
            $conf = Config::get('database.mysql');
            if (empty($conf)) {
                throw new \Exception('无法读取Mysql配置信息', 501);
            }
            $mysql[$tranID] = new Mysql($tranID, ($_conf + $conf));
            Debug::relay("New Mysql({$tranID});");
        }
        return $mysql[$tranID];
    }


    /**
     * @param string $db
     * @param array $_conf
     * @return Mongodb
     * @throws \Exception
     */
    public static function Mongodb(string $db = 'temp', array $_conf = [])
    {
        static $mongodb = Array();
        if (!isset($mongodb[$db])) {
            $conf = Config::get('database.mongodb');
            if (empty($conf)) {
                throw new \Exception('无法读取mongodb配置信息', 501);
            }
            $mongodb[$db] = new Mongodb($_conf + $conf, $db);
            Debug::relay("New Mongodb({$db});");
        }
        return $mongodb[$db];
    }


}