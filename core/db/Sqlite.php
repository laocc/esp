<?php

namespace esp\core\db;

use PDO;
use esp\core\Controller;
use esp\core\Debug;
use function esp\helper\mk_dir;

final class Sqlite
{
    private $conf;
    private $db;
    /**
     * @var $_debug Debug
     */
    private $_debug;
    private $table;

    public function __construct(Controller $controller, array $conf)
    {
        $this->conf = $conf;
        if (!isset($this->conf['db'])) throw new \Error('Sqlite库文件未指定');
        $this->_debug = &$controller->_debug;

        if (!file_exists($this->conf['db'])) {
            mk_dir($this->conf['db']);
            $fp = fopen($this->conf['db'], 'w');
            if (!$fp) throw new \Error('Sqlite创建失败');
            fclose($fp);
        }
        $this->db = new PDO("sqlite:{$this->conf['db']}");
    }

    public function __destruct()
    {
        $this->db = null;
    }

    private function debug($value): void
    {
        $this->_debug->relay($value);
    }

    public function table(string $table): Sqlite
    {
        $this->table = $table;
        return $this;
    }

    public function create(array $data)
    {
        $filed = [];
        foreach ($data as $k => $type) {
            $filed[] = "{$k} {$type}";
        }
        return $this->db->exec('CREATE TABLE' . "{$this->table}(" . implode(',', $filed) . ')');
    }

    public function exec(string $sql)
    {
        return $this->db->exec($sql);
    }

    public function desc()
    {

    }

    public function where()
    {

    }

    public function get()
    {

    }

    public function list()
    {

    }

    public function all()
    {

    }

    public function update()
    {

    }

    public function delete()
    {

    }

    public function insert()
    {

    }

    function transaction(): bool
    {
//        sem_acquire($this->sem);
        return $this->db->beginTransaction();
    }

    function commit(): bool
    {
        $success = $this->db->commit();
//        sem_release($this->sem);
        return $success;
    }

    function rollback(): bool
    {
        $success = $this->db->rollBack();
//        sem_release($this->sem);
        return $success;
    }


}