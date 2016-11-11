<?php
namespace esp\extend\db;

use esp\core\Config;

class Mysql
{
    const _ERROR_RETURN = null;//执行出错时返回的内容

    private $_debug = false;//若debug=true，则在出错时，将打印SQL信息，此设置可在config中定义
    private $_try_error_throw = false;//若try到错误，是否抛出异常
    private $_CONF;//配置定义
    private $_logTitle = null;//日志标题
    private $_trans_state = [];//事务状态
    private $_sql_logs = [];//记录SQL语句
    public $slave = [];//从库连接
    public $master = [];//主库连接
    public $_error = [];//每个连接的错误信息

    public function __construct($conf = null)
    {
        if (!is_array($conf)) $conf=Config::get('mysql');

        $this->_CONF = $conf;
        $this->_sql_logs = &$sql;
        $this->_debug = !!$conf->debug;
    }

    /**
     * @param $tabName
     * @return ext\Builder
     * @throws \Exception
     */
    public function table($tabName)
    {
        if (!is_string($tabName) || empty($tabName)) {
            error('PDO_Error :  数据表名错误');
        }
        return (new ext\Builder($this, $this->_CONF->table))->table($tabName);
    }

    /**
     * 创建一个事务
     * @param int $trans_id
     * @return bool|ext\Builder
     * @throws \Exception
     */
    public function trans($trans_id = 1)
    {
        if (is_array($trans_id)) {
            return $this->trans_batch($trans_id);
        }
        if ($trans_id === 0) {
            error("Trans Error: 多重事务ID须从1开始，不可以为0。");
        }
        $CONN = $this->connect(1, $trans_id);//连接数据库，自动选择主从库
        if ($CONN->inTransaction()) {
            error("Trans Begin Error: 当前正处于未完成的事务{$trans_id}中");
        }
        $CONN->beginTransaction();
        $this->_trans_state[$trans_id] = true;
        return new ext\Builder($this, $this->_CONF->table, $trans_id);
    }

    /**
     * 提交事务
     * @param \PDO $CONN
     * @throws \Exception
     */
    public function trans_commit(\PDO &$CONN, $trans_id)
    {
        if (isset($this->_trans_state[$trans_id]) and $this->_trans_state[$trans_id] === false) return false;

        if (!$CONN->inTransaction()) {
            error("Trans Commit Error: 当前没有处于事务{$trans_id}中");
        }
        $this->_trans_state[$trans_id] = false;
        return $CONN->commit();
    }

    /**
     * 回滚事务
     * @return bool
     */
    public function trans_back(\PDO &$CONN, $trans_id = 0, &$error = null)
    {
        $this->_trans_state[$trans_id] = false;
        if (!$CONN->inTransaction()) {
            return true;
        }
        $this->_sql_logs[] = [
            'wait' => 0,
            'trans' => $trans_id,
            'sql' => 'rollBack',
            'prepare' => null,
            'param' => null,
            'result' => true,
            'error' => $error,
        ];
        return $CONN->rollBack();
    }

    /**
     * 检查当前连接是否还在事务之中
     * @param \PDO $CONN
     * @param $trans_id
     * @return bool
     */
    public function trans_in(\PDO &$CONN, $trans_id)
    {
        return $CONN->inTransaction();
    }

    /**
     * @param $queryType
     * @return \PDO
     */
    private function connect($upData, $trans_id = 0)
    {
        $c = $this->_CONF;
        $real = $upData ? 'master' : 'slave';

        //当前缓存过该连接，直接返
        if (isset($this->{$real}[$trans_id]) and !!$this->{$real}[$trans_id]) {
            return $this->{$real}[$trans_id];
        }

        $host = $c->{$real};

        //不是更新操作时，选择从库，需选择一个点
        if (!$upData) $host = $host[ip2long(_IP) % count($host)];

        try {
            $opts = array(
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_SILENT,//错误等级
                \PDO::ATTR_AUTOCOMMIT => $upData and !$trans_id,//关闭自动提交事务=false，默认true
                \PDO::ATTR_EMULATE_PREPARES => false,//是否使用PHP本地模拟prepare,禁止
                \PDO::ATTR_PERSISTENT => true,//启用持久连接
                \PDO::ATTR_TIMEOUT => 2, //设置超时时间，默认=2
            );
            $time_a = microtime(true);
            $conStr = "mysql:dbname={$c->db};host={$host};port={$c->port};charset={$c->charset};id={$trans_id};";
            $pdo = new \PDO($conStr, $c->user, $c->pwd, $opts);
            $this->{$real}[$trans_id] = $pdo;
            $this->_sql_logs[] = [
                'wait' => (microtime(true) - $time_a) * 1000,
                'trans' => null,
                'sql' => $conStr,
                'prepare' => null,
                'param' => null,
                'result' => '(Object)PDO',
                'error' => intval($pdo->errorCode()) ? $pdo->errorInfo()[2] : null,
            ];
            return $this->{$real}[$trans_id];

        } catch (\PDOException $PdoError) {
            /*
             *信息详细程度取决于$opts里PDO::ATTR_ERRMODE =>
             * PDO::ERRMODE_SILENT，只简单地设置错误码，默认值
             * PDO::ERRMODE_WARNING： 还将发出一条传统的 E_WARNING 信息，
             * PDO::ERRMODE_EXCEPTION，还将抛出一个 PDOException 异常类并设置它的属性来反射错误码和错误信息，
            */
            error("Mysql Connection failed:" . $PdoError->getCode() . ',' . $PdoError->getMessage());
        }
    }


    /**
     * 直接执行SQL
     * @param $sql
     * @return null
     * @throws \Exception
     */
    public function query($sql, &$data = [])
    {
        $action = $this->sqlAction($sql);
        if (!$action) {
            if ($this->_debug) echo $sql;
            error("PDO_Error :  SQL语句不合法");
        }
        $option = [
            'param' => $data,
            'prepare' => true,
            'count' => false,
            'fetch' => 1,
            'bind' => [],
            'trans_id' => 0,
            'action' => $action,
        ];

        return $this->query_exec($sql, $option);
    }

    /**
     * 从SQL语句中获取该语句的执行性质
     * @param string $sql
     * @return string|null
     */
    private function sqlAction($sql)
    {
        if (preg_match('/^(select|insert|replace|update|delete)\s+.+/is', trim($sql), $matches)) {
            return $matches[1];
        } else {
            return null;
        }
    }

    /**
     * 批量事务
     * @param array $SQLs
     * @return bool
     * @throws \Exception
     */
    public function trans_batch(array $SQLs)
    {
        $trans_id = $this->trans_id();
        $CONN = $this->connect(1, $trans_id);//连接数据库，自动选择主从库
        if ($CONN->beginTransaction()) {
            $this->_trans_state[$trans_id] = true;
            foreach ($SQLs as &$sql) {
                $action = $this->sqlAction($sql);
                if (!$action) {
                    if ($this->_debug) echo $sql;
                    error("PDO_Error :  SQL语句不合法");
                }
                $option = [
                    'param' => false,
                    'prepare' => true,
                    'count' => false,
                    'fetch' => 0,
                    'bind' => [],
                    'trans_id' => $trans_id,
                    'action' => $action,
                ];
                $this->query_exec($sql, $option, $CONN);
            }
            $in = $CONN->inTransaction();
            $CONN->commit();
            $this->_trans_state[$trans_id] = false;
            return $in;//返回commit之前的事务状态
        } else {
            error("PDO_Error :  启动事务失败。");
        }
    }

    /**
     * 查找当前空闲的事务ID
     * @return int
     */
    private function trans_id()
    {
        $c = count($this->_trans_state);
        for ($i = 1; $i <= $c; $i++) {
            if (!isset($this->_trans_state[$i]) or !$this->_trans_state[$i]) return $i;
        }
        return $i;
    }

    /**
     * @param string $sql
     * @param array $option
     * @param \PDO|null $CONN
     * @return null|bool|ext\Result
     * @throws \Exception
     */
    public function query_exec($sql, array $option, \PDO &$CONN = null)
    {
        if (empty($sql)) {
            error("PDO_Error :  SQL语句不能为空");
        }
        $action = strtolower($option['action']);

        if (!in_array($action, ['select', 'insert', 'replace', 'update', 'delete'])) {
            error("PDO_Error :  数据处理方式不明确：【{$action}】。");
        }

        $upData = ($action === 'select') ? 0 : 1;//是否更新数据操作

        //数据操作时，若当前_trans_state=false，则说明刚才被back过了或已经commit，后面的数据不再执行
        if ($upData > 0 and isset($this->_trans_state[$option['trans_id']]) and $this->_trans_state[$option['trans_id']] === false) {
            return self::_ERROR_RETURN;
        }

        //连接数据库，自动选择主从库
        $CONN = $CONN ?: $this->connect($upData, $option['trans_id']);


        //执行SQL
        $time_a = microtime(true);
        $result = $this->{$action}($CONN, $sql, $option, $error);//执行
        $this->_sql_logs[] = [
            'wait' => (microtime(true) - $time_a) * 1000,
            'trans' => $option['trans_id'] ? $option['trans_id'] : null,
            'sql' => $sql,
            'prepare' => (!empty($option['param']) or $option['prepare']) ? 'YES' : 'NO',
            'param' => empty($option['param']) ? null : json_encode(isset($option['param'][1]) ? $option['param'] : $option['param'][0], 256),
            'result' => is_object($result) ? ('(Object)' . get_class($result) . '{}') : $result,
            'error' => $error,
        ];
        if (!!$error) $this->_error[$option['trans_id']][] = $error;

        //记录日志，若当前在事务中，则在这里不记录，事后一并保存
        if (!!$upData and !!$this->_logTitle) {
            $this->saveLog($sql, $this->_logTitle);
        }

        return $result;
    }

    /**
     * @param \PDO $CONN
     * @param string $sql
     * @param array $option
     * @param $error
     * @return int|null 受影响的行数
     * @throws \Exception
     */
    private function update(\PDO &$CONN, &$sql, array &$option, &$error)
    {
        if (!empty($option['param']) or $option['prepare']) {
            try {
                $stmt = $CONN->prepare($sql, [\PDO::MYSQL_ATTR_FOUND_ROWS => true]);
                if ($stmt === false) {//预处理时就出错，一般是不应该的，有可能是字段名不对等等
                    $error = $CONN->errorInfo()[2];
                    $this->trans_back($CONN, $option['trans_id'], $error);//回滚事务
                    if ($this->_debug) echo $sql;
                    error('PDO_Error :  Prepare Update = ' . print_r($error, true));
                }
            } catch (\PDOException $PdoError) {//执行预处理，如果出错，很少见，还没遇到过
                $error = $PdoError->getMessage();
                $this->trans_back($CONN, $option['trans_id'], $error);//回滚事务
                if ($this->_try_error_throw) {
                    error('PDO_Error :  Prepare Update = ' . print_r($error, true));
                }
                return self::_ERROR_RETURN;
            }
            try {
                $run = $stmt->execute($option['param']);
                if ($run === false) {//执行预处理过的内容，如果不成功，多出现传入的值不符合字段类型的情况
                    $error = $stmt->errorInfo()[2];
                    $this->trans_back($CONN, $option['trans_id'], $error);//回滚事务
                    return self::_ERROR_RETURN;
                }
            } catch (\PDOException $PdoError) {//执行预处理过的SQL，如果出错，很少见，还没遇到过
                $error = $PdoError->getMessage();
                $this->trans_back($CONN, $option['trans_id'], $error);//回滚事务
                if ($this->_try_error_throw) {
                    if ($this->_debug) pre($option['param']);
                    error('PDO_Error :  Execute Update = ' . print_r($error, true));
                }
                return self::_ERROR_RETURN;
            }
            return $stmt->rowCount();//受影响的行数
        } else {
            try {
                $run = $CONN->exec($sql);
                if ($run === false) {
                    $error = $CONN->errorInfo()[2];
                    $this->trans_back($CONN, $option['trans_id'], $error);//回滚事务
                    return self::_ERROR_RETURN;
                } else {
                    return $run;//受影响的行数
                }
            } catch (\PDOException $PdoError) {
                $error = $PdoError->getMessage();
                $this->trans_back($CONN, $option['trans_id'], $error);//回滚事务
                if ($this->_try_error_throw) {
                    if ($this->_debug) echo $sql;
                    error('PDO_Error :  Exec Update = ' . print_r($error, true));
                }
                return self::_ERROR_RETURN;
            }
        }
    }


    /**
     * @param \PDO $CONN
     * @param string $sql
     * @param array $option
     * @param $error
     * @return int|null 被删除的行数
     * @throws \Exception
     */
    private function delete(\PDO &$CONN, &$sql, array &$option, &$error)
    {
        if (!empty($option['param']) or $option['prepare']) {
            try {
                $stmt = $CONN->prepare($sql);
                if ($stmt === false) {
                    $error = $CONN->errorInfo()[2];
                    $this->trans_back($CONN, $option['trans_id'], $error);//回滚事务
                    if ($this->_debug) echo $sql;
                    error('PDO_Error :  Prepare Delete = ' . print_r($error, true));
                }
            } catch (\PDOException $PdoError) {//执行预处理，如果出错，很少见，还没遇到过
                $error = $PdoError->getMessage();
                $this->trans_back($CONN, $option['trans_id'], $error);//回滚事务
                if ($this->_try_error_throw) {
                    error('PDO_Error :  Prepare Delete = ' . print_r($error, true));
                }
                return self::_ERROR_RETURN;
            }
            try {
                $run = $stmt->execute($option['param']);
                if ($run === false) {
                    $error = $stmt->errorInfo()[2];
                    $this->trans_back($CONN, $option['trans_id'], $error);//回滚事务
                    return self::_ERROR_RETURN;
                }
            } catch (\PDOException $PdoError) {
                $error = $PdoError->getMessage();
                $this->trans_back($CONN, $option['trans_id'], $error);//回滚事务
                if ($this->_try_error_throw) {
                    if ($this->_debug) pre($option['param']);
                    error('PDO_Error :  Execute Delete = ' . print_r($error, true));
                }
                return self::_ERROR_RETURN;
            }
            return $stmt->rowCount();//受影响的行数，也就是被删除的行数
        } else {
            try {
                $run = $CONN->exec($sql);
                if ($run === false) {
                    $error = $CONN->errorInfo()[2];
                    $this->trans_back($CONN, $option['trans_id'], $error);//回滚事务
                    return self::_ERROR_RETURN;
                } else {
                    return $run;//受影响的行数，也就是被删除的行数
                }
            } catch (\PDOException $PdoError) {
                $error = $PdoError->getMessage();
                $this->trans_back($CONN, $option['trans_id'], $error);//回滚事务
                if ($this->_try_error_throw) {
                    if ($this->_debug) echo $sql;
                    error('PDO_Error :  Exec Delete = ' . print_r($error, true));
                }
                return self::_ERROR_RETURN;
            }
        }
    }

    /**
     * insert的副本
     * @param \PDO $CONN
     * @param string $sql
     * @param array $option
     * @param $error
     * @return array|int|null
     */
    private function replace(\PDO &$CONN, &$sql, array &$option, &$error)
    {
        return $this->insert($CONN, $sql, $option, $error);
    }

    /**
     * @param \PDO $CONN
     * @param string $sql
     * @param array $option
     * @param $error
     * @return array|int|null 最后插入的ID，若批量插入则返回值是数组
     * @throws \Exception
     */
    private function insert(\PDO &$CONN, &$sql, array &$option, &$error)
    {
        if (!empty($option['param']) or $option['prepare']) {
            $result = [];
            try {
                $stmt = $CONN->prepare($sql);
                if ($stmt === false) {
                    $error = $CONN->errorInfo()[2];
                    $this->trans_back($CONN, $option['trans_id'], $error);//回滚事务
                    if ($this->_debug) echo $sql;
                    error('PDO_Error :  Prepare Insert = ' . print_r($error, true));
                }
            } catch (\PDOException $PdoError) {//执行预处理，如果出错，很少见，还没遇到过
                $error = $PdoError->getMessage();
                $this->trans_back($CONN, $option['trans_id'], $error);//回滚事务
                if ($this->_try_error_throw) {
                    error('PDO_Error :  Prepare Insert = ' . print_r($error, true));
                }
                return self::_ERROR_RETURN;
            }
            if (!empty($option['param'])) {//有后续参数
                foreach ($option['param'] as &$row) {
                    try {
                        $run = $stmt->execute($row);
                        if ($run === false) {
                            $error = intval($stmt->errorCode()) ? $stmt->errorInfo()[2] : 'insert execute error';
                            $this->trans_back($CONN, $option['trans_id'], $error);//回滚事务
                            return self::_ERROR_RETURN;
                        } else {
                            $result[] = (int)$CONN->lastInsertId();//最后插入的ID
                        }
                    } catch (\PDOException $PdoError) {
                        $error = $PdoError->getMessage();
                        $this->trans_back($CONN, $option['trans_id'], $error);//回滚事务
                        if ($this->_try_error_throw) {
                            if ($this->_debug) pre($row);
                            error('PDO_Error :  Execute Insert = ' . print_r($error, true));
                        }
                        return self::_ERROR_RETURN;
                    }
                }
            } else {//无后续参数
                try {
                    $run = $stmt->execute();
                    if ($run === false) {
                        $error = intval($stmt->errorCode()) ? $stmt->errorInfo()[2] : 'insert execute error.';
                        $this->trans_back($CONN, $option['trans_id'], $error);//回滚事务
                        return self::_ERROR_RETURN;
                    } else {
                        $result[] = (int)$CONN->lastInsertId();
                    }
                } catch (\PDOException $PdoError) {
                    $error = $PdoError->getMessage();
                    $this->trans_back($CONN, $option['trans_id'], $error);//回滚事务
                    if ($this->_try_error_throw) {
                        error('PDO_Error :  Execute Insert = ' . print_r($error, true));
                    }
                    return self::_ERROR_RETURN;
                }
            }

            //只有一条的情况下返回一个ID
            return (count($result) === 1) ? $result[0] : $result;

        } else {
            try {
                $run = $CONN->exec($sql);
                if ($run === false) {
                    $error = $CONN->errorInfo()[2];
                    $this->trans_back($CONN, $option['trans_id'], $error);//回滚事务
                    return self::_ERROR_RETURN;
                } else {
                    return (int)$CONN->lastInsertId();//ID
                }
            } catch (\PDOException $PdoError) {
                $error = $PdoError->getMessage();
                $this->trans_back($CONN, $option['trans_id'], $error);//回滚事务
                if ($this->_try_error_throw) {
                    if ($this->_debug) echo $sql;
                    error('PDO_Error :  Exec Insert = ' . print_r($error, true));
                }
                return self::_ERROR_RETURN;
            }
        }
    }


    /**
     * @param \PDO $CONN
     * @param string $sql
     * @param array $option
     * @param $error
     * @return ext\Result|null
     * @throws \Exception
     */
    private function select(\PDO &$CONN, &$sql, array &$option, &$error)
    {
        $fetch = [\PDO::FETCH_NUM, \PDO::FETCH_ASSOC, \PDO::FETCH_BOTH];
        if (!in_array($option['fetch'], [0, 1, 2])) $option['fetch'] = 2;

        if (!empty($option['param']) or $option['prepare']) {
            try {
                //预处理，返回结果允许游标上下移动
                $stmt = $CONN->prepare($sql, [\PDO::ATTR_CURSOR => \PDO::CURSOR_SCROLL]);
                if ($stmt === false) {
                    $error = $CONN->errorInfo();
                    if ($this->_debug) echo $sql;
                    error('PDO_Error :  Prepare Select = ' . print_r($error, true));
                }
            } catch (\PDOException $PdoError) {//执行预处理，如果出错，很少见，还没遇到过
                $error = [$PdoError->getCode(), $PdoError->getMessage()];
                if ($this->_try_error_throw) {
                    if ($this->_debug) echo $sql;
                    error('PDO_Error :  Prepare Select = ' . print_r($error, true));
                }
                return self::_ERROR_RETURN;
            }

            try {
                //返回数据方式：数字索引值，键值对，两者都要
                $stmt->setFetchMode($fetch[$option['fetch']]);//为语句设置默认的获取模式，也就是返回索引，还是键值对

                //如果有字段绑定，输入
                if (!empty($option['bind'])) {
                    foreach ($option['bind'] as $k => &$av) {
                        $stmt->bindColumn($k, $av);
                    }
                }
                $run = $stmt->execute($option['param']);
                if ($run === false) {
                    $error = $stmt->errorInfo();
                    return self::_ERROR_RETURN;
                }
            } catch (\PDOException $PdoError) {
                $error = [$PdoError->getCode(), $PdoError->getMessage()];
                if ($this->_try_error_throw) {
                    if ($this->_debug) echo $sql;
                    error('PDO_Error :  Execute Select = ' . print_r($error, true));
                }
                return self::_ERROR_RETURN;
            }
        } else {
            try {
                $stmt = $CONN->query($sql, $fetch[$option['fetch']]);
                if ($stmt === false) {
                    $error = $CONN->errorInfo();
                    return self::_ERROR_RETURN;
                }
            } catch (\PDOException $PdoError) {
                $error = [$PdoError->getCode(), $PdoError->getMessage()];
                if ($this->_try_error_throw) {
                    if ($this->_debug) echo $sql;
                    error('PDO_Error :  Query Select = ' . print_r($error, true));
                }
                return self::_ERROR_RETURN;
            }
        }
        //查询总数
        $count = $option['count'] ? $CONN->query('SELECT FOUND_ROWS()', \PDO::FETCH_NUM)->fetch()[0] : null;
        return new ext\Result($stmt, $count);
    }


    /**
     * 将日志写入Redis队列，由后台读取写入库
     * @param $sql
     */
    public function saveLog($sql, $title = null)
    {
        if ($title === false or $title === null) return;
        if ($sql) {
            $sql = str_replace('`', '', $sql);
            $sql = preg_replace('/([\x{4e00}-\x{9fa5}]){50,}/iu', '...', $sql);
        }

        $text = [];
        $text['title'] = $title;
        $text['userID'] = 0;//userID
        $text['time'] = time();
        $text['date'] = date('Y-m-d H:i:s');
        $text['ip'] = _IP;
        $text['agent'] = htmlentities(getenv('HTTP_USER_AGENT'));
        $text['sql'] = htmlentities($sql);//htmlentities

        $val = [
            'model' => 'logs',
            'action' => 'logs',
            'value' => $text,
        ];
//        \Asyn::send($val);
//        self::redis('RunData')->table('dbLogs')->zAdd($val);
    }


    public function log($title)
    {
        $this->_logTitle = $title;
    }

}