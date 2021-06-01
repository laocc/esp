<?php
declare(strict_types=1);

namespace esp\core\db\ext;

use esp\core\db\Mysql;
use esp\error\EspError;

/**
 *
 * 负责查询或执行语句的创建
 * Class Builder
 */
final class Builder
{
    private $_table = '';//使用的表名称
    private $_table_prefix;//表前缀

    private $_select = array();//保存已经设置的选择字符串
    private $_join_select = array();//join表字段

    private $_where = '';//保存已经设置的Where子句
    private $_where_group_in = false;

    private $_limit = '';//LIMIT组合串
    private $_skip = 0;//跳过行数

    private $_join = array();//保存已经应用的join字符串，数组内保存解析好的完整字符串
    private $_joinTable = array();

    private $_order_by = '';//保存已经解析的排序字符串

    private $_group;
    private $_forceIndex = '';
    private $_having = '';

    private $_MySQL;//Mysql
    private $_Trans_ID = 0;//多重事务ID，正常情况都=0，只有多重事务理才会大于0

    private $_count = false;//是否启用自动统计
    private $_distinct = null;//消除重复行
    private $_fetch_type = 1;//返回的数据，是用1键值对方式，还是0数字下标，或3都要，默认1

    private $_dim_param = false;//系统是否定义了是否使用预处理
    private $_prepare = false;//是否启用预处理
    private $_param = false;//预处理中，是否启用参数占位符方式
    private $_param_data = array();//占位符的事后填充内容

    private $_bindKV = array();

    private $_gzLevel = 5;//压缩比
    private $_protect = true;//默认加保护符

    public function __construct(Mysql $mysql, string $table_prefix, bool $param, int $trans_id = 0)
    {
        $this->_MySQL = $mysql;
        $this->_dim_param = $param;
        $this->_table_prefix = $table_prefix;
        $this->clean_builder();

        //必须在clean_builder后执行
        $this->_Trans_ID = $trans_id;
    }

    /**
     * 清除所有现有的`Query_builder`设置内容
     * @param bool $clean_all
     */
    private function clean_builder($clean_all = true)
    {
        $this->_table = $this->_where = $this->_limit = $this->_having = $this->_order_by = '';
        $this->_select = $this->_join = $this->_join_select = array();
        $this->_where_group_in = 0;

        $this->_skip = 0;
        $this->_fetch_type = 1;
        $this->_count = false;
        $this->_group = null;
        $this->_distinct = null;
        $this->_protect = true;
        $this->_gzLevel = 5;

        $this->_prepare = $this->_param = $this->_dim_param;
        $this->_bindKV = $this->_param_data = array();

        //清除全部数据
        if ($clean_all === false) return;
        $this->_Trans_ID = 0;
    }


    /**
     * 设置，或获取表名
     *
     * @param string|null $tableName
     * @param bool|null $_protect
     * @return $this
     */
    public function table(string $tableName = null, bool $_protect = null)
    {
        $this->clean_builder(false);
        if ($this->_table_prefix and stripos($tableName, $this->_table_prefix) === false) {
            $tableName = "{$this->_table_prefix}{$tableName}";
        }
        if (is_bool($_protect)) $this->_protect = $_protect;
        $this->_table = $tableName;
        if ($this->_protect) $this->_table = $this->protect_identifier($this->_table);
        return $this;
    }

    /**
     * 事务结束，提交。
     * @param bool $rest
     * @return string|bool
     */
    public function commit(bool $rest = true)
    {
        $val = $this->_MySQL->trans_commit($this->_Trans_ID);
        if ($rest && is_array($val)) return $val['error'];
        return $val;
    }

    /**
     * 检查值是否合法，若=false，则回滚事务
     * @param bool $value ，为bool表达式结果，一般如：!!$ID，或：$val>0
     * @return bool 返回$value相反的值，即：返回true表示被回滚了，false表示正常
     */
    public function back(bool $value)
    {
        if ($value) return false;
        return $this->_MySQL->trans_back($this->_Trans_ID);
    }

    /**
     * 相关错误
     * @return array
     */
    public function error()
    {
        return $this->_MySQL->_error[$this->_Trans_ID];
    }


    /**
     * 使用预处理方式
     * @param bool|true $bool
     * @return $this
     */
    public function prepare(bool $bool = true)
    {
        $this->_prepare = $bool;
        return $this;
    }

    /**
     * 对于[update,insert]时，相关值使用预定义参数方式
     * 此方式依赖于使用预处理方式，所以即便没有指定使用预处理方式，此方式下也会隐式的使用预处理方式。
     * @param bool|true $bool
     * @return $this
     */
    public function param(bool $bool = true)
    {
        $this->_param = $bool;
        if ($bool and !$this->_prepare) {
            $this->_prepare = true;
        }
        return $this;
    }

    /**
     * @param $key
     * @param null $param
     * @return $this
     */
    public function bind($key, $param = null)
    {
        if ($key === 0) {
            throw new EspError('PDO数据列绑定下标应该从1开始', 1);
        }
        if (is_array($key)) {
            $this->_bindKV += $key;
        } else {
            $this->_bindKV[$key] = $param;
        }
        $this->_prepare = true;
        return $this;
    }


    /**
     * 消除重复行
     * @param bool $bool
     * @return $this
     */
    public function distinct(bool $bool = true)
    {
        $this->_distinct = $bool;
        return $this;
    }

    /**
     * 返回的数据形式：
     * 0：数字下标，
     * 1：键值对方式，默认1
     * 2：两者都要
     * [0=>\PDO::FETCH_NUM, 1=>\PDO::FETCH_ASSOC, 2=>\PDO::FETCH_BOTH];
     * @param int $type
     * @return $this
     */
    public function fetch(int $type = 1)
    {
        $this->_fetch_type = $type;
        return $this;
    }

    /**
     * 传回mysql的选项
     * @param $action
     * @return array
     */
    private function option(string $action): array
    {
        return [
            'param' => ($this->_param or $this->_prepare) ? $this->_param_data : [],
            'prepare' => $this->_param ?: $this->_prepare,
            'count' => $this->_count,
            'fetch' => $this->_fetch_type,
            'bind' => $this->_bindKV,
            'trans_id' => $this->_Trans_ID,
            'action' => $action,
        ];
    }

    /**
     * 执行一个选择子句，并进行简单的标识符转义
     * 标识符转义只能处理简单的选择子句，对于复杂的选择子句
     * 请设置第二个参数为 false
     *
     * 自动保护标识符目前可以处理以下情况：
     *      1. 简单的选择内容
     *      2. 点号分隔的"表.字段"的格式
     *      3. 以上两种情况下的"AS"别名
     *
     * 暂不支持函数式
     *
     *
     * @param string|array $fields 选择子句字符串，可以是多个子句用逗号分开的格式
     * @param bool $add_identifier 是否自动添加标识符，默认是true，对于复杂查询及带函数的查询请设置为false
     * @return $this
     */
    public function select($fields, bool $add_identifier = null)
    {
        if (is_array($fields) and !empty($fields)) {
            foreach ($fields as $field) {
                $this->select($field, $add_identifier);
            }
            return $this;
        }
        if (!is_string($fields) or empty($fields)) {
            throw new EspError('选择的字段不能为空，且只能是字符串类型。', 1);
        }

        /**
         * 如果设置了不保护标识符（针对复杂的选择子句）
         * 或：选择*所有
         * 直接设置当前内容到选择数组，不再进行下面的操作
         */
        if ($add_identifier === false or $fields === '*') {
            $this->_select[] = $fields;
            return $this;
        }

        if ($this->_protect) {
            $select = explode(',', $fields);
            array_push($this->_select, ...$this->protect_identifier($select));
        } else {
            $this->_select[] = $fields;
        }

        return $this;
    }


    /**
     * 选择字段的最大值
     *
     * @param $fields
     * @param null $rename
     * @return $this
     */
    public function select_max($fields, $rename = null)
    {
        return $this->select_func('MAX', $fields, $rename);
    }

    /**
     * 选择字段的最小值
     *
     * @param $fields
     * @param null $rename
     * @return $this
     */
    public function select_min($fields, $rename = null)
    {
        return $this->select_func('MIN', $fields, $rename);
    }

    /**
     * 选择字段的平均值
     *
     * @param $fields
     * @param null $rename
     * @return $this
     */
    public function select_avg($fields, $rename = null)
    {
        return $this->select_func('AVG', $fields, $rename);
    }

    /**
     * 选择字段的和
     *
     * @param $fields
     * @param null $rename
     * @return $this
     */
    public function select_sum($fields, $rename = null)
    {
        return $this->select_func('SUM', $fields, $rename);
    }

    /**
     * 计算行数
     *
     * @param string $fields
     * @param null $rename
     * @return Builder
     */
    public function select_count($fields = '*', $rename = null)
    {
        return $this->select_func('COUNT', $fields, $rename);
    }

    /**
     * 执行一个查询函数，如COUNT/MAX/MIN等等
     *
     * @param string $func 函数名
     * @param string $select 字段名
     * @param string|null $AS 如果需要重命名返回字段，这里是新的字段名
     * @return $this
     */
    private function select_func($func, $select = '*', string $AS = null)
    {
        $select = $this->protect_identifier($select);
        $this->_select[] = strtoupper($func) . "({$select})" . ($AS ? " AS `{$AS}`" : '');
        return $this;
    }

    /**
     * 根据当前选择字符串生成完成的select 语句
     * @return string
     */
    private function _build_select()
    {
        //($this->_count ? ' SQL_CALC_FOUND_ROWS ' : '') .
        if (empty($this->_join)) {
            return (empty($this->_select) ? '*' : implode(',', $this->_select));
        } else {
            if (empty($this->_join_select)) {
                return (empty($this->_select) ? '*' : implode(',', $this->_select));
            } else if (empty($this->_select)) {
                return "{$this->_table}.*," . implode(',', $this->_join_select);
            } else {
                return implode(',', $this->_select) . ',' . implode(',', $this->_join_select);
            }
        }
    }


    /**
     * 执行一个Where子句
     * 接受以下几种方式的参数：
     *      1. 直接的where字符串，这种方式不做任何转义和处理，不推荐使用，如 where('abc.def = "ade"')
     *      2. 两个参数分别是表达式和值的情况，自动添加标识符和值转义，如 where('abc.def', 'ade')
     *      3. 第2种情况的KV数组，如 where(['abc'=>'def', 'cde'=>'fgh'])
     *
     * 表达式支持以下格式的使用及自动转义
     *      where('aaa <=', 'ddd')
     *
     *
     * 如果当前查询中有join，且where中有所join表的字段条件，则在计算总数时不考虑join表
     * 而如果join表中有where条件，则需要在句子中明确指表名。
     * 如原语句：where userAge>10 and orderAmount>100,这其中userAge是join表tabUser的，
     * 则需改为：where tabUser.userAge>10 and orderAmount>100
     * 如果不加表名，则在不考虑userAge条件的情况下计算总数
     *
     * @param string $field
     * @param null $value
     * @param null $is_OR 若=0则返回组合后的where而不加入现有sql中
     * @return $this|string
     * @throws EspError
     */
    public function where($field = '', $value = null, $is_OR = null)
    {
        if (empty($field)) return $this;

        //省略了第三个参数，第二个是布尔型
        if (is_bool($value) and $is_OR === null) {
            list($is_OR, $value) = [$value, null];
        }
        if (is_bool($value)) {
            throw new EspError("DB_ERROR: where 不支持Bool类型的值", 1);
        } else if (is_object($value)) {
            throw new EspError("DB_ERROR: where 不支持Object类型的值", 1);
        }
        if (is_string($is_OR)) {
            $is_OR = ('or' === strtolower($is_OR));
        }

        /**
         * 处理第一个参数是数组的情况（忽略第二个参数）
         * 每数组中的每个元素应用where子句
         */
        if (is_array($field) and !empty($field)) {
            foreach ($field as $key => $val) {
                $fType = is_string($key) ? strtolower($key[-1]) : '';
                if (is_int($key)) {
                    if (is_array($val)) {//多条件或开始

                        $this->where_group_start(false);

                        foreach ($val as $k => $v) {
                            if (is_int($k) and is_array($v)) {
                                /**
                                 * $where[] = [['labID' => 1], ['labKey' => 2]]; //* 不同字段
                                 */
                                $simWhere = [];
                                foreach ($v as $vk => $vv) {
                                    $simWhere[] = $this->where($vk, $vv, 0);
                                }
                                $this->_where_insert('(' . implode(' and ', $simWhere) . ')', 'or');

                            } else {
                                //$where['labID'] = [1, 2];     //同一字段
                                $this->where($k, $v, true);
                            }
                        }
                        $this->where_group_end();
                    } else {
                        $this->where($val, null, $is_OR);
                    }
                } else if (is_array($val) and !in_array($fType, ['#', '$', '@', '%'])) {
                    $this->where_group_start(false);
                    foreach ($val as $v) $this->where($key, $v, true);
                    $this->where_group_end();
                } else {
                    $this->where($key, $val, $is_OR);
                }
            }
            return $this;
        }

        if (!is_string($field)) {
            throw new EspError("DB_ERROR: where 条件异常:" . var_export($field, true), 1);
        }


        if ($value === null) {
            if (preg_match('/^[a-z0-9]+$/i', $field)) {
                $_where = "isnull({$field})";
            } else {
                /**
                 * 未指定条件值，则其本身就是一个表达式，直接应用当前Where子句
                 * @NOTE 尽量不要使用这种方式（难以处理安全性）
                 */
                $_where = $field;
            }
        } else {
            $sqlVal = false;
            $findType = strtolower($field[-1]);
            if ($findType === '\\') {
                //where字段后加\号，如：$where['value<=\\'] = "(select num from table where expID={$expID})";
                $brackets = (is_string($value) and $value[0] === '(' and $value[-1] === ')');
                if (!$brackets) {
                    throw new EspError("DB_ERROR: where 直接引用SQL时，被引用的SQL要用括号圈定完整语句", 1);
                }
                $field = substr($field, 0, -1);
                $findType = strtolower($field[-1]);
                $sqlVal = true;
            }

            switch ($findType) {
                case '~'://组合 like
                    $field = substr($field, 0, -1);
                    $pos = '';
                    if ($field[-1] === '!') {
                        $pos = 'not ';
                        $field = substr($field, 0, -1);
                    }

                    if (!empty($value)) {
                        if ($value[0] === '^') $value = substr($value, 1);
                        else if ($value[0] !== '%') $value = "%{$value}";
                        if (!empty($value)) {
                            if ($value[-1] === '$') $value = substr($value, 0, -1);
                            else if ($value[-1] !== '%') $value = "{$value}%";
                        }
                    }
                    $fieldPro = $this->protect_identifier($field);
                    if ($this->_param) {//采用占位符后置内容方式
                        $key = $this->paramKey($field);
                        $this->_param_data[$key] = $value;
                        $_where = "{$fieldPro} {$pos} like {$key}";
                    } else {
                        $_where = "{$fieldPro} {$pos} like " . $this->quote($value);
                    }

                    break;
                case '^'://组合 "locate('{$key}',keyWord)";
                    $field = substr($field, 0, -1);
                    $pos = '>0';
                    if ($field[-1] === '!') {
                        $pos = '=0';
                        $field = substr($field, 0, -1);
                    }
                    $fieldPro = $this->protect_identifier($field);
                    if ($this->_param) {//采用占位符后置内容方式
                        $key = $this->paramKey($field);
                        $this->_param_data[$key] = $value;
                        $_where = "locate(" . $key . ",{$fieldPro}){$pos}";
                    } else {
                        $_where = "locate('" . $this->quote($value) . "',{$fieldPro}){$pos}";
                    }
                    break;
                case '$'://全文搜索："MATCH (`godTitle`,`godPinYin`) AGAINST ('{$py}')"
                    $field = substr($field, 0, -1);
                    $pos = '>';
                    if ($field[-1] === '!') {
                        $pos = '<=';
                        $field = substr($field, 0, -1);
                    }
                    $fieldPro = $this->protect_identifier($field);
                    if (!is_array($value)) $value = [$value, 0];
                    if (!is_float($value[1])) throw new EspError("MATCH第2个值只能是浮点型值，表示匹配度", 1);

                    if ($this->_param) {//采用占位符后置内容方式
                        $key = $this->paramKey($field);
                        $this->_param_data[$key] = $value[0];
                        $_where = "MATCH({$fieldPro}) AGAINST (" . $key . "){$pos}{$value[1]}";
                    } else {
                        $_where = "MATCH({$fieldPro}) AGAINST ('" . $this->quote($value[0]) . "'){$pos}{$value[1]}";
                    }
                    break;
                case '!'://等同于 !=
                    $field = substr($field, 0, -1);
                    $fieldPro = $this->protect_identifier($field);

                    if ($sqlVal) {
                        $_where = "{$fieldPro} != {$value}";

                    } else if ($this->_param) {//采用占位符后置内容方式
                        $key = $this->paramKey($field);
                        $this->_param_data[$key] = $value;
                        $_where = "{$fieldPro} != {$key}";
                    } else {
                        $_where = "{$fieldPro} != " . $this->quote($value) . "";
                    }

                    break;
                case '&'://位运算
                    $field = substr($field, 0, -1);
                    $in = '>0';
                    if ($field[-1] === '!') {
                        $in = '=0';
                        $field = substr($field, 0, -1);
                    }
                    $value = intval($value);
                    $fieldPro = $this->protect_identifier($field);

                    if ($this->_param) {//采用占位符后置内容方式
                        if ($value === 0) {
                            $_where = "({$fieldPro} = 0 )";
                        } else {
                            $key = $this->paramKey($field);
                            $this->_param_data[$key] = $value;
                            $_where = "({$fieldPro} >0 and ({$fieldPro} & {$key}){$in})";
                        }
                    } else {
                        if ($value === 0) {
                            $_where = "({$fieldPro} = 0 )";
                        } else {
                            $_where = "({$fieldPro} >0 and ({$fieldPro} & " . $this->quote($value) . "){$in})";
                        }
                    }
                    break;
                case '*'://正则表达式
                    $field = substr($field, 0, -1);
                    $fieldPro = $this->protect_identifier($field);
                    if ($this->_param) {//采用占位符后置内容方式
                        $key = $this->paramKey($field);
                        $this->_param_data[$key] = $value;
                        $_where = "{$fieldPro} REGEXP {$key}";
                    } else {
                        $_where = "{$fieldPro} REGEXP " . $this->quote($value);
                    }
                    break;
                case '#'://组合 between;
                    $field = substr($field, 0, -1);
                    $in = 'between';
                    if ($field[-1] === '!') {
                        $in = 'not between';
                        $field = substr($field, 0, -1);
                    }

                    if (empty($value)) $value = [0, 0];
                    $fieldPro = $this->protect_identifier($field);

                    if ($this->_param) {//采用占位符后置内容方式
                        if (is_array($value[0])) {
                            $_wbt = [];
                            foreach ($value as $vi => $val) {
                                $key1 = $this->paramKey($field . $vi);
                                $key2 = $this->paramKey($field . $vi);
                                $this->_param_data[$key1] = $val[0];
                                $this->_param_data[$key2] = $val[1];
                                $_wbt[] = "({$fieldPro} {$in} {$key1} and {$key2})";
                            }
                            $_where = '(' . implode(' or ', $_wbt) . ')';

                        } else {
                            $key1 = $this->paramKey($field);
                            $key2 = $this->paramKey($field);
                            $this->_param_data[$key1] = $value[0];
                            $this->_param_data[$key2] = $value[1];
                            $_where = "{$fieldPro} {$in} {$key1} and {$key2}";
                        }
                    } else {
                        $_where = "{$fieldPro} {$in} ({$value[0]} and {$value[1]})";
                    }
                    break;
                case '@'://组合 in 和 not in
                    $field = substr($field, 0, -1);
                    $in = 'in';
                    if ($field[-1] === '!') {
                        $in = 'not in';
                        $field = substr($field, 0, -1);
                    }
                    $fieldPro = $this->protect_identifier($field);

                    if ($sqlVal) {
                        //in的结果是一个SQL语句
                        $_where = "{$fieldPro} {$in} {$value}";
                        break;
                    } else if (!is_array($value)) {
                        throw new EspError("where in 的值必须为数组形式", 1);
                    }
                    if (empty($value)) $value = [0, 0];

                    if ($this->_param) {//采用占位符后置内容方式
                        //用字段组合一个只有\w的字符，也就是剔除所有非\w的字符，用于预置占位符
                        $keys = [];
                        foreach ($value as $i => $val) {
                            $keys[$i] = $this->paramKey($field . $i);
                            $this->_param_data[$keys[$i]] = $val;
                        }
                        $key = implode(',', $keys);
                        $_where = "{$fieldPro} {$in} ({$key})";
                    } else {
                        $_where = "{$fieldPro} {$in} ({$value})";
                    }
                    break;
                case '%'://mod
                    $field = substr($field, 0, -1);
                    $in = '=';
                    if ($field[-1] === '!') {
                        $in = '!=';
                        $field = substr($field, 0, -1);
                    }

                    if (!is_array($value)) {
                        throw new EspError("mod 的值必须为数组形式，如mod(Key,2)=1，则value=[2,1]", 1);
                    }
                    if (empty($value)) $value = [2, 1];
                    $fieldPro = $this->protect_identifier($field);

                    if ($this->_param) {//采用占位符后置内容方式
                        $key = $this->paramKey($field);
                        $this->_param_data[$key] = $value[1];
                        $_where = "mod({$fieldPro},{$value[0]}) {$in} {$key} ";
                    } else {
                        $_where = "mod({$fieldPro},{$value[0]}) {$in} {$value[1]} ";
                    }
                    break;
                case '=':
                    $field = substr($field, 0, -1);
                    $in = '<=>';
                    if ($field[-1] === '!') {
                        $in = '!=';
                        $field = substr($field, 0, -1);
                    } else if ($field[-1] === '>') {
                        $in = '>=';
                        $field = substr($field, 0, -1);
                    } else if ($field[-1] === '<') {
                        $in = '<=';
                        $field = substr($field, 0, -1);
                    }
                    $fieldPro = $this->protect_identifier($field);
                    if ($sqlVal) {
                        $_where = "{$fieldPro} {$in} {$value}";

                    } else if ($this->_param) {
                        $key = $this->paramKey($field);
                        $this->_param_data[$key] = $value;
                        $_where = "{$fieldPro} {$in} {$key}";
                    } else {
                        $_where = "{$fieldPro} {$in} " . $this->quote($value);
                    }

                    break;
                case '>':
                case '<':
                    $field = substr($field, 0, -1);
                    $fieldPro = $this->protect_identifier($field);

                    if ($sqlVal) {
                        $_where = "{$fieldPro} {$findType} {$value}";

                    } else if ($this->_param) {
                        $key = $this->paramKey($field);
                        $this->_param_data[$key] = $value;
                        $_where = "{$fieldPro} {$findType} {$key}";
                    } else {
                        $_where = "{$fieldPro} {$findType} " . $this->quote($value);
                    }
                    break;
                case ':'://预留
                case ';'://预留
                case '?'://预留
                    break;
                default:
                    if (in_array($findType, ['-', '+', ',', '.', '?', '/'])) {
                        $field = substr($field, 0, -1);
                    }
                    $fieldPro = $this->protect_identifier($field);

                    if ($sqlVal) {
                        $_where = "{$fieldPro} = {$value}";

                    } else if ($this->_param) {//采用占位符后置内容方式
                        $key = $this->paramKey($field);
                        $this->_param_data[$key] = $value;
                        $_where = "{$fieldPro} = {$key}";

                    } else {
                        $_where = "{$fieldPro} = " . $this->quote($value);// 对 $value 进行安全转义
                    }
            }
        }
        if (empty($_where)) {
            throw new EspError("where条件为空", 1);
        }
        if ($is_OR === '') return $_where;
        $this->_where_insert($_where, ($is_OR ? ' OR ' : ' AND '));
        return $this;
    }

    private function paramKey(string $field): string
    {
        if (strlen($field) > 32) $field = md5($field);
        return ':' . preg_replace('/[^\w]/i', '', $field) . uniqid();
    }

    /**
     * 执行一个where or 子句
     * @param $field
     * @param null $value
     * @return Builder
     */
    public function or_where($field, $value = null)
    {
        return $this->where($field, $value, true);
    }

    /**
     * @param $field
     * @param null $value
     * @return Builder
     */
    public function where_or($field, $value = null)
    {
        return $this->where($field, $value, true);
    }

    /**
     * 创建一个Where IN 查旬子句
     *
     * @param string $field 字段名
     * @param array $data IN的内容，必须是一个数组
     * @param bool $is_OR
     * @param bool $isNot
     * @return $this
     */
    public function where_in(string $field, array $data, bool $is_OR = false, bool $isNot = false)
    {
        if (empty($field)) {
            throw new EspError('DB_ERROR: where in 条件不可为空', 1);
        }
        $protectField = $this->protect_identifier($field);

        $data = empty($data) ? null : $data;
        if (is_array($data)) $data = implode("','", $data);
        $IN = $isNot ? ' not in' : ' in ';

        if ($this->_param) {
            $key = $this->paramKey($field);
            $this->_param_data[$key] = "'{$data}'";
            $_where = "{$protectField} {$IN} ({$key})";
        } else {
            $data = quotemeta($data);
            $_where = "{$protectField} {$IN} ('{$data}')";
        }
        $this->_where_insert($_where, ($is_OR ? ' OR ' : ' AND '));

        return $this;
    }

    /**
     * @param $field
     * @param $data
     * @param bool $is_OR
     * @return Builder
     */
    public function where_not_in($field, $data, $is_OR = false)
    {
        return $this->where_in($field, $data, $is_OR, true);
    }

    /**
     * 创建一个 OR WHERE xxx IN xxx 的查询
     *
     * @param string $field
     * @param array $data
     * @return Builder
     */
    public function or_where_in($field, array $data)
    {
        return $this->where_in($field, $data, true);
    }

    /**
     * 创建一个where like 子句
     * 代码和 where_in差不多
     * @param $field
     * @param $value
     * @param bool $is_OR
     * @return $this
     */
    public function where_like($field, $value, $is_OR = false)
    {
        if (empty($field) || empty($value)) {
            throw new EspError('DB_ERROR: where like 条件不能为空', 1);
        }
        $protectField = $this->protect_identifier($field);

        if ($this->_param) {
            $key = $this->paramKey($field);
            $this->_param_data[$key] = $value;
            $_where = "{$protectField} LIKE {$key}";

        } else {
            $value = quotemeta($value);
            $_where = "{$protectField} LIKE '{$value}'";
        }
        $this->_where_insert($_where, ($is_OR ? 'OR' : 'AND'));
        return $this;
    }

    private function _where_insert(string $_where, string $ao): void
    {
        if (empty($this->_where)) {
            $this->_where = $_where;
        } else {
            if ($this->_where_group_in === 1) {
                $this->_where .= " {$_where}";
            } else {
                $this->_where .= " {$ao} {$_where} ";
            }
            if ($this->_where_group_in) $this->_where_group_in++;
        }
    }

    /**
     * 开始一个where组，用于建立复杂的where查询，需要与
     * where_group_end()配合使用
     *
     * @param bool $is_OR
     * @return $this
     */
    public function where_group_start(bool $is_OR = false)
    {
        if ($this->_where_group_in) {
            throw new EspError('DB_ERROR: 当前还处于Where Group之中，请先执行where_group_end', 1);
        }
        if (empty($this->_where)) {
            $this->_where = '(';
        } else {
            $this->_where .= ($is_OR ? ' or' : ' and') . ' (';
        }
        $this->_where_group_in++;
        return $this;
    }

    /**
     * 结束一个where组，为语句加上后括号
     * @return $this
     */
    public function where_group_end()
    {
        if (!$this->_where_group_in) {
            throw new EspError('DB_ERROR: 当前未处于Where Group之中', 1);
        }
        if (empty($this->_where)) {
            throw new EspError('DB_ERROR: 当前where条件为空，无法创建where语句', 1);
        } else {
            $this->_where .= ')';
            $this->_where_group_in = 0;
        }
        return $this;
    }

    /**
     * @return Builder
     * @return Builder
     * @see $this->where_group_start
     */
    public function or_where_group_start()
    {
        return $this->where_group_start(true);
    }

    /**
     * 创建Where查询字符串
     * @return string
     */
    private function _build_where()
    {
        if (empty($this->_where)) return '';
        if ($this->_where_group_in) {
            throw new EspError('DB_ERROR: 当前还处于Where Group之中，请先执行where_group_end', 1);
        }
        /**
         * 这只是个权宜之计，暂时先用正则替换掉括号后面的AND和OR
         * preg_replace('/\((\s*(?:AND|OR))/', '(', $this->_where)
         */
        return $this->_where;
    }


    /**
     * limit的辅助跳过
     * @param $n
     * @return $this
     */
    public function skip(int $n)
    {
        $this->_skip = $n;
        return $this;
    }

    /**
     * 启用统计
     * @param bool|true $bool
     * @return $this
     */
    public function count(bool $bool = true)
    {
        $this->_count = $bool;
        return $this;
    }


    /**
     * 执行Limit查询
     *
     * @param int $size 要获取的记录数
     * @param int $skip 偏移
     * @return $this
     */
    public function limit(int $size, int $skip = 0)
    {
        $skip = $skip ?: $this->_skip;
        if ($skip < 0) $skip = 0;
        if ($skip === 0) {
            $this->_limit = $size;
        } else {
            $this->_limit = $skip . ',' . $size;
        }
        return $this;
    }


    /**
     * 创建一个联合查询
     * @param string $table 要联查的表的名称
     * @param null $_filter 条件
     * @param string $select 选择字段
     * @param string $method 联查的类型，默认是NULL，可选值为'left','right','inner','outer','full','using'
     * @param bool $identifier 是否加保护符
     * @return $this
     */
    public function join(string $table, $_filter, string $select = null, string $method = 'left', bool $identifier = true)
    {
        $method = strtoupper($method);
        if (!in_array($method, [null, 'LEFT', 'RIGHT', 'INNER', 'OUTER', 'FULL', 'USING'])) {
            throw new EspError('DB_ERROR: JOIN模式不存在：' . $method, 1);
        }
        $this->_joinTable[] = $table;

        // 保护标识符
        if ($identifier) $table = $this->protect_identifier($table);

        //连接条件允许以数组方式
        if (is_string($_filter)) {
            if (stripos($_filter, ' and ')) {
                $_filter = explode(' and ', $_filter);
            } else if (stripos($_filter, ',')) {
                $_filter = explode(',', $_filter);
            } else {
                $_filter = [$_filter];
            }
        } else if (!is_array($_filter)) {
            throw new EspError('DB_ERROR: JOIN 条件未指定，需为string或array形式', 1);
        }

        $_filter_arr = array_map(function ($re) use ($identifier) {
            return preg_replace_callback('/^(.*)(>|<|<>|=|<=>)(.*)/', function ($mch) use ($identifier) {
                if ($mch[1] === $mch[3]) {
                    throw new EspError('DB_ERROR: JOIN条件两边不能完全相同，如果是不同表相同字段名，请用[tabName.filed]的方式', 1);
                }
                if ($identifier)
                    return $this->protect_identifier($mch[1]) . " {$mch[2]} " . $this->protect_identifier($mch[3]);
                else
                    return "{$mch[1]} {$mch[2]} {$mch[3]}";
            }, $re);
        }, $_filter);

        $_filter_str = implode(' and ', $_filter_arr);

        if ($method === 'USING') {
            $this->_join[] = " JOIN {$table} USING ({$_filter_str}) ";
        } else {
            $this->_join[] = " {$method} JOIN {$table} ON ({$_filter_str}) ";
        }
        if (!is_null($select)) {
            if ($select === '*') {
                $this->_join_select[] = "{$table}.*";
            } else {
                $this->_join_select[] = $select;
            }
        }
        return $this;
    }

    /**
     * 创建一个排序规则
     *
     * @param string $field 需要排序的字段名
     * @param string $method 排序的方法，可选值有 'ASC','DESC','RAND' 其中，RAND随机排序和字段名无关（任意即可）
     * @param bool $addProtect
     * @return $this
     * @throws EspError
     */
    public function order($field, $method = 'ASC', bool $addProtect = null)
    {
        /**
         * 检查传入的排序方式是否被支持
         */
        $method = strtoupper(trim($method));
        if (!in_array($method, ['ASC', 'DESC', 'RAND'])) {
            throw new EspError('DB_ERROR: ORDER模式不存在：' . $method, 1);
        }

        if ($method === 'RAND' or $field === 'RAND') {
            $str = 'RAND()';//随机排序,和字段无关
        } else {
            if ($addProtect) $field = $this->protect_identifier($field);
            $str = "{$field} {$method}";
        }

        if (empty($this->_order_by)) {
            $this->_order_by = $str;
        } else {
            $this->_order_by .= ', ' . $str;
        }
        return $this;
    }


    /**
     * 执行Group 和 having
     *
     * @param string $field 分组字段
     * @return $this
     *
     * 被分组，过滤条件的字段务必出现在select中
     *
     * $sql="select orgGoodsID,count(*) as orgCount from tabs group by orgGoodsID having orgCount>1";
     */
    public function group($field)
    {
        $this->_group = $field;
        return $this;
    }

    public function force(array $index)
    {
        $this->_forceIndex = implode(',', $index);
        return $this;
    }

    /**
     * @param string $filter
     * @return $this
     */
    public function having(string $filter)
    {
        $this->_having = $filter;
        return $this;
    }


    /**
     * @return string
     */
    public function _build_get()
    {
        $sql = array();
        $sql[] = "SELECT " . ($this->_distinct ? 'DISTINCT ' : '') . $this->_build_select();

        $sql[] = "FROM {$this->_table}";

        if (!empty($this->_forceIndex)) $sql[] = "FORCE index({$this->_forceIndex})";

        if (!empty($this->_join)) $sql[] = implode(' ', $this->_join);

        if (!empty($where = $this->_build_where())) $sql[] = "WHERE {$where}";

        if (!empty($this->_group)) {
            if (is_array($this->_group)) {
                $this->_group = implode(',', $this->_group);
            }
            $sql[] = "GROUP BY {$this->_group}";
        }

        if (!empty($this->_having)) $sql[] = "HAVING {$this->_having}";

        if (!empty($this->_order_by)) $sql[] = "ORDER BY {$this->_order_by}";

        if (!empty($this->_limit)) $sql[] = "LIMIT {$this->_limit}";
        return implode(' ', $sql);
    }


    public function _build_count_sql()
    {
        $sql = array();
        $sql[] = "SELECT";
        $sql[] = "count(1) FROM {$this->_table}";
        if (!empty($this->_forceIndex)) $sql[] = "force index({$this->_forceIndex})";

        $where = $this->_build_where();
        if (!empty($this->_join) and !empty($where)) {
            foreach ($this->_join as $j => $join) {
                if (stripos($where, $this->_joinTable[$j]) !== false) {
                    $sql[] = $join;
                }
            }
        }

        if (!empty($where)) $sql[] = "WHERE {$where}";

        if (!empty($this->_group)) {
            if (is_array($this->_group)) {
                $this->_group = implode(',', $this->_group);
            }
            $sql[] = "GROUP BY {$this->_group}";
        }

        if (!empty($this->_having)) $sql[] = "HAVING {$this->_having}";

        return implode(' ', $sql);
    }


    /**
     * 获取查询结果
     * @param int $row
     * @param int $tractLevel
     * @return bool|Result|mixed|null
     */
    public function get(int $row = 0, int $tractLevel = 0)
    {
        if ($row > 0) $this->limit($row);

        $option = $this->option('select');
        $_build_sql = $this->_build_get();
        $this->replace_tempTable($_build_sql);

        if ($option['count']) {
            $option['_count_sql'] = $this->_build_count_sql();
            $this->replace_tempTable($option['_count_sql']);
        }
        $get = $this->_MySQL->query($_build_sql, $option, null, $tractLevel + 1);

        if (is_string($get)) throw new EspError($get, $tractLevel + 1);

        return $get;
    }

    /**
     * 组合SQL，并不执行，暂存起来，供后面调用
     * @return string
     */
    public function temp()
    {
        $tmpID = uniqid();
        $this->_temp_table[$tmpID] = $this->_build_get();
        return $tmpID;
    }

    private $_temp_table = array();


    /**
     * @return string
     */
    public function sql()
    {
        return $this->_build_get();
    }


    /**
     * @param $sql
     */
    private function replace_tempTable(&$sql)
    {
        if (empty($this->_temp_table)) return;
        $sql = preg_replace_callback('/(?:\[|<)([a-f0-9]{14})(?:\]|>)/i', function ($matches) {
            if (!isset($this->_temp_table[$matches[1]])) return $matches[0];
            $table = $this->_temp_table[$matches[1]];
            unset($this->_temp_table[$matches[1]]);//用完即清除，所以不需要初始化时清空
            return "( {$table} ) as {$matches[1]} ";
        }, $sql);
    }


    /**
     * 删除记录，配合where类子句使用以删除指定记录
     * 没有where的情况下是删除表内所有数据
     * @param int $tractLevel
     * @return bool|Result|int|string
     * @throws EspError
     */
    public function delete(int $tractLevel = 0)
    {
        $where = $this->_build_where();
        if (empty($where)) {//禁止无where时删除数据
            throw new EspError('DB_ERROR: 禁止无where时删除数据，如果要清空表，请采用：id>0的方式', $tractLevel + 1);
        }

        $sql = array();
        $sql[] = "DELETE";
        $sql[] = "FROM {$this->_table} WHERE {$where}";
        if (!empty($this->_order_by)) $sql[] = "ORDER BY {$this->_order_by}";
        if (!empty($this->_limit)) $sql[] = "LIMIT {$this->_limit}";
        $sql = implode(' ', $sql);
        return $this->_MySQL->query($sql, $this->option('delete'), null, $tractLevel + 1);
    }


    /**
     * 一次插入多个值
     * $v = [['name' => 'lcc', 'sex' => 1], ['name' => 'zhang', 'sex' => 0]];
     * @param array $data
     * @param bool $is_REPLACE
     * @param int $tractLevel
     * @return int
     * 注：在一次插入很多记录时，不用预处理或许速度更快，若一次插入数据只有几条或十几条，这种性能损失可以忽略不计。
     */
    public function insert(array $data, bool $is_REPLACE = false, int $tractLevel = 0)
    {
        if (empty($data)) throw new EspError('DB_ERROR: 无法 insert/replace 空数据', $tractLevel + 1);

        $this->_param_data = array();

        //非多维数组，转换成多维
        if (!(isset($data[0]) and is_array($data[0]))) $data = [$data];
        if (isset($data[0][0])) throw new EspError('DB_ERROR: insert/replace 只能插入二维或三维数组', $tractLevel + 1);

        $values = array();
        $keys = null;
        $param = null;
        $op = $is_REPLACE ? 'REPLACE' : 'INSERT';

        foreach ($data as $i => &$item) {

            if ($this->_param) {
                $valKey = [];
                $nv = [];
                $itemKey = [];
                foreach ($item as $k => &$v) {
                    if (is_array($v)) $v = json_encode($v, 256 | 64);
                    if (is_null($v)) throw new EspError("DB_ERROR: INSERT({$k})值不可为NULL", $tractLevel + 1);
                    switch (substr($k, -1)) {
                        case '#':  //压缩数据
                            $k = substr($k, 0, -1);
                            $v = gzcompress($v, $this->_gzLevel);
                            $nv[":{$k}"] = $v;
                            $valKey[] = ":{$k}";
                            break;

                        case '\\': //直接表达式
                            $k = substr($k, 0, -1);
                            $nv[":{$k}"] = $v;
                            $valKey[] = ":{$k}";
                            break;

                        case '@'://空间位置数据
                            $k = substr($k, 0, -1);
                            $nv[":{$k}"] = $v;
                            $valKey[] = "ST_GeomFromText(:{$k})";
                            break;

                        default:
                            $nv[":{$k}"] = $v;
                            $valKey[] = ":{$k}";
                    }
                    $itemKey[] = $k;
                }
                if (is_null($keys)) $keys = $itemKey;
                if (is_null($param)) $param = '(' . implode(',', $valKey) . ')';
                $this->_param_data[] = $nv;

            } else {
                $itemKey = [];
                $itemVal = [];
                foreach ($item as $k => &$val) {
                    if (is_array($val)) $val = json_encode($val, 256 | 64);
                    switch (substr($k, -1)) {
                        case '#':
                            $k = substr($k, 0, -1);
                            $val = gzcompress($val, $this->_gzLevel);
                            $itemVal[] = "'{$val}'";
                            break;
                        case '\\':
                            $k = substr($k, 0, -1);
                            $itemVal[] = $val;
                            break;
                        case '@':
                            $k = substr($k, 0, -1);
                            $itemVal[] = "ST_GeomFromText('{$val}')";
                            break;
                        default:
                            if (is_string($val)) {
                                $itemVal[] = "'{$val}'";
                            } else {
                                $itemVal[] = $val;
                            }
                    }

                    $itemKey[] = $k;
                }
                if (is_null($keys)) $keys = $itemKey;
                $values[] = '(' . implode(', ', $itemVal) . ')';
            }
        }

        $keys = $this->protect_identifier($keys);
        $keys = implode(', ', $keys);

        $value = $param ?: implode(', ', $values);

        $sql = "{$op} INTO {$this->_table} ({$keys}) VALUES {$value}";
        return $this->_MySQL->query($sql, $this->option($op), null, $tractLevel + 1);
    }

    /**
     * 执行一个replace查询
     * replace和insert的区别：当insert时，若唯一索引值冲突，则会报错，而replace则自动先delete再insert，这是原子性操作。
     * 在执行REPLACE后，系统返回了所影响的行数，
     * 如果返回1，说明在表中并没有重复的记录，
     * 如果返回2，说明有一条重复记录，系统自动先调用了 DELETE删除这条记录，然后再记录用INSERT来插入这条记录。
     * 如果返回的值大于2，那说明有多个唯一索引，有多条记录被删除和插入。
     *
     * @param array $data
     * @return string
     */
    public function replace(array $data)
    {
        return $this->insert($data, true);
    }


    /**
     * @param array $data
     * @param bool $add_identifier
     * @param int $tractLevel
     * @return bool|Result|null
     */
    public function update(array $data, bool $add_identifier = true, int $tractLevel = 0)
    {
        if (empty($data)) {
            throw new EspError('DB_ERROR: 不能 update 空数据', $tractLevel + 1);
        }
        $sets = array();
        foreach ($data as $key => &$value) {
            if (is_int($key)) {
                $sets[] = $value;
                continue;
            }
            if (is_array($value)) $value = json_encode($value, 256 | 64);
            if (is_null($value)) throw new EspError("DB_ERROR: Update({$key})值不可为NULL", $tractLevel + 1);

            $kFH = substr($key, -1);

            if ($kFH === '#') { //字段以#结束，表示此字段值要压缩
                /**
                 * 采用压缩的字段类型只能是：
                 * TINYBLOB    0-255 bytes    不超过 255 个字符的二进制字符串 ，对应：CHAR
                 * BLOB    0-65 535 bytes    二进制形式的长文本数据，对应：TEXT和VARCHAR
                 * MEDIUMBLOB    0-16 777 215 bytes    二进制形式的中等长度文本数据，对应：MEDIUMTEXT
                 * LONGBLOB    0-4 294 967 295 bytes    二进制形式的极大文本数据，对应：LONGBLOB
                 * 其中之一。
                 */
                $key = substr($key, 0, -1);
                $value = gzcompress($value, $this->_gzLevel);
                if ($this->_param) {
                    $pKey = $this->paramKey($key);
                    $this->_param_data[$pKey] = $value;
                    $value = $pKey;
                } else {
                    $value = $this->quote($value);
                }

            } elseif (in_array($kFH, ['+', '-', '|', '&', '$'])) { //键以+-结束，或以|&结束的位运算
                $key = substr($key, 0, -1);
                if (!is_numeric($value)) {
                    throw new EspError("DB_ERROR: [{$key}]加减操作时，其值必须为数字", $tractLevel + 1);
                }
                if ($kFH === '$') {
                    //位运算：减法，值为自己先异或value
                    $value = $this->protect_identifier($key) . " - ({$key} & {$value})";
                } else {
                    $value = $this->protect_identifier($key) . " {$kFH} {$value}";
                }

            } else if ($kFH === '\\') {// \\ 的值为表达式或另一个字段
                $key = substr($key, 0, -1);

//                $value = $this->protect_identifier($key) . " = {$value}";

            } else if ($kFH === '@') {// 空间数据
                $key = substr($key, 0, -1);

                $value = "ST_GeomFromText('{$value}')";

            } else if ($kFH === '.') {//.号为拼接
                $key = substr($key, 0, -1);

                if ($this->_param) {
                    $pKey = $this->paramKey($key);
                    $this->_param_data[$pKey] = $value;
                    $value = "CONCAT(`{$key}`,{$pKey})";

                } else {
                    $value = "CONCAT(`{$key}`,'{$value}')";
                }

            } elseif ($this->_param) {
                $pKey = $this->paramKey($key);
                $this->_param_data[$pKey] = $value;
                $value = $pKey;

            } elseif ($add_identifier) {
                $value = $this->quote($value);

            }


            $sets[] = $this->protect_identifier($key) . " = {$value}";
        }

        $where = $this->_build_where();
        if (empty($where)) {//禁止无where时更新数据
            throw new EspError('DB_ERROR: 禁止无where时更新数据', $tractLevel + 1);
        }

        $sets = implode(', ', $sets);
        $sql = "UPDATE {$this->_table} SET {$sets} WHERE {$where}";

        $exe = $this->_MySQL->query($sql, $this->option('update'), null, $tractLevel + 1);

        if (is_string($exe)) throw new EspError($exe, $tractLevel + 1);

        return $exe;
    }

    /**
     * 用于复杂的条件更新
     * @param array $upData
     * @param int $tractLevel
     * @return false|string
     *
     * 此时外部where作为大条件，内部条件仅实现了根据name查询，更复杂的条件有待进一步设计
     *
     * update_batch(['name' => ['张小三' => '张三', '李大牛' => '李四'],'address' => ['上海' => '江苏']]);
     * 将：name=张小三改为张三，李大牛改为李四，同时将address=上海改为江苏，两者无关系。
     *
     */
    public function update_batch(array $upData, int $tractLevel = 0)
    {
        if (empty($upData)) {
            throw new EspError('DB_ERROR: 不能 update 空数据', $tractLevel + 1);
        }
        $sql = $when = [];
        $sql[] = "UPDATE {$this->_table} SET";
        foreach ($upData as $key => $data) {
            if ($this->_protect) $key = $this->protect_identifier($key);

            if (isset($data[0]) and isset($data[1])) {
                $oldVal = quotemeta($data[0]);
                $newVal = quotemeta($data[1]);
            } else {
                $oldVal = $newVal = '';
                foreach ($data as $oldVal => $newVal) {
                    if (!is_array($newVal)) {
                        $oldVal = quotemeta($oldVal);
                        $newVal = quotemeta($newVal);
                    }
                }
            }
            $when[] = "{$key} = CASE WHEN {$key} = {$oldVal} THEN {$newVal} ELSE {$key} END";
        }

        $sql[] = implode(',', $when);
        $where = $this->_build_where();
        if (empty($where)) {//禁止无where时更新数据
            throw new EspError('DB_ERROR: 禁止无where时更新数据', $tractLevel + 1);
        }
        $sql[] = "WHERE {$where}";

        return $this->_MySQL->query(implode(' ', $sql), $this->option('update'), null, $tractLevel + 1);
    }

    /**
     * 是否加保护符，默认加
     * @param bool $protect
     * @return $this
     */
    public function protect(bool $protect)
    {
        $this->_protect = $protect;
        return $this;
    }

    /**
     * 保护标识符
     * 目前处理类似于以下格式：
     *      abc
     *      abc.def
     *      def AS hij
     *      abc.def AS hij
     *
     * @param string|array $clause
     * @return mixed|string
     */
    private function protect_identifier($clause)
    {
        if (!$this->_protect) return $clause;
        /**
         * 处理数组形式传入参数
         */
        if (is_array($clause)) {
            $r = array();
            foreach ($clause as $cls) {
                $r[] = $this->protect_identifier($cls);
            }
            return $r;
        }
        if (!is_string($clause)) return $clause;
        if ($clause === '*') return '*';
        $clause = trim(str_replace('`', '', $clause));//先去除已存在的`号

        if (preg_match('/^[a-z]+\(.+\)$/i', $clause, $m)) {
            //userName => `userName`
            return $clause;

        } else if (preg_match('/^[\w\-]+$/i', $clause, $m)) {
            //userName => `userName`
            return "`{$clause}`";

        } else if (preg_match('/^([\w\-]+)\.([\w\-]+)$/i', $clause, $m)) {
            //tabUser.userName => `tabUser`.`userName`
            return "`{$m[1]}`.`{$m[2]}`";

        } else if (preg_match('/^([\w\-]+)\.\*$/i', $clause, $m)) {
            //tabUser.* => `tabUser`.*
            return "`{$m[1]}`.*";

        } else if (preg_match('/^[\w\-]+\.?[\w\-]+\,[\w\-]+.+$/i', $clause, $m)) {
            //tabUser.userName,userMobile like
            return "CONCAT({$clause})";

        } else if (preg_match('/^([\w\-]+)\s+AS\s+([\w\-]+)$/i', $clause, $m)) {
            //userName as name => `userName` as `name`
            return "`{$m[1]}` as `{$m[2]}`";

        } else if (preg_match('/^([\w\-]+)\.([\w\-]+)\s+AS\s+([\w\-]+)$/i', $clause, $m)) {
            //tabUser.userName as name => `tabUser`.`userName` as `name`
            return "`{$m[1]}`.`{$m[2]}` as `{$m[3]}`";
        }

        //其他情况都加
        return "`{$clause}`";
    }

    /**
     * 给字符串加引号，同时转义元字符
     * @param $data
     * @return array|string
     */
    private function quote($data)
    {
        if (is_array($data)) {
            foreach ($data as $i => $v) {
                if (is_string($v)) $data[$i] = "'" . quotemeta($v) . "'";//转义元字符集
            }
            return $data;
        } elseif (is_string($data)) {
            return "'" . quotemeta($data) . "'";
        } else {
            return $data;
        }
    }


    /**
     * 组合空间-点
     * @param $lng
     * @param null $lat
     * @return string
     */
    public function point($lng, $lat = null)
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
    public function polygon(array $location)
    {
        if (count($location) < 3) throw new EspError("空间的一个区域至少需要3个点");
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


}