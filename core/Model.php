<?php

namespace esp\core;

use esp\core\db\Mongodb;
use esp\core\db\Mysql;
use esp\core\db\Redis;
use esp\core\db\Yac;

/**
 * Class Model
 * @package esp\core
 *
 * func_get_args()
 */
class Model
{
    private $_table_fix = 'tab';    //表前缀
    private $_page_key = 'page';       //分页，页码键名，可以任意命名，只要不和常用的别的键冲突就可以
    private $__table = null;        //创建对象时，或明确指定当前模型的对应表名
    private $__pri = null;          //同上，对应主键名
    private $__cache = false;       //是否缓存，若此值被设置，则覆盖子对象的相关设置
    private $_controller;

    /**
     * Model constructor.
     * @param bool|null $cache
     */
    final public function __construct(bool $cache = null)
    {
        $this->_controller = $GLOBALS['_Controller'];
        if (false) $this->_controller = new Controller(null);//无作用，只是为了让下面有些函数调用能跟踪到而已

        if (is_bool($cache)) $this->__cache = $cache;

        if (method_exists($this, '_init') and is_callable([$this, '_init'])) {
            call_user_func_array([$this, '_init'], []);
        }
    }

    /**
     * 记录错误
     * @param string ...$action
     * @return mixed
     */
    final public function error(string $action)
    {
        $pre = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
        $this->_controller->error($action, $pre);
        return true;
    }

    /**
     * @param array ...$action
     * @return Debug|mixed
     */
    final public function debug(...$action)
    {
        if (empty($action)) return $this->_controller->_debug;
        $pre = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
        if (count($action) === 1) $action = $action[0];
        return $this->_controller->debug($action, $pre);
    }

    /**
     * 设置并返回debug文件名
     * @param string|null $filename
     * @return string
     */
    final public function debug_file(string $filename = null)
    {
        $file = $this->_controller->_debug->filename($filename);
        return substr($file, strlen(_ROOT));
    }

    /**
     * 发送通知信息
     * @param string $key
     * @param array $value
     * @return int
     */
    final public function publish(string $key, array $value)
    {
        return $this->_controller->publish($key, $value);
    }

    /**
     * @param bool $cache
     * @return $this
     */
    final public function cache(bool $cache = false)
    {
        $this->__cache = $cache;
        return $this;
    }

    //===================================================

    /**
     * @return array|null
     * *************************** 17. row ***************************
     * TABLE_CATALOG: def
     * TABLE_SCHEMA: dbPlatform
     * TABLE_NAME: tabWithdrawals
     * TABLE_TYPE: BASE TABLE
     * ENGINE: InnoDB
     * VERSION: 10
     * ROW_FORMAT: Dynamic
     * TABLE_ROWS: 16
     * AVG_ROW_LENGTH: 1024
     * DATA_LENGTH: 16384
     * MAX_DATA_LENGTH: 0
     * INDEX_LENGTH: 49152
     * DATA_FREE: 0
     * AUTO_INCREMENT: 17
     * CREATE_TIME: 2018-08-30 11:23:08
     * UPDATE_TIME: 2018-08-30 18:59:16
     * CHECK_TIME: NULL
     * TABLE_COLLATION: utf8_general_ci
     * CHECKSUM: NULL
     * CREATE_OPTIONS:
     * TABLE_COMMENT: 提现表
     */
    final public function tables($field = null)
    {
        $mysql = $this->_controller->Mysql();
        $val = $mysql->table('INFORMATION_SCHEMA.TABLES')->select('*')->where(['TABLE_SCHEMA' => $mysql->dbName])->get()->rows();
        if (empty($val)) return null;
        if (!is_null($field)) return array_column($val, $field);
        return $val;
    }

    /**
     * 当前模型对应的表名
     * @return Model|string
     */
    final public function table(string $table = null)
    {
        if (!is_null($table)) {
            $this->__table = $table;
            return $this;
        }
        if (!is_null($this->__table)) return $this->__table;

        static $val;
        if (!is_null($val)) return $val;
        if (isset($this->_table)) return $this->_table;

        preg_match('/(.+\\\)?(\w+)model$/i', get_class($this), $mac);
        if (!$mac) return null;

        return $val = ($this->_table_fix . ucfirst($mac[2]));
    }

    /**
     * select * from INFORMATION_SCHEMA.Columns where table_name='tabAdmin' and table_schema='dbPayCenter';
     * 当前模型表对应的主键字段名，即自增字段
     * @return null
     * @throws \Exception
     *************************** 1. row ***************************
     * TABLE_CATALOG: def
     * TABLE_SCHEMA: dbPlatform
     * TABLE_NAME: tabAdmin
     * COLUMN_NAME: adminID
     * ORDINAL_POSITION: 1
     * COLUMN_DEFAULT: NULL
     * IS_NULLABLE: NO
     * DATA_TYPE: int
     * CHARACTER_MAXIMUM_LENGTH: NULL
     * CHARACTER_OCTET_LENGTH: NULL
     * NUMERIC_PRECISION: 10
     * NUMERIC_SCALE: 0
     * DATETIME_PRECISION: NULL
     * CHARACTER_SET_NAME: NULL
     * COLLATION_NAME: NULL
     * COLUMN_TYPE: int(10) unsigned
     * COLUMN_KEY: PRI
     * EXTRA: auto_increment
     * PRIVILEGES: select,insert,update,references
     * COLUMN_COMMENT: ID
     * GENERATION_EXPRESSION:
     */
    final public function PRI($table = null)
    {
        if (is_null($table)) {
            $table = $this->table();
            if (!is_null($this->__pri)) return $this->__pri;
            if (isset($this->_id)) return $this->_id;
        }
        if (!$table) throw new \Exception('Unable to get table name');
        $val = $this->_controller->Mysql()->table('INFORMATION_SCHEMA.Columns')
            ->select('COLUMN_NAME')
            ->where(['table_name' => $table, 'EXTRA' => 'auto_increment'])
            ->get()->row();
        if (empty($val)) return null;
        return $val['COLUMN_NAME'];
    }

    /**
     * 设置自增ID起始值
     * @param string $table
     * @param int $id
     * @return bool|db\ext\Result|null
     */
    final public function increment(string $table, int $id = 1)
    {
        //TRUNCATE TABLE dbAdmin;
        //alter table users AUTO_INCREMENT=10000;
        return $this->_controller->Mysql()->query("alter table {$table} AUTO_INCREMENT={$id}");
    }

    /**
     * 列出表字段
     * @return array
     * @throws \Exception
     */
    final public function field($table = null)
    {
        $table = $table ?: $this->table();
        if (!$table) throw new \Exception('Unable to get table name');
        $mysql = $this->_controller->Mysql();
        $val = $mysql->table('INFORMATION_SCHEMA.Columns')
            ->where(['table_schema' => $mysql->dbName, 'table_name' => $table])
            ->get()->rows();
        if (empty($val)) throw new \Exception("Table '{$table}' doesn't exist");
        return $val;
    }

    /**
     * @param array ...$key
     * @return $this
     */
    final public function cache_delete(...$key)
    {
        $table = $this->table();
        $buffer = $this->_controller->_buffer;
        foreach ($key as $i => $k) {
            $buffer->hDel("{$buffer->key}_{$table}", "_key:{$k}", "_id:{$k}");
        }
        return $this;
    }


    /**
     * @param string $table
     * @param string $key
     * @param array $value
     * @return int
     */
    final private function cache_set(string $table, string $key, array $value)
    {
        return $this->_controller->_buffer->hSet("{$this->_controller->_buffer->key}_{$table}", "{$key}", serialize($value));
    }

    /**
     * @param string $table
     * @param string $key
     * @return bool|mixed|string
     */
    final private function cache_get(string $table, string $key)
    {
        $data = $this->_controller->_buffer->hGet("{$this->_controller->_buffer->key}_{$table}", $key);
        return unserialize($data);
    }


    /**
     * 列出所有字段的名称
     * @return array
     * @throws \Exception
     */
    final public function title()
    {
        $table = $this->table();
        $data = $this->cache_get($table, '_title');
        if (!empty($data)) return $data;

        if (!$table) throw new \Exception('Unable to get table name');
        $val = $this->_controller->Mysql()->table('INFORMATION_SCHEMA.Columns')->select('COLUMN_NAME as field,COLUMN_COMMENT as title')->where(['table_name' => $table])->get()->rows();
        if (empty($val)) throw new \Exception("Table '{$table}' doesn't exist");
        $this->cache_set($table, '_title', $val);
        return $val;
    }

    /**
     * 新增行时，填充字段
     * @param string $table
     * @param array $data
     * @return array
     * @throws \Exception
     */
    final private function _FillField(string $table, array $data)
    {
        $field = $this->cache_get($table, '_field');
        if (empty($field)) {
            $field = $this->field($table);
            $this->cache_set($table, '_field', $field);
        }

        foreach ($field as $i => $rs) {
            if ($rs['EXTRA'] === 'auto_increment') continue;//自增字段
            if (isset($data[$rs['COLUMN_NAME']])) continue;//字段有值
            $string = array('CHAR', 'VARCHAR', 'TINYBLOB', 'TINYTEXT', 'BLOB', 'TEXT', 'MEDIUMBLOB', 'MEDIUMTEXT', 'LONGBLOB', 'LONGTEXT');
            $number = array('INT', 'TINYINT', 'SMALLINT', 'MEDIUMINT', 'INTEGER', 'BIGINT', 'FLOAT', 'DOUBLE', 'DECIMAL');
            if (in_array(strtoupper($rs['DATA_TYPE']), $number)) $data[$rs['COLUMN_NAME']] = 0;//数值型
            elseif (in_array(strtoupper($rs['DATA_TYPE']), $string)) $data[$rs['COLUMN_NAME']] = '';//文本型
            else $data[$rs['COLUMN_NAME']] = null;//其他类型，均用null填充，主要是日期和时间类型
        }
        return $data;
    }


    /**
     * 增
     * @param array $data
     * @param bool $full 传入的数据是否已经是全部字段，如果不是，则要从表中拉取所有字段
     * @return int
     * @throws \Exception
     */
    final public function insert(array $data, bool $full = false)
    {
        $table = $this->table();
        if (!$table) throw new \Exception('Unable to get table name');
        return $this->_controller->Mysql()->table($table)->insert($full ? $data : $this->_FillField($table, $data));
    }

    /**
     * 删
     * @param $where
     * @return mixed
     * @throws \Exception
     */
    final public function delete($where)
    {
        $table = $this->table();
        if (!$table) throw new \Exception('Unable to get table name');
        if (is_numeric($where)) {
            $where = [$this->PRI() => intval($where)];
        }
        return $this->_controller->Mysql()->table($table)->where($where)->delete();
    }


    /**
     * 改
     * @param $where
     * @param array $data
     * @return bool|db\ext\Result|null
     * @throws \Exception
     */
    final public function update($where, array $data)
    {
        $table = $this->table();
        if (!$table) throw new \Exception('Unable to get table name');
        if (is_numeric($where)) {
            $where = [$this->PRI() => intval($where)];
        }
        return $this->_controller->Mysql()->table($table)->where($where)->update($data);
    }


    /**
     * 选择一条记录
     * @param $where
     * @param string|null $orderBy
     * @param string $sort
     * @return array|bool
     * @throws \Exception
     */
    final public function get($where, string $orderBy = null, string $sort = 'asc')
    {
        $table = $this->table();
        if (!$table) throw new \Exception('Unable to get table name');
        if (is_numeric($where)) {
            if ($this->__cache === true) {
                $kID = $where;
                $data = $this->cache_get($table, "_id:{$kID}");
                if (!empty($data)) return $data;
            }
            $where = [$this->PRI() => intval($where)];
        }
        $obj = $this->_controller->Mysql()->table($table);

        if ($this->selectKey) $obj->select(...$this->selectKey);
        if (!empty($this->tableJoin)) {
            foreach ($this->tableJoin as $join) {
                $obj->join(...$join);
            }
        }
        if ($where) $obj->where($where);
        if ($this->groupKey) $obj->group($this->groupKey);

        if ($orderBy) {
            $sort = (strtolower($sort) === 'asc') ? 'asc' : 'desc';
            $obj->order($orderBy, $sort);
        }

        $data = $obj->get()->row();
        if ($this->__cache === true and isset($kID) and !empty($data)) {
            $this->cache_set($table, "_id:{$kID}", $data);
        }
        return $data;
    }

    /**
     * @param $keyValue
     * @param bool $fromCache
     * @return array|bool
     * @throws \Exception
     */
    final public function read($keyValue, bool $fromCache = true)
    {
        if (!isset($this->_key) or empty($this->_key)) throw new \Exception('Model 未定义或未指定 _key');

        $table = $this->table();

        if ($fromCache and $this->__cache === true) {
            $data = $this->cache_get($table, "_key:{$keyValue}");
            if (!empty($data)) return $data;
        }

        $val = $this->_controller->Mysql()->table($table)->where($this->_key, $keyValue)->get()->row();
        if ($fromCache and $this->__cache === true and !empty($val)) {
            $this->cache_set($table, "_key:{$keyValue}", $val);
        }

        return $val;
    }

    /**
     * id in
     * @param array $ids
     * @param null $where
     * @return array
     */
    final public function in(array $ids, $where = null)
    {
        if (empty($ids)) return [];
        $table = $this->table();
        $val = $this->_controller->Mysql()->table($table)
            ->where_in($this->PRI(), $ids);
        if ($where) $val->where($where);
        return $val->get()->rows();
    }

    /**
     * @param array $where
     * @param string|null $orderBy
     * @param string $sort
     * @param int $limit
     * @return array
     * @throws \Exception
     */
    final public function all($where = [], string $orderBy = null, string $sort = 'asc', int $limit = 0)
    {
        $table = $this->table();
        if (!$table) throw new \Exception('Unable to get table name');
        $obj = $this->_controller->Mysql()->table($table);

        if ($this->selectKey) $obj->select(...$this->selectKey);
        if (!empty($this->tableJoin)) {
            foreach ($this->tableJoin as $join) {
                $obj->join(...$join);
            }
        }
        if ($where) $obj->where($where);
        if ($this->groupKey) $obj->group($this->groupKey);

        if ($orderBy) {
            $sort = (strtolower($sort) === 'asc') ? 'asc' : 'desc';
            $obj->order($orderBy, $sort);
        }
        $data = $obj->get($limit);

//        if (!is_subclass_of($data, Result::class)) return $data;

        return $data->rows();
    }


    /**
     * @param null $where
     * @param string $ascDesc
     * @return array
     * @throws \Exception
     */
    final public function list($where = null, $ascKey = null, string $ascDesc = 'desc')
    {
        $table = $this->table();
        if (!$table) throw new \Exception('Unable to get table name');
        if ($this->pageSize === 0) $this->pageSet();
        $rs = $this->_controller->Mysql()->table($table);
        if ($this->selectKey) $rs->select(...$this->selectKey);
        if (!empty($this->tableJoin)) {
            foreach ($this->tableJoin as $join) {
                $rs->join(...$join);
            }
        }
        if ($where) $rs->where($where);
        if ($this->groupKey) $rs->group($this->groupKey);
        if (is_array($ascKey)) {
            list($key, $asc) = $ascKey;
        } elseif (is_null($ascKey)) {
            list($key, $asc) = [$this->PRI(), $ascDesc];
        } else {
            if (in_array(strtolower($ascKey), ['asc', 'desc'])) {
                list($ascKey, $ascDesc) = [$this->PRI(), $ascKey];
            }
            list($key, $asc) = [$ascKey, $ascDesc];
        }
        $val = $rs->limit($this->pageSize, $this->pageSkip)->order($key, $asc)->get();
        $this->pageCount = $val->count();
        return $val->rows();
    }


    /**
     * @param string $string
     * @return mixed
     * @throws \Exception
     */
    final public function quote(string $string)
    {
        return $this->_controller->Mysql()->quote($string);
    }

    final public function pageCount()
    {
        return $this->pageCount;
    }

    protected $pageCount = 0;
    protected $pageSize = 0;
    protected $pageIndex = 1;
    protected $pageSkip = 0;
    protected $tableJoin = Array();
    protected $tableJoinCount = 0;
    protected $groupKey = null;
    protected $selectKey = null;

    final public function join(...$data)
    {
        if (empty($data)) {
            $this->tableJoin = Array();
            return $this;
        }
        $this->tableJoin[] = $data;
        return $this;
    }

    final public function group(string $groupKey)
    {
        $this->groupKey = $groupKey;
        return $this;
    }

    final public function select($select, $add_identifier = true)
    {
        $this->selectKey = [$select, $add_identifier];
        return $this;
    }

    final public function pageKey(string $key)
    {
        $this->_page_key = $key;
        return $this;
    }

    final public function pageSet(int $size = 10, int $index = 0)
    {
        $this->pageIndex = $index ?: Input::get($this->_page_key, 1);
        if ($this->pageIndex < 1) $this->pageIndex = 1;
        $this->pageSize = $size;
        $this->pageSkip = ($this->pageIndex - 1) * $this->pageSize;
        if ($this->pageSkip < 0) $this->pageSkip = 0;
        return $this;
    }


    /**
     * 组合分页连接
     * @param string $class
     * @return string
     */
    final public function pageGet(string $class = 'pageForm'): string
    {
        $info = [
            'recode' => $this->pageCount,//记录数
            'size' => max(2, $this->pageSize),//每页数量
            'index' => $this->pageIndex,//当前页码
        ];

        $key = $this->_page_key;  //URL中标识页码的键名，可以任意指定，但不要和网站其他可能的参数重名
        $_show = 5;             //页码显示为当前页前后页数

        $info['index'] = $info['index'] ?: Input::get($key, 1);//当前页码

        $info['last'] = (int)($info['recode'] % $info['size']);//最后一页数
        $info['page'] = (int)($info['recode'] / $info['size']);
        $info['page'] += !!$info['last'] ? 1 : 0;//总页数

        $info['prev'] = $info['index'] - 1;//上一页
        $info['next'] = $info['index'] + 1;//下一页
        $info['prev'] < 1 and $info['prev'] = 1;
        if ($info['next'] > $info['page']) $info['next'] = $info['page'];

        $link = Array();
        $link[] = "<form method='get' action='?' autocomplete='off' class='{$class}'><ul>";
        $link[] = "<li><a href='?{$key}=1&[QueryString]' class='first'>&lt;&lt;</a></li>";
        $link[] = "<li><a href='?{$key}={$info['prev']}&[QueryString]' class='prev'>&lt;</a></li>";

        $get = $_GET;
        unset($get[$key]);
        foreach ($get as $_k => $_v) {
            $link[] = "<input type='hidden' name='{$_k}' value='{$_v}'>";
        }

        $page = Array();

        //页面导航的起止点
        $star = $info['index'] - $_show;
        $star < 1 and $star = 1;
        $stop = $info['index'] + $_show;
        $stop > $info['page'] and $stop = $info['page'];

        if ($star >= $_show) {
            $page[] = "<li class='omit'><a>...</a></li>";
        }

        for ($i = $star; $i <= $stop; $i++) {
            if ($i == $info['index'])
                $page[] = "<li class='active'><a>{$i}</a></li>";
            else
                $page[] = "<li class='link'><a href='?{$key}={$i}&[QueryString]'>{$i}</a></li>";
        }

        if ($stop <= ($info['page'] - $_show)) {
            $page[] = "<li class='omit'><a>...</a></li>";
        }

        $link[] = implode($page);
        $link[] = "<li><a href='?{$key}={$info['next']}&[QueryString]' class='next'>&gt;</a></li>";
        $link[] = "<li><a href='?{$key}={$info['page']}&[QueryString]' class='last'>&gt;&gt;</a></li>";
        $link[] = "<li class='total'>第{$info['index']}/{$info['page']}页 每页{$info['size']}条/共{$info['recode']}条</li>";
        $link[] = "<li class='submit'><input type='tel' onclick='this.select();' name='{$key}' id='pageIndex' value='{$info['index']}'><input id='pageGo' type='submit' value='Go'></li>";

        $link[] = "</ul></form>";
        $get['_'] = mt_rand();
        return str_replace(['[QueryString]'], [http_build_query($get)], implode("", $link));
    }


    /**
     * 创建一个Mysql实例
     * @param int $tranID 事务ID
     * @param array $_conf
     * @return Mysql
     * 如果要创建一个持久连接，则$_conf需要传入参数：persistent=true，
     */
    public function Mysql(int $tranID = 0, array $_conf = [])
    {
        return $this->_controller->Mysql($tranID, $_conf);
    }

    /**
     * @param string $db
     * @param array $conf
     * @return Mongodb
     */
    public function Mongodb(string $db = 'temp', array $conf = [])
    {
        return $this->_controller->Mongodb($db, $conf);
    }

    /**
     * @param string $tab
     * @return Yac
     */
    public function Yac(string $tab = 'tmp')
    {
        return $this->_controller->Yac($tab);
    }


    /**
     * @param int $db
     * @return Redis
     */
    public function Redis(int $db = 1)
    {
        return $this->_controller->Redis($db);
    }

    /**
     * 缓存哈希
     * @param string $key
     * @param string|null $value
     * @return int|string
     */
    public function Hash(int $db = 1, string $key, string $value = null)
    {
        $hash = $this->_controller->Redis($db)->hash('CacheHash');
        if (is_null($value))
            return $hash->hGet($key);
        else
            return $hash->hSet($key, $value);
    }

}