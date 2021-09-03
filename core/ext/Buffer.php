<?php
declare(strict_types=1);

namespace esp\core\ext;


use esp\core\db\Redis;

final class Buffer
{
    private $db;
    private $table;
    private $redis;

    public function __construct(Redis $redis, string $db)
    {
        $this->redis = $redis;
        $this->db = "mysql_buffer_{$db}";
    }

    public function table(string $table)
    {
        $this->table = $table;
        return $this;
    }

    /**
     * @param array $where
     * @return array|int|string
     */
    public function read(array $where)
    {
        $key = array_keys($where)[0] ?? null;
        if (!$key) return null;
        $mdKey = md5($this->table . $key . var_export($where[$key], true));
        return $this->redis->hash($this->db)->get($mdKey);
    }

    /**
     * @param array $where
     * @param $data
     * @return int
     */
    public function save(array $where, $data)
    {
        $key = array_keys($where)[0] ?? null;
        if (!$key) return false;
        $mdKey = md5($this->table . $key . var_export($where[$key], true));
        return $this->redis->hash($this->db)->set($mdKey, $data);
    }


    /**
     * key存在于where，即删除符合该key的值
     *
     * @param array $where
     * @return bool|int
     */
    public function delete(array $where)
    {
        $mdKey = [];
        foreach ($where as $key => $val) {
            if (is_array($val)) {
                foreach ($val as $v) {
                    $mdKey[] = md5($this->table . $key . var_export($v, true));
                }
            } else {
                $mdKey[] = md5($this->table . $key . var_export($val, true));
            }
        }
        if (!empty($mdKey)) return $this->redis->hash($this->db)->del(...$mdKey);
        return 0;
    }

}