<?php
declare(strict_types=1);

namespace esp\core;

use esp\core\db\Mongodb;
use esp\core\db\Mysql;
use esp\core\db\Redis;
use esp\core\db\Sqlite;
use esp\core\db\Yac;
use esp\core\ext\Buffer;
use esp\core\ext\MysqlExt;
use esp\dbs\library\Paging;
use esp\error\EspError;

/**
 * Class Model
 * @package esp\core
 *
 * func_get_args()
 */
abstract class Model extends Library
{
    private $__table_fix = 'tab';    //表前缀
    private $__table = null;        //创建对象时，或明确指定当前模型的对应表名
    private $__pri = null;          //同上，对应主键名
    private $__cache = null;       //缓存指令
    private $__tranIndex = 0;       //事务

    private $_print_sql;
    private $_debug_sql;
    private $_traceLevel = 1;

    private $_order = [];
    private $_count = null;
    private $_decode = [];
    private $_protect = true;//是否加保护符，默认加
    private $_distinct = null;//消除重复行
    private $_having = '';//having

    /**
     * @var $Buffer Buffer
     */
    private $Buffer = null;

    protected $tableJoin = array();
    protected $tableJoinCount = 0;
    protected $forceIndex = [];
    protected $selectKey = [];
    protected $columnKey = null;
    protected $groupKey = null;

    /**
     * @var Paging $paging
     */
    public $paging;

    use MysqlExt;

    /**
     * 清除自身的一些对象变量
     */
    final public function clear_initial()
    {
        //这两个值是程序临时指定的，与model自身的_table和_pri用处相同，优先级高
        $this->__table = $this->__pri = null;
        $this->_count = null;
        $this->_distinct = null;
        $this->_protect = true;
        $this->_having = '';
        $this->_order = [];
        $this->_decode = [];

        $this->columnKey = null;
        $this->groupKey = null;
        $this->forceIndex = [];
        $this->tableJoin = [];
        $this->selectKey = [];
    }

    public function __debugInfo()
    {
        return ['table' => $this->_table, 'id' => $this->_id];
    }

    public function __toString()
    {
        return json_encode(['table' => $this->_table, 'id' => $this->_id]);
    }

    final public function debug_sql(bool $df = false): Model
    {
        $this->_debug_sql = $df;
        return $this;
    }

    /**
     * 缓存设置：
     * 建议应用环境：
     * 例如：tabConfig表，内容相对固定，前端经常读取，这时将此表相对应值缓存，前端不需要每次读取数据库；
     *
     * 调用 $rds->flush(); 清除所有缓存
     * 紧急情况，将databases.mysql.cache=false可关闭所有缓存读写
     *
     * get时，cache(true)，表示先从缓存读，若缓存无值，则从库读，读到后保存到缓存
     * 注意：get时若有select字段，缓存结果也是只包含这些字段的值
     *
     * update,delete时，cache(['key'=>VALUE])用于删除where自身之外的相关缓存
     * 当数据可以被缓存的键除了where中的键之外，还可以指定其他键，同时指定其值
     *
     * 例tabArticle中，除了artID可以被缓存外，artTitle 也可以被缓存
     *
     * 当删除['artID'=>10]的时候，该缓存会被删除，但是['artTitle'=>'test']这个缓存并没有删除
     * 所以这里需要在执行delete之前指定
     *
     * $this->cache(['artTitle'=>'test'])->delete(['artID'=>10]);
     * $this->cache(['artID'=>10])->update(['artTitle'=>'test']);
     *
     * 若只有artID可以被缓存，则需要调用：$this->cache(true)->delete(['artID'=>10]);
     *
     * 若需要执行$this->delete(['artID>'=>10]);这种指令，被删除的目标数据可能存在多行，此时若也要删除对应不同artTitle的缓存
     * 则需要采用：$this->cache(['artTitle'=>['test','abc','def']])->delete(['artID>'=>10]);
     * 也就是说 artTitle 为一个数组
     *
     * 作用期间，连续执行的时候：
     * $this->cache(true)->delete(['artID'=>10]); 会删除缓存
     * $this->delete(['artID'=>11]);              不删除，因为没指定
     *
     *
     * @param bool $run
     * @return $this
     */
    final public function cache($run = true): Model
    {
        $this->__cache = $run;
        return $this;
    }

    //===================================================


    /**
     * 指定当前模型的表
     * 或，返回当前模型对应的表名
     * @param string|null $table
     * @param string|null $pri
     * @return $this|null|string
     */
    final public function table(string $table = null, string $pri = null)
    {
        if (!is_null($table)) {
            $this->__table = $table;
            if (!is_null($pri)) $this->__pri = $pri;
            return $this;
        }

        //有指定表名
        if (!is_null($this->__table)) return $this->__table;

        if (isset($this->_table)) return $this->_table;

        preg_match('/(.+\\\)?(\w+)model$/i', get_class($this), $mac);
        if (!$mac) return null;

        return ($this->__table_fix . ucfirst($mac[2]));
    }

    /**
     * 检查执行结果，所有增删改查的结果都不会是字串，所以，如果是字串，则表示出错了
     * 非字串，即不是json格式的错误内容，退出
     * @param string $action
     * @param $data
     * @return null
     * @throws EspError
     */
    final  private function checkRunData(string $action, $data)
    {
        $this->clear_initial();
        if (!is_string($data)) return null;
        $json = json_decode($data, true);
        if (isset($json[2]) or isset($json['2'])) {
            throw new EspError($action . ':' . ($json[2] ?? $json['2']), $this->_traceLevel + 1);
        }
        throw new EspError($data, $this->_traceLevel + 1);
    }

    /**
     * 增
     * @param array $data
     * @param bool $full 传入的数据是否已经是全部字段，如果不是，则要从表中拉取所有字段
     * @param bool $replace
     *  bool $returnID 返回新ID,false时返回刚刚添加的数据
     * @return int|null
     * @throws EspError
     */
    final public function insert(array $data, bool $full = false, bool $replace = false)
    {
        $table = $this->table();
        if (!$table) throw new EspError('Unable to get table name', $this->_traceLevel);
        $mysql = $this->Mysql(0, [], 1);
        $data = $full ? $data : $this->_FillField($mysql->dbName, $table, $data);
        $val = $mysql->table($table, $this->_protect)->insert($data, $replace, $this->_traceLevel);
        $ck = $this->checkRunData('insert', $val);
        if ($ck) return $ck;
        return $val;
    }

    /**
     * 直接删除相关表的缓存，一般用于批量事务完成之后
     *
     * @param string $table
     * @param array $where
     * @return $this
     * @throws EspError
     */
    final public function trans_cache(string $table, array $where): Model
    {
        if (is_null($this->Buffer)) {
            $mysql = $this->Mysql(0, [], 1);
            $cacheKey = $mysql->cacheKey;
            $this->Buffer = new Buffer($this->Redis(), $cacheKey);
        }

        $this->Buffer->table($table)->delete($where);

        return $this;
    }

    /**
     * 删
     * @param $where
     * @return bool|int|string
     * @throws EspError
     */
    final public function delete($where)
    {
        $table = $this->table();
        if (!$table) throw new EspError('Unable to get table name', $this->_traceLevel);
        if (is_numeric($where)) {
            $where = [$this->PRI() => intval($where)];
        }

        $mysql = $this->Mysql(0, [], 1);
        $val = $mysql->table($table, $this->_protect)->where($where)->delete($this->_traceLevel);
        if ($this->__cache and $mysql->cacheKey) {
            if (is_array($this->__cache)) $where += $this->__cache;
            if (is_null($this->Buffer)) $this->Buffer = new Buffer($this->Redis(), $mysql->cacheKey);
            $sc = $this->Buffer->table($table)->delete($where);
            $this->_controller->_dispatcher->debug(['buffer' => $where, 'delete' => $sc]);
            $this->__cache = null;
        }

        return $this->checkRunData('delete', $val) ?: $val;
    }


    /**
     * 改
     * @param $where
     * @param array $data
     * @return bool|null
     * @throws EspError
     */
    final public function update($where, array $data)
    {
        $table = $this->table();
        if (!$table) throw new EspError('Unable to get table name', $this->_traceLevel);
        if (is_numeric($where)) {
            $where = [$this->PRI() => intval($where)];
        }
        if (empty($where)) throw new EspError('Update Where 禁止为空', $this->_traceLevel);
        $mysql = $this->Mysql(0, [], 1);

        $val = $mysql->table($table, $this->_protect)->where($where)->update($data, true, $this->_traceLevel);
        if ($this->__cache and $mysql->cacheKey) {
            if (is_array($this->__cache)) $where += $this->__cache;
            if (is_null($this->Buffer)) $this->Buffer = new Buffer($this->Redis(), $mysql->cacheKey);
            $sc = $this->Buffer->table($table)->delete($where);
            $this->_controller->_dispatcher->debug(['buffer' => $where, 'delete' => $sc]);
            $this->__cache = null;
        }

        return $this->checkRunData('update', $val) ?: $val;
    }

    /**
     * 选择一条记录
     * @param $where
     * @param string|null $orderBy
     * @param string $sort
     * @return mixed|null
     */
    final public function get($where, string $orderBy = null, string $sort = 'asc')
    {
        $mysql = $this->Mysql(0, [], 1);
        $table = $this->table();
        if (!$table) throw new EspError('Unable to get table name', $this->_traceLevel);
        if (is_numeric($where)) {
            $where = [$this->PRI() => intval($where)];
        }

        if ($this->__cache and $mysql->cacheKey) {
            if (is_null($this->Buffer)) $this->Buffer = new Buffer($this->Redis(), $mysql->cacheKey);

            if (!empty($data = $this->Buffer->table($table)->read($where))) {
                $this->clear_initial();
                $this->__cache = null;
                $this->_controller->_dispatcher->debug(['readByBuffer' => $where]);

                if ($this->_controller->_counter) {
                    $sql = "HitCache({$table}) " . json_encode($where, 320);
                    $this->_controller->_counter->recodeMysql('select', $sql, 1);
                }

                return $data;
            }
        }

        $obj = $mysql->table($table, $this->_protect);
        if (is_int($this->columnKey)) $obj->fetch(0);

        if (!empty($this->selectKey)) {
            foreach ($this->selectKey as $select) $obj->select(...$select);
        }
        if (!empty($this->tableJoin)) {
            foreach ($this->tableJoin as $join) $obj->join(...$join);
        }
        if (is_bool($this->_debug_sql)) $obj->debug_sql($this->_debug_sql);
        if ($this->forceIndex) $obj->force($this->forceIndex);
        if ($this->_having) $obj->having($this->_having);
        if ($where) $obj->where($where);
        if (is_string($this->groupKey)) $obj->group($this->groupKey);
        if (is_bool($this->_distinct)) $obj->distinct($this->_distinct);

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
        $data = $obj->get(0, $this->_traceLevel);
        $_decode = $this->_decode;

        if ($c = $this->checkRunData('get', $data)) return $c;
        $val = $data->row($this->columnKey, $_decode);

        if ($this->__cache and $mysql->cacheKey) {
            $sc = $this->Buffer->table($table)->save($where, $val);
            $this->_controller->_dispatcher->debug(['buffer' => $where, 'save' => $sc]);
            $this->__cache = null;
        }

        if ($val === false) $val = null;

        return $val;
    }


    /**
     * @param array $where
     * @param string|null $orderBy
     * @param string $sort
     * @param int $limit
     * @return array
     */
    final public function all($where = [], string $orderBy = null, string $sort = 'asc', int $limit = 0)
    {
        $table = $this->table();
        if (!$table) throw new EspError('Unable to get table name', $this->_traceLevel);
        $obj = $this->Mysql(0, [], 1)->table($table, $this->_protect)->prepare();
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
        if (is_string($this->groupKey)) $obj->group($this->groupKey);
        if ($this->forceIndex) $obj->force($this->forceIndex);
        if (is_bool($this->_debug_sql)) $obj->debug_sql($this->_debug_sql);
        if ($this->_having) $obj->having($this->_having);

        if (is_bool($this->_distinct)) $obj->distinct($this->_distinct);

        if (!empty($this->_order)) {
            foreach ($this->_order as $k => $a) {
                $obj->order($a['key'], $a['sort'], $a['pro']);
            }
        }
        if ($orderBy) {
            if (!in_array(strtolower($sort), ['asc', 'desc', 'rand'])) $sort = 'ASC';
            $obj->order($orderBy, $sort);
        }

        $data = $obj->get($limit, $this->_traceLevel);
        $_decode = $this->_decode;
        if ($v = $this->checkRunData('all', $data)) return $v;

        return $data->rows(0, $this->columnKey, $_decode);
    }

    /**
     * @param null $where
     * @param null $orderBy
     * @param string $sort
     * @return array|mixed|null
     * @throws EspError
     */
    final public function list($where = null, $orderBy = null, string $sort = 'desc')
    {
        $table = $this->table();
        if (!$table) throw new EspError('Unable to get table name', $this->_traceLevel);
        $obj = $this->Mysql(0, [], 1)->table($table, $this->_protect);
        if (!empty($this->selectKey)) {
            foreach ($this->selectKey as $select) $obj->select(...$select);
        }
        if (!empty($this->tableJoin)) {
            foreach ($this->tableJoin as $join) $obj->join(...$join);
        }
        if (is_bool($this->_protect)) $obj->protect($this->_protect);
        if ($this->forceIndex) $obj->force($this->forceIndex);
        if (is_bool($this->_distinct)) $obj->distinct($this->_distinct);
        if (is_bool($this->_debug_sql)) $obj->debug_sql($this->_debug_sql);

        if ($where) $obj->where($where);
        if (is_string($this->groupKey)) $obj->group($this->groupKey);
        if ($this->_having) $obj->having($this->_having);

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

        $count = $this->_count;
        if (is_null($count)) $count = true;
        $obj->count($count === true);
        if (is_string($this->sumKey)) $obj->sum($this->sumKey);

        if (is_null($this->paging)) $this->paging = new Paging();
        $skip = ($this->paging->index - 1) * $this->paging->size;
        $data = $obj->limit($this->paging->size, $skip)->get(0, $this->_traceLevel);
        $_decode = $this->_decode;
        if ($v = $this->checkRunData('list', $data)) return $v;

        if ($this->sumKey) $this->paging->sum($data->sum());

        if ($count === true) {
            $this->paging->calculate($data->count());
        } else if (is_int($count)) {
            if ($count <= 10) {
                $this->paging->calculate(($count + ($this->paging->index - 1)) * $this->paging->size, true);
            } else {
                $this->paging->calculate($count);
            }
        } else {
            $this->paging->calculate(0);
        }

        return $data->rows(0, null, $_decode);
    }

    final public function sql(&$sql): Model
    {
        $this->_print_sql = true;
        $sql = $this->_print_sql;
        return $this;
    }

    final public function having(string $filter): Model
    {
        $this->_having = $filter;
        return $this;
    }

    /**
     * 压缩字符串
     * @param string $string
     * @return false|string
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
     * @return false|string
     */
    final public function ugz(string $string)
    {
        try {
            return gzuncompress($string);
        } catch (EspError $e) {
            return $e->getMessage();
        }
    }

    final public function decode(string $cols, string $type = 'json'): Model
    {
        if (!isset($this->_decode[$type])) $this->_decode[$type] = [];
        array_push($this->_decode[$type], ...array_map(function ($col) {
            if (strpos($col, '=') > 0) return explode('=', $col);
            return [$col, $col];
        }, explode(',', $cols)));
        return $this;
    }

    /**
     * 组合空间-点
     * @param $lng
     * @param null $lat
     * @return string
     */
    final public function point($lng, $lat = null): string
    {
        if (is_null($lat) and is_array($lng)) {
            $lat = $lng['lat'] ?? ($lng[1] ?? 0);
            $lng = $lng['lng'] ?? ($lng[0] ?? 0);
        }
        return "point({$lng} {$lat})";
    }

    /**
     * 组合空间-闭合的区域
     * @param array $location
     * @return string
     * @throws EspError
     */
    final public function polygon(array $location): string
    {
        if (count($location) < 3) throw new EspError("空间区域至少需要3个点");
        $val = [];
        $fst = null;
        $lst = null;
        foreach ($location as $loc) {
            $lst = "{$loc['lng']} {$loc['lat']}";
            $val[] = $lst;
            if (is_null($fst)) $fst = $lst;
        }
        if ($fst !== $lst) $val[] = $fst;
        return "polygon(" . implode(',', $val) . ")";
    }

    private $sumKey = null;

    final public function sum(string $sumKey): Model
    {
        $this->sumKey = $sumKey;
        $this->_count = true;
        return $this;
    }

    /**
     * 当前请求结果的总行数
     * @param  $count
     * @return $this
     *
     * $count取值：
     * true     :执行count(1)统计总数
     * 0|false  :不统计总数
     * 1-10     :size的倍数，为了分页不至于显示0页
     * 10以上    :为指定总数
     */
    final public function count($count = true): Model
    {
        $this->_count = $count;
        if ($count === 0) $this->_count = false;
        return $this;
    }

    /**
     * 是否加保护符，默认加
     * @param bool $protect
     * @return $this
     */
    final public function protect(bool $protect): Model
    {
        $this->_protect = $protect;
        return $this;
    }

    /**
     * 消除重复行
     * @param bool $bool
     * @return $this
     */
    final public function distinct(bool $bool = true): Model
    {
        $this->_distinct = $bool;
        return $this;
    }

    final public function pagingSet(int $size, int $index = 0, int $recode = null): Model
    {
        $this->paging = new Paging($size, $index, $recode);
        return $this;
    }

    final public function pageSet(int $size, int $index = 0, int $recode = null): Model
    {
        $this->paging = new Paging($size, $index, $recode);
        return $this;
    }

    final public function paging(int $size, int $index = 0, int $recode = null): Model
    {
        $this->paging = new Paging($size, $index, $recode);
        return $this;
    }

    final public function pagingIndex(int $index): Model
    {
        $this->paging->index($index);
        return $this;
    }

    /**
     * @param string $string
     * @return mixed
     */
    final public function quote(string $string)
    {
        return $this->Mysql(0, [], 1)->quote($string);
    }


    final public function join(...$data): Model
    {
        if (empty($data)) {
            $this->tableJoin = array();
            return $this;
        }
        $this->tableJoin[] = $data;
        return $this;
    }

    final public function group(string $groupKey, bool $only = false): Model
    {
        if ($only) $this->columnKey = 0;
        $this->groupKey = $groupKey;
        return $this;
    }


    /**
     * 返回指定键列
     * @param string $field
     * @return $this
     */
    final public function field(string $field): Model
    {
        $this->columnKey = 0;
        $this->selectKey = [[$field, true]];
        return $this;
    }

    /**
     * 返回第x列数据
     * @param int $field
     * @return $this
     */
    final public function column(int $field = 0): Model
    {
        $this->columnKey = $field;
        return $this;
    }

    /**
     * 强制从索引中读取，多索引用逗号连接
     * @param $index
     * @return $this
     */
    final public function force($index): Model
    {
        if (empty($index)) return $this;
        if (is_string($index)) $index = explode(',', $index);
        $new = array_merge($this->forceIndex, $index);
        $this->forceIndex = array_unique($new);
        return $this;
    }

    /**
     * 强制从索引中读取
     * @param string $index
     * @return $this
     */
    final public function index($index): Model
    {
        if (empty($index)) return $this;
        if (is_string($index)) $index = explode(',', $index);
        $new = array_merge($this->forceIndex, $index);
        $this->forceIndex = array_unique($new);
        return $this;
    }


    /**
     * 设置排序字段，优先级高于函数中指定的方式
     * @param $key
     * @param string $sort
     * @param bool $addProtect
     * @return $this
     */
    final public function order($key, string $sort = 'asc', bool $addProtect = null): Model
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
        if (is_null($addProtect)) $addProtect = $this->_protect;
        $this->_order[] = ['key' => $key, 'sort' => $sort, 'pro' => $addProtect];
        return $this;
    }

    /**
     * @param $select
     * @param $add_identifier
     * @return $this
     * @throws EspError
     */
    final public function select($select, $add_identifier = null): Model
    {
        if (is_int($add_identifier)) {
            //当$add_identifier是整数时，表示返回第x列数据
            $this->columnKey = $add_identifier;
            $this->selectKey[] = [$select, true];

        } else {
            if (is_null($add_identifier)) $add_identifier = $this->_protect;
            if ($select and ($select[0] === '~' or $select[0] === '!')) {
                //不含选择，只适合从单表取数据
                $field = $this->fields();
                $seKey = array_column($field, 'COLUMN_NAME');
                $kill = explode(',', substr($select, 1));
                $this->selectKey[] = [implode(',', array_diff($seKey, $kill)), $add_identifier];

            } else {
                $this->selectKey[] = [$select, $add_identifier];
            }
        }
        return $this;
    }

    /**
     * @param string $tab
     * @param int $traceLevel
     * @return Yac
     */
    final public function Yac(string $tab = 'tmp', int $traceLevel = 0): Yac
    {
        if (!isset($this->_controller->_Yac[$tab])) {
            $this->_controller->_Yac[$tab] = new Yac($tab);
            $this->_controller->_dispatcher->debug("New Yac({$tab});", $traceLevel + 1);
        }
        return $this->_controller->_Yac[$tab];
    }

    /**
     * @param string $dbFile
     * @param int $traceLevel
     * @return Sqlite
     */
    final public function Sqlite(string $dbFile, int $traceLevel = 0): Sqlite
    {
        $key = md5($dbFile);
        if (!isset($this->_controller->_Sqlite[$key])) {
            $conf = $this->_controller->_config->get('database.sqlite');
            if (!$conf) $conf = [];
            $conf['db'] = $dbFile;
            $this->_controller->_Sqlite[$key] = new Sqlite($this->_controller, $conf);
            $this->_controller->_dispatcher->debug("New Sqlite({$dbFile});", $traceLevel + 1);
        }
        return $this->_controller->_Sqlite[$key];
    }

    /**
     * 创建一个Mysql实例
     * @param int $tranID
     * @param int $traceLevel
     * @param array $_conf 如果要创建一个持久连接，则$_conf需要传入参数：persistent=true，
     * @return Mysql
     * @throws EspError
     */
    final public function Mysql(int $tranID = 0, array $_conf = [], int $traceLevel = 0): Mysql
    {
        $branchName = $this->_branch ?? 'auto';

        if ($tranID === 1) $tranID = ++$this->__tranIndex;

        if (isset($this->_controller->_Mysql[$branchName][$tranID])) {
            return $this->_controller->_Mysql[$branchName][$tranID];
        }

        $conf = $this->_controller->_config->get('database.mysql');
        if (isset($this->_branch) and !empty($this->_branch)) {
            $_branch = $this->_controller->_config->get($this->_branch);
            if (empty($_branch) or !is_array($_branch)) {
                throw new EspError("Model中`_branch`指向内容非Mysql配置信息", $traceLevel + 1);
            }
            $this->_controller->_dispatcher->debug([$_branch, $conf]);
            $conf = $_branch + $conf;
        }
        if (isset($this->_conf) and is_array($this->_conf) and !empty($this->_conf)) {
            $this->_controller->_dispatcher->debug([$this->_conf, $conf]);
            $conf = $this->_conf + $conf;
        }

        if (empty($conf) or !is_array($conf)) {
            throw new EspError("`Database.Mysql`配置信息错误", $traceLevel + 1);
        }
        $conf = $_conf + $conf + ['branch' => $branchName];
        $this->_controller->_Mysql[$branchName][$tranID] = new Mysql($this->_controller, $tranID, $conf);
        $this->_controller->_dispatcher->debug(["new Mysql({$branchName}-{$tranID});", $conf], $traceLevel + 1);

        return $this->_controller->_Mysql[$branchName][$tranID];
    }


    /**
     * @param string $db
     * @param array $_conf
     * @param int $traceLevel
     * @return Mongodb
     * @throws EspError
     */
    final public function Mongodb(string $db = 'temp', array $_conf = [], int $traceLevel = 0): Mongodb
    {
        if (!_CLI and isset($this->_controller->_Mongodb[$db])) {
            return $this->_controller->_Mongodb[$db];
        }

        $conf = $this->_controller->_config->get('database.mongodb');
        if (empty($conf)) {
            throw new EspError('无法读取mongodb配置信息', $traceLevel + 1);
        }

        $this->_controller->_Mongodb[$db] = new Mongodb($_conf + $conf, $db);
        $this->_controller->_dispatcher->debug("New Mongodb({$db});", $traceLevel + 1);
        return $this->_controller->_Mongodb[$db];
    }

}