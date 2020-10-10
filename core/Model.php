<?php
declare(strict_types=1);

namespace esp\core;

use esp\core\db\Mongodb;
use esp\core\db\Mysql;
use esp\core\db\Redis;
use esp\core\db\Yac;
use esp\core\ext\EspError;
use esp\core\ext\Mysql as MysqlExt;
use esp\core\ext\Page as PageExt;

/**
 * Class Model
 * @package esp\core
 *
 * func_get_args()
 */
abstract class Model
{
    private $_table_fix = 'tab';    //表前缀
    private $__table = null;        //创建对象时，或明确指定当前模型的对应表名
    private $__pri = null;          //同上，对应主键名
    private $__cache = false;       //是否缓存，若此值被设置，则覆盖子对象的相关设置

    protected $_buffer;
    protected $_config;
    protected $_controller;

    private $_debug;
    private $_print_sql;

    //=========数据相关===========
    private $_Yac = array();
    private $_Mysql = array();
    private $_Mongodb = array();
    private $_Redis = array();

    private $_order = [];
    private $_count = null;
    protected $tableJoin = Array();
    protected $tableJoinCount = 0;
    protected $forceIndex = '';
    protected $groupKey;
    protected $selectKey = [];
    protected $columnKey = null;

    use MysqlExt, PageExt;

    public function __construct(...$param)
    {
        $this->_Yac = &$GLOBALS['_Yac'] ?? [];
        $this->_Mysql = &$GLOBALS['_Mysql'] ?? [];
        $this->_Mongodb = &$GLOBALS['_Mongodb'] ?? [];
        $this->_Redis = &$GLOBALS['_Redis'] ?? [];
        $this->_controller = &$GLOBALS['_Controller'];
        if (0) {
            $this->_Yac instanceof Yac and 1;
            $this->_Mysql instanceof Mysql and 1;
            $this->_Mongodb instanceof Mongodb and 1;
            $this->_Redis instanceof Redis and 1;
            $this->_controller instanceof Controller and 1;
        }
        $this->_config = $this->_controller->getConfig();
        $this->_buffer = $this->_controller->_buffer;

        if (method_exists($this, '_init') and is_callable([$this, '_init'])) {
            call_user_func_array([$this, '_init'], $param);
        }
    }

    public function __debugInfo()
    {
        return ['table' => $this->_table, 'id' => $this->_id];
    }

    public function __toString()
    {
        return json_encode(['table' => $this->_table, 'id' => $this->_id]);
    }

    /**
     * @return Controller
     */
    final public function Controller()
    {
        return $this->_controller;
    }

    /**
     * @param $value
     * @param array|null $pre
     * @return bool|Debug
     */
    final public function debug($value, array $pre = null)
    {
        if (_CLI) return false;
        if (0) $this->_debug instanceof Debug and 1;

        if (is_null($this->_debug)) {
            $this->_debug = $this->_controller->_debug;
//            $this->_debug = Debug::class();
        }
        if (is_null($value)) return $this->_debug;
        if (is_null($this->_debug)) return false;
        if (is_null($pre)) $pre = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
        return $this->_debug->relay($value, $pre);
    }

    /**
     * 设置并返回debug文件名
     * @param string|null $filename
     * @return string
     */
    final public function debug_file(string $filename = null)
    {
        $file = $this->debug(null)->filename($filename);
        return substr($file, strlen(_ROOT));
    }

    final protected function config(...$key)
    {
        return $this->_config->get(...$key);
    }


    /**
     * 发送通知信息
     * @param string $action
     * @param array $value
     * @return int
     */
    final public function publish(string $action, array $value)
    {
        return $this->_buffer->publish('order', $action, $value);
    }

    /**
     * 发送到队列
     * @param string $queKey
     * @param array $data
     * @return int
     *
     * 用下面方法读取
     * while ($data = $this->_redis->lPop($queKey)){...}
     */
    final public function queue(string $queKey, array $data)
    {
        return $this->_buffer->push('task', $data + ['_action' => $queKey]);
    }

    /**
     * @param bool $cache
     * @return $this
     */
    final public function cache(bool $cache = true)
    {
        $this->__cache = $cache;
        return $this;
    }

    //===================================================


    /**
     * 指定当前模型的表
     * 或，返回当前模型对应的表名
     * @param string|null $table
     * @return $this|null|string
     */
    final public function table(string $table = null)
    {
        if (!is_null($table)) {//指定表名
            $this->__table = $table;
            return $this;
        }
        //有指定表名
        if (!is_null($this->__table)) return $this->__table;

        if (isset($this->_table)) return $this->_table;

        preg_match('/(.+\\\)?(\w+)model$/i', get_class($this), $mac);
        if (!$mac) return null;

        return ($this->_table_fix . ucfirst($mac[2]));
    }

    /**
     * 检查执行结果，所有增删改查的结果都不会是字串，所以，如果是字串，则表示出错了
     * @param string $action
     * @param $data
     * @return null
     * @throws EspError
     */
    private function checkRunData(string $action, $data)
    {
        /**
         * 非字串，即json，退出
         */
        if (!is_string($data)) return null;
        $json = json_decode($data, true);

        /**
         * 当前ajax中
         */
//        if (($ajax = getenv('HTTP_X_REQUESTED_WITH')) and strtolower($ajax) === 'xmlhttprequest') {
//            $this->debug(null)->error($json);
//            return $json[2];
//        }

        throw new EspError($data);
    }

    /**
     * 增
     * @param array $data
     * @param bool $full 传入的数据是否已经是全部字段，如果不是，则要从表中拉取所有字段
     * @param bool $returnID 返回新ID,false时返回刚刚添加的数据
     * @param null $pre
     * @return int|null
     * @throws EspError
     */
    final public function insert(array $data, bool $full = false, bool $returnID = true, $pre = null)
    {
        $table = $this->table();
        if (!$table) throw new EspError('Unable to get table name');
        $mysql = $this->Mysql();
        $data = $full ? $data : $this->_FillField($mysql->dbName, $table, $data);
        $obj = $mysql->table($table);
        $val = $obj->insert($data);
        $ck = $this->checkRunData('insert', $val);
        if ($ck) return $ck;
        return $val;
    }

    final public function unset_cache(...$where)
    {
        $mysql = $this->Mysql();
        $table = $this->table();

        foreach ($where as $w) {
            if (is_numeric($w)) $w = [$this->PRI() => intval($w)];
            $kID = md5(serialize($w));
            $this->cache_del("{$mysql->dbName}.{$table}", "_id_{$kID}");
        }
        return $this;
    }

    /**
     * 删
     * @param $where
     * @param string $sql
     * @param null $pre
     * @return mixed
     * @throws EspError
     */
    final public function delete($where, &$sql = '', $pre = null)
    {
        $table = $this->table();
        if (!$table) throw new EspError('Unable to get table name');
        if (is_numeric($where)) {
            $where = [$this->PRI() => intval($where)];
        }
        if (is_null($pre)) $pre = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];

        $mysql = $this->Mysql();
        $val = $mysql->table($table)->where($where)->delete($sql, $pre);

        if ($this->__cache === true) {
            $kID = md5(serialize($where));
            $this->cache_del("{$mysql->dbName}.{$table}", "_id_{$kID}");
        }
        return $this->checkRunData('delete', $val) ?: $val;
    }


    /**
     * 改
     * @param $where
     * @param array $data
     * @param string $sql
     * @param null $pre
     * @return bool|db\ext\Result|null
     * @throws EspError
     */
    final public function update($where, array $data, &$sql = '', $pre = null)
    {
        $table = $this->table();
        if (!$table) throw new EspError('Unable to get table name');
        if (is_numeric($where)) {
            $where = [$this->PRI() => intval($where)];
        }
        if (empty($where)) throw new EspError('Update Where 禁止为空');
        $mysql = $this->Mysql();

        if ($this->__cache === true) {
            $kID = md5(serialize($where));
            $this->cache_del("{$mysql->dbName}.{$table}", "_id_{$kID}");
        }
        if (is_null($pre)) $pre = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];

        $val = $mysql->table($table)->where($where)->update($data, true, $sql, $pre);
        return $this->checkRunData('update', $val) ?: $val;
    }

    /**
     * 压缩字符串
     * @param string $string
     * @return string
     */
    final public function gz(string $string)
    {
        try {
            return gzcompress($string, 5);
        } catch (EspError $e) {
            return $e->getMessage();
        }
    }

    /**
     * 解压缩字符串
     * @param string $string
     * @return string
     */
    final public function ugz(string $string)
    {
        try {
            return gzuncompress($string);
        } catch (EspError $e) {
            return $e->getMessage();
        }
    }

    /**
     * 选择一条记录
     * @param $where
     * @param string|null $orderBy
     * @param string $sort
     * @param string $sql
     * @param null $pre
     * @return mixed|null
     */
    final public function get($where, string $orderBy = null, string $sort = 'asc', &$sql = '', $pre = null)
    {
        $mysql = $this->Mysql();
        $table = $this->table();
        if (!$table) throw new EspError('Unable to get table name');
        if (is_numeric($where)) {
            $where = [$this->PRI() => intval($where)];
        }
        if ($this->__cache === true) {
            $kID = md5(serialize($where));
            $data = $this->cache_get("{$mysql->dbName}.{$table}", "_id_{$kID}");
            $this->debug('getCache = ' . print_r(['table' => $table, 'where' => $where, 'key' => $kID, 'value' => !empty($data)], true));
            if (!empty($data)) {
                $this->clear_initial();
                return $data;
            }
        }

        $obj = $mysql->table($table);

        if (!empty($this->selectKey)) {
            foreach ($this->selectKey as $select) $obj->select(...$select);
        }
        if (!empty($this->tableJoin)) {
            foreach ($this->tableJoin as $join) $obj->join(...$join);
        }
        if ($this->forceIndex) $obj->force($this->forceIndex);
        if ($where) $obj->where($where);
        if ($this->groupKey) $obj->group($this->groupKey);

        if (!empty($this->_order)) {
            foreach ($this->_order as $k => $a) {
                $obj->order($a['key'], $a['sort'], $a['pro']);
            }
        }
        if ($orderBy === 'PRI') $orderBy = $this->PRI($table);
        if ($orderBy) {
            if (!in_array(strtolower($sort), ['asc', 'desc', 'rand'])) $sort = 'ASC';
            $obj->order($orderBy, $sort);
        }
        if (is_null($pre)) $pre = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
        $data = $obj->get(0, $sql, $pre);
        $c = $this->checkRunData('get', $data);
        if ($c) return $c;
        $val = $data->row();
        if ($val === false) $val = null;

        if ($this->__cache === true and isset($kID) and !empty($val)) {
            $this->cache_set("{$mysql->dbName}.{$table}", "_id_{$kID}", $val);
            $this->__cache = false;
        }
        $this->clear_initial();
        return $val;
    }

    /**
     * id in
     * @param array $ids
     * @param null $where
     * @param null $orderBy
     * @param string $sort
     * @param string $sql
     * @param null $pre
     * @return array|mixed
     * @throws EspError
     */
    final public function in(array $ids, $where = null, $orderBy = null, $sort = 'asc', &$sql = '', $pre = null)
    {
        if (empty($ids)) return [];
        $table = $this->table();
        $obj = $this->Mysql()->table($table);

        if (!empty($this->selectKey)) {
            foreach ($this->selectKey as $select) $obj->select(...$select);
        }
        if (!empty($this->tableJoin)) {
            foreach ($this->tableJoin as $join) $obj->join(...$join);
        }
        if ($orderBy === 'PRI') $orderBy = $this->PRI($table);
        if ($orderBy) {
            if (!in_array(strtolower($sort), ['asc', 'desc', 'rand'])) $sort = 'ASC';
            $obj->order($orderBy, $sort);
        }
        if (!empty($this->_order)) {
            foreach ($this->_order as $k => $a) {
                $obj->order($a['key'], $a['sort'], $a['pro']);
            }
        }
        if ($this->forceIndex) $obj->force($this->forceIndex);

        if (is_null($pre)) $pre = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
        $obj = $obj->where_in($this->PRI(), $ids);
        if ($where) $obj->where($where);
        $data = $obj->get(0, $sql, $pre);
        return $this->checkRunData('in', $data) ?: $data->rows();
    }


    final public function order($key, string $sort = 'asc', bool $addProtect = true)
    {
        if (is_array($key)) {
            foreach ($key as $ks) {
                if (!isset($ks[1])) $ks[1] = 'asc';
                if (!isset($ks[2])) $ks[2] = true;
                if (!in_array(strtolower($ks[1]), ['asc', 'desc', 'rand'])) $ks[1] = 'ASC';
                $this->_order[] = ['key' => $ks[0], 'sort' => $ks[1], 'pro' => $ks[2]];
            }
            return $this;
        }
        if (!in_array(strtolower($sort), ['asc', 'desc', 'rand'])) $sort = 'ASC';
        $this->_order[] = ['key' => $key, 'sort' => $sort, 'pro' => $addProtect];
        return $this;
    }

    final public function sql(&$sql)
    {
        $this->_print_sql = true;
        $sql = $this->_print_sql;
        return $this;
    }

    /**
     * @param array $where
     * @param string|null $orderBy
     * @param string $sort
     * @param int $limit
     * @return array
     */
    final public function all($where = [], string $orderBy = null, string $sort = 'asc', int $limit = 0, &$sql = '', $pre = null)
    {
        $table = $this->table();
        if (!$table) throw new EspError('Unable to get table name');
        $obj = $this->Mysql()->table($table)->prepare();
        if ($orderBy === 'PRI') {
            $orderBy = $this->PRI($table);
            if (isset($where['PRI'])) {
                $where[$orderBy] = $where['PRI'];
                unset($where['PRI']);
            }
        }

        if (!empty($this->selectKey)) {
            foreach ($this->selectKey as $select) $obj->select(...$select);
        }
        if (!empty($this->tableJoin)) {
            foreach ($this->tableJoin as $join) $obj->join(...$join);
        }
        if ($where) $obj->where($where);
        if ($this->groupKey) $obj->group($this->groupKey);
        if ($this->forceIndex) $obj->force($this->forceIndex);

        if (!empty($this->_order)) {
            foreach ($this->_order as $k => $a) {
                $obj->order($a['key'], $a['sort'], $a['pro']);
            }
        }
        if ($orderBy) {
            if (!in_array(strtolower($sort), ['asc', 'desc', 'rand'])) $sort = 'ASC';
            $obj->order($orderBy, $sort);
        }


        if (is_null($pre)) $pre = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
        if (is_bool($this->_count)) $obj->count($this->_count);
        $data = $obj->get($limit, $sql, $pre);
        $v = $this->checkRunData('all', $data);
        if ($v) return $v;

        $data = $data->rows(0, $this->columnKey);
        $this->clear_initial();
        return $data;
    }


    /**
     * 当前请求结果的总行数
     * @param bool $count
     * @return $this
     */
    final public function count(bool $count = true)
    {
        $this->_count = $count;
        return $this;
    }

    /**
     * @param null $where
     * @param null $orderBy
     * @param string $sort
     * @param string $sql
     * @param null $pre
     * @return array|mixed|null
     * @throws EspError
     */
    final public function list($where = null, $orderBy = null, string $sort = 'desc', &$sql = '', $pre = null)
    {
        $table = $this->table();
        if (!$table) throw new EspError('Unable to get table name');
        if ($this->pageSize === 0) $this->pageSet();
        $obj = $this->Mysql()->table($table);
        if (!empty($this->selectKey)) {
            foreach ($this->selectKey as $select) $obj->select(...$select);
        }
        if (!empty($this->tableJoin)) {
            foreach ($this->tableJoin as $join) $obj->join(...$join);
        }
        if ($this->forceIndex) $obj->force($this->forceIndex);

        if ($where) $obj->where($where);
        if ($this->groupKey) $obj->group($this->groupKey);
        if (!empty($this->_order)) {
            foreach ($this->_order as $k => $a) {
                $obj->order($a['key'], $a['sort'], $a['pro']);
            }
        }

        if ($orderBy === 'PRI') $orderBy = $this->PRI($table);
        if ($orderBy) {
            if (!in_array(strtolower($sort), ['asc', 'desc', 'rand'])) $sort = 'ASC';
            $obj->order($orderBy, $sort);
        }

        if (is_null($this->_count)) $this->_count = true;
        $obj->count($this->_count);
        if (is_null($pre)) $pre = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
        $data = $obj->limit($this->pageSize, $this->pageSkip)->get(0, $sql, $pre);
        $v = $this->checkRunData('list', $data);
        if ($v) return $v;
        $this->dataCount = $data->count();
        $this->clear_initial();
        return $data->rows();
    }

    /**
     * 清除自身的一些对象变量
     */
    final public function clear_initial()
    {
        $this->__table = null;
        $this->columnKey = null;
        $this->groupKey
            = $this->__pri
            = $this->forceIndex
            = '';
        $this->tableJoin = [];
        $this->selectKey = [];
    }

    /**
     * @param string $string
     * @return mixed
     */
    final public function quote(string $string)
    {
        return $this->Mysql()->quote($string);
    }


    final public function join(...$data)
    {
        if (empty($data)) {
            $this->tableJoin = Array();
            return $this;
        }
        $this->tableJoin[] = $data;
        return $this;
    }

    final public function group($groupKey, bool $only = false)
    {
        if ($only) $this->columnKey = 0;
        $this->groupKey = $groupKey;
        return $this;
    }

    /**
     * 返回第x列数据
     * @param int $field
     * @return $this
     */
    final public function column(int $field = 0)
    {
        $this->columnKey = $field;
        return $this;
    }

    /**
     * 强制从索引中读取，多索引用逗号连接
     * @param $index
     * @return $this
     */
    final public function force($index)
    {
        if (empty($index)) return $this;
        if (is_array($index)) {
            $this->forceIndex = implode(',', $index);
        } else {
            $this->forceIndex = $index;
        }
        return $this;
    }

    /**
     * 强制从索引中读取
     * @param string $index
     * @return $this
     */
    final public function index($index)
    {
        if (empty($index)) return $this;
        if (is_array($index)) {
            $this->forceIndex = implode(',', $index);
        } else {
            $this->forceIndex = $index;
        }
        return $this;
    }

    /**
     * @param $select
     * @param bool $add_identifier
     * @return $this
     * @throws EspError
     */
    final public function select($select, $add_identifier = true)
    {
        if (is_int($add_identifier)) {
            //当$add_identifier是整数时，表示返回第x列数据
            $this->columnKey = $add_identifier;
            $this->selectKey[] = [$select, true];

        } else if ($select and ($select[0] === '~' or $select[0] === '!') and $add_identifier) {
            //不含选择，只适合从单表取数据
            $field = $this->field();
            $seKey = array_column($field, 'COLUMN_NAME');
            $kill = explode(',', substr($select, 1));
            $this->selectKey[] = [implode(',', array_diff($seKey, $kill)), $add_identifier];

        } else {
            $this->selectKey[] = [$select, $add_identifier];
        }
        return $this;
    }


    /**
     * @param string $tab
     * @return Yac
     */
    final public function Yac(string $tab = 'tmp')
    {
        if (!isset($this->_Yac[$tab])) {
            $this->_Yac[$tab] = new Yac($tab);
            $pre = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];

            $this->debug("New Yac({$tab});", $pre);
        }
        return $this->_Yac[$tab];
    }

    /**
     * 创建一个Mysql实例
     * @param int $tranID
     * @param array $_conf 如果要创建一个持久连接，则$_conf需要传入参数：persistent=true，
     * @return Mysql
     */
    final public function Mysql(int $tranID = 0, array $_conf = []): Mysql
    {
        $branchName = $this->_branch ?? 'auto';

        if ($tranID === 1) {
            if (!isset($GLOBALS['_tranID'])) $GLOBALS['_tranID'] = 0;
            $tranID = $GLOBALS['_tranID']++;
        }

        if (isset($this->_Mysql[$branchName][$tranID])) {
            return $this->_Mysql[$branchName][$tranID];
        }

        $conf = $this->_config->get('database.mysql');
        if (isset($this->_branch) and !empty($this->_branch)) {
            $_branch = $this->_config->get($this->_branch);
            if (empty($_branch) or !is_array($_branch)) {
                throw new EspError("Model中`_branch`指向内容非Mysql配置信息", 501);
            }
            $conf = $_branch + $conf;
        }
        if (isset($this->_conf) and is_array($this->_conf) and !empty($this->_conf)) {
            $conf = $this->_conf + $conf;
        }

        if (empty($conf) or !is_array($conf)) {
            throw new EspError("`Database.Mysql`配置信息错误", 501);
        }
//            if (defined('_DISABLED_PARAM') and _DISABLED_PARAM) $_conf['param'] = false;
        $this->_Mysql[$branchName][$tranID] = new Mysql($tranID, ($_conf + $conf));
        $pre = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
        $this->debug("New Mysql({$branchName}-{$tranID});", $pre);
        return $this->_Mysql[$branchName][$tranID];
    }


    /**
     * @param string $db
     * @param array $_conf
     * @return Mongodb
     */
    final public function Mongodb(string $db = 'temp', array $_conf = [])
    {
        if (!isset($this->_Mongodb[$db])) {
            $conf = $this->_config->get('database.mongodb');
            if (empty($conf)) {
                throw new EspError('无法读取mongodb配置信息', 501);
            }
            $this->_Mongodb[$db] = new Mongodb($_conf + $conf, $db);
            $pre = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];

            $this->debug("New Mongodb({$db});", $pre);
        }
        return $this->_Mongodb[$db];
    }

    /**
     * @param array $_conf
     * @return Redis
     */
    final public function Redis(array $_conf = []): Redis
    {
        $conf = $this->_config->get('database.redis');
        $conf = $_conf + $conf + ['db' => 1];
        if (!isset($this->_Redis[$conf['db']])) {
            $this->_Redis[$conf['db']] = new Redis($conf);
            $pre = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
            $this->debug("create Redis({$conf['db']});", $pre);
        }
        return $this->_Redis[$conf['db']];
    }


    /**
     * 缓存哈希
     * @param string $table
     * @param string|null $key
     * @param string|null $value
     * @return mixed
     */
    final public function Hash(string $table, string $key = null, string $value = null)
    {
        $hash = $this->Redis()->hash($table);
        if (is_null($key)) return $hash;
        if (is_null($value))
            return $hash->hGet($key);
        else
            return $hash->hSet($key, $value);
    }


    /**
     * @param string $hashTable
     * @param mixed ...$key
     * @return int
     */
    final public function cache_delete(string $hashTable, ...$key)
    {
        return $this->_buffer->hash($hashTable)->del(...$key);
    }

    /**
     * @param string $hashTable
     * @param mixed ...$key
     * @return int
     */
    final public function cache_del(string $hashTable, ...$key)
    {
        return $this->_buffer->hash($hashTable)->del(...$key);
    }


    /**
     * @param string $hashTable
     * @param string $key
     * @param array $value
     * @return int
     */
    final public function cache_set(string $hashTable, string $key, array $value)
    {
        return $this->_buffer->hash($hashTable)->set($key, $value);
    }

    /**
     * @param string $hashTable
     * @param string $key
     * @return array|int|string
     */
    final public function cache_get(string $hashTable, string $key)
    {
        return $this->_buffer->hash($hashTable)->get($key);
    }


    /**
     * 注册关门后操作
     * @param callable $fun
     * @param mixed ...$parameter
     */
    final public function shutdown(callable $fun, ...$parameter)
    {
        register_shutdown_function($fun, ...$parameter);
    }

}