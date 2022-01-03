<?php


namespace esp\core;

use esp\mysql\Mysql;

/**
 * @method void insert(...$value)
 * @method void delete(...$value)
 * @method void update(...$value)
 *
 * @method Array get(...$value)
 * @method Array all(...$value)
 *
 * @method Mysql paging(...$value)
 * @method Mysql select(...$value)
 * @method Mysql join(...$value)
 *
 * Class ModelPdo
 * @package esp\core
 */
abstract class ModelPdo extends Library
{
    public $_table = null;  //Model对应表名
    public $_id = null;      //表主键
    public $_fix = 'tab';    //表前缀

    /**
     * @var $_mysqlBridge Mysql
     */
    private $_mysqlBridge;

    /**
     * 指定当前模型的表
     * 或，返回当前模型对应的表名
     * @param string|null $table
     * @param string|null $pri
     * @return Mysql
     */
    final public function table(string $table = null, string $pri = null)
    {
        if (is_null($table)) {
            if (isset($this->_table)) {
                $table = $this->_table;
            } else {
                preg_match('/(.+\\\)?(\w+)Model$/i', get_class($this), $mac);
                if (!$mac) throw new \Error('未指定表名');
                if (is_null($pri)) $pri = $this->_fix;
                $table = ($pri . ucfirst($mac[2]));
            }
        }

        if (is_null($this->_mysqlBridge)) {
            $this->_mysqlBridge = new Mysql($table);

            $conf = $this->_controller->_config->get('database.mysql');
            $this->_mysqlBridge->config($conf);
            $this->_mysqlBridge->Redis($this->_controller->_config->_Redis->redis);

        } else {
            $this->_mysqlBridge->table($table);
        }

        return $this->_mysqlBridge;
    }


    /**
     * @param $name
     * @param $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        return $this->table()->{$name}(...$arguments);
    }

    /**
     * @param string|null $table
     * @return Mysql
     */
    public function mysql(string $table = null): Mysql
    {
        return $this->table($table);
    }

}