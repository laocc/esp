<?php
declare(strict_types=1);

namespace esp\core\db;

use PDO;
use PDOException;
use esp\core\Controller;
use esp\core\db\ext\Builder;
use esp\core\db\ext\PdoResult;
use esp\core\Debug;
use esp\error\EspError;


final class Mysql
{
    private $_CONF;//配置定义
    private $_trans_run = array();//事务状态
    private $_trans_error = array();//事务出错状态
    private $connect_time = array();//连接时间
    private $transID;
    private $_checkGoneAway = false;
    private $_cli_print_sql = false;
    private $_debug;
    private $_counter;
    private $_PDO;
    public $_error = array();//每个连接的错误信息
    public $dbName;
    public $cacheKey;
    public $lowCase = false; //是否转换为小写

    /**
     * @var $_controller Controller
     */
    public $_controller;

    /**
     * Mysql constructor.
     * @param Controller $controller
     * @param int $tranID
     * @param array|null $conf
     * @throws EspError
     */
    public function __construct(Controller $controller, int $tranID = 0, array $conf = null)
    {
        if (is_array($tranID)) list($tranID, $conf) = [0, $tranID];
        if (!is_array($conf)) throw new EspError('Mysql配置信息错误', 1);
        $this->_CONF = $conf + [
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_general_ci',
//                'persistent' => false,//是否持久连接，不指定时取值_CLI
                'param' => true,
                'cache' => false,
                'lowercase' => false,
                'timeout' => 2,
                'time_limit' => 5,//单条sql超时报警
                'prefix' => '',
            ];
        $this->transID = $tranID;
        $this->_checkGoneAway = _CLI;

        $this->_controller = &$controller;
        $this->_debug = &$controller->_debug;
        $this->_counter = &$controller->_counter;

        $this->lowCase = boolval($this->_CONF['lowercase'] ?? 0);
        if ($this->_CONF['lowercase'] ?? 0) {
            $this->_CONF['db'] = strtolower($this->_CONF['db']);
            $this->_CONF['prefix'] = strtolower($this->_CONF['prefix']);
        }
        if ($this->_CONF['cache']) {
            if (is_string($this->_CONF['cache'])) {
                $this->cacheKey = $this->_CONF['cache'];
            } else {
                $this->cacheKey = $this->_CONF['db'];
            }
        }
        $this->dbName = $this->_CONF['db'];
    }

    /**
     * 统计执行sql并发
     *
     * @param string $action
     * @param string $sql
     * @param int $traceLevel
     */
    public function counter(string $action, string $sql, int $traceLevel)
    {
        if (!$this->_counter) return;
        if ($traceLevel === -1) $this->_counter->recodeMysql($action, $sql);
        $this->_counter->recodeMysql($action, $sql, $traceLevel + 1);
    }

    /**
     * @param string $tabName
     * @param bool|null $_protect
     * @return Builder
     * @throws EspError
     */
    public function table(string $tabName, bool $_protect = null): Builder
    {
        if (!is_string($tabName) || empty($tabName)) {
            throw new EspError('PDO_Error :  数据表名错误', 1);
        }
        $bud = new Builder($this, $this->_CONF['prefix'], boolval($this->_CONF['param'] ?? 0), $this->lowCase, $this->transID);
        return $bud->table($tabName, $_protect);
    }

    public function print(bool $boolPrint = false): Mysql
    {
        $this->_cli_print_sql = $boolPrint;
        return $this;
    }

    /**
     * @param $value
     * @param int $traceLevel
     * @return false|null|Debug
     */
    public function debug($value = '_RETURN_DEBUG_', int $traceLevel = 1)
    {
        if (is_null($this->_debug)) return null;
        if ($value === '_RETURN_DEBUG_') return $this->_debug;
        if ($traceLevel > 10) $traceLevel = 2;
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, ($traceLevel + 1));
        $trace = $trace[$traceLevel] ?? [];
        return $this->_debug->mysql_log($value, $trace);
    }

    /**
     * @param bool $upData
     * @param int $trans_id
     * @param int $traceLevel
     * @param int $try
     * @return void
     * @throws EspError
     */
    private function connect(bool $upData, int $trans_id = 0, int $traceLevel = 0, int $try = 0): void
    {
        if (!is_null($this->_PDO)) return;

        $real = $upData ? 'master' : 'slave';
        if (!$upData and !isset($this->_CONF['slave'])) $real = 'master';

        $cnf = $this->_CONF;
        if (!$upData) {
            $host = $cnf['slave'] ?? $cnf['master'];

            //不是更新操作时，选择从库，需选择一个点
            if (is_array($host)) {
                $host = $host[ip2long(_CIP) % count($host)];
            }
        } else {
            $host = $cnf['master'];
        }

        //自动提交事务=false，默认true,如果有事务ID，则为该事务的状态反值
        if (isset($this->_trans_run[$trans_id])) {
            $autoCommit = !$this->_trans_run[$trans_id];
        } else {
            $autoCommit = true;
        }

        try {
            $opts = array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT,//错误等级
                PDO::ATTR_AUTOCOMMIT => $autoCommit,//自动提交事务=false，默认true,如果有事务ID，则为false
                PDO::ATTR_EMULATE_PREPARES => false,//是否使用PHP本地模拟prepare,禁止
                PDO::ATTR_PERSISTENT => boolval($cnf['persistent'] ?? 0),//是否启用持久连接
                PDO::ATTR_TIMEOUT => intval($cnf['timeout']), //设置超时时间，秒，默认=2
            );
            if ($host[0] === '/') {//unix_socket
                $conStr = "mysql:dbname={$cnf['db']};unix_socket={$host};charset={$cnf['charset']};id={$trans_id};";
            } else {
                $port = 3306;
                if (strpos($host, ':') > 0) list($host, $port) = explode(':', "{$host}:3306", 2);
                $conStr = "mysql:dbname={$cnf['db']};host={$host};port={$port};charset={$cnf['charset']};id={$trans_id};";
            }

            try {

                $this->_PDO = new PDO($conStr, $cnf['username'], $cnf['password'], $opts);
                (!_CLI) and $this->debug("{$real}({$trans_id}):{$conStr}");
                if (_CLI and defined('_EchoNewPdo') and _EchoNewPdo) echo "new PDO::{$real}({$trans_id})\n";

                if (_CLI and $try > 0) {
                    print_r([$opts, $cnf, $conStr]);
                }

            } catch (PDOException $PdoError) {
                $err = [];
                $err['code'] = $PdoError->getCode();
                $err['msg'] = $PdoError->getMessage();
                $err['host'] = $host;
                throw new EspError("Mysql Connection failed:" . json_encode($err, 256 | 64), 1, 1);
            }
            $this->connect_time[$trans_id] = time();

        } catch (PDOException $PdoError) {
            /*
             *信息详细程度取决于$opts里PDO::ATTR_ERRMODE =>
             * PDO::ERRMODE_SILENT，只简单地设置错误码，默认值
             * PDO::ERRMODE_WARNING： 还将发出一条传统的 E_WARNING 信息，
             * PDO::ERRMODE_EXCEPTION，还将抛出一个 PDOException 异常类并设置它的属性来反射错误码和错误信息，
            */
            throw new EspError("Mysql Connection failed:" . $PdoError->getCode() . ',' . $PdoError->getMessage(), 1, 1);
        }
    }


    /**
     * 从SQL语句中提取该语句的执行性质
     * @param string $sql
     * @return mixed
     * @throws EspError
     */
    public function sqlAction(string $sql)
    {
        if (preg_match('/^(select|insert|replace|update|delete|alter|analyze)\s+.+/is', trim($sql), $matches)) {
            return strtolower($matches[1]);
        } else {
            throw new EspError("PDO_Error:SQL语句不合法:{$sql}");
        }
    }

    public function quote($string)
    {
        return $this->_PDO->quote($string);
    }


    /**
     * 直接执行SQL
     * @param string $sql
     * @param array $param
     * @return bool|null
     * @throws EspError
     */
    private function query_dddd(string $sql, array $param = [])
    {
        $option = [
            'param' => $param,
            'prepare' => true,
            'count' => false,
            'fetch' => 1,
            'bind' => [],
            'trans_id' => 0,
            'action' => $this->sqlAction($sql),
        ];
        return $this->query($sql, $option);
    }

    /**
     * 执行sql
     * 此方法内若发生错误，必须以string返回
     * @param string $sql
     * @param array $option
     * @param PDO|null $CONN
     * @param int $traceLevel
     * @return bool|string|int|PdoResult
     * @throws EspError
     */
    public function query(string $sql, array $option = [], PDO $CONN = null, int $traceLevel = 0)
    {
        if (empty($sql)) {
            throw new EspError("PDO_Error :  SQL语句不能为空", $traceLevel + 1);
        }
        if (_CLI and $this->_cli_print_sql) echo "{$sql}\n";

        if (empty($option) or !isset($option['trans_id']) or !isset($option['action']) or !isset($option['param'])) {
            $option = [
                'param' => $option,
                'prepare' => true,
                'count' => false,
                'fetch' => 1,
                'limit' => 0,
                'bind' => [],
                'trans_id' => 0,
                'action' => $this->sqlAction($sql),
            ];
        }

        $action = strtolower($option['action']);
        $transID = ($option['trans_id']);

        if (!in_array($action, ['select', 'insert', 'replace', 'update', 'delete', 'alter', 'analyze'])) {
            throw new EspError("PDO_Error :  数据处理方式不明确：【{$action}】。", $traceLevel + 1);
        }

        //是否更新数据操作
        $upData = ($action !== 'select');
        $real = $upData ? 'master' : 'slave';
        //这4种操作要换成当前类中的对应操作方法
        switch ($action) {
            case 'delete':
                $action = 'update';
                break;
            case 'replace':
                $action = 'insert';
                break;
            case 'alter':
            case 'analyze':
                $action = 'select';
                break;
        }

        $try = 0;
        tryExe://重新执行起点

        //连接数据库，自动选择主从库
        if (!$CONN) {
            if (is_null($this->_PDO)) $this->connect($upData, $transID, $traceLevel + 1, $try);
        }

        $CONN = &$this->_PDO;

        if ($this->_checkGoneAway and $this->connHasGoneAway($transID, $real, $CONN, $try++)) {
            echo "Mysql has gone away, try Now!\n";
            goto tryExe;
        }

        $debug = true;
        $debug_sql = (($option['debug_sql'] ?? null) !== false);

        //数据操作时，若当前`trans_run`=false，则说明刚才被back过了或已经commit，后面的数据不再执行
        //更新操作，有事务ID，在运行中，且已被标识为false
        if ($upData and $transID and (!($this->_trans_run[$transID] ?? 0) or !$this->trans_in($CONN, $transID))) {
            return null;
        }

        $error = array();//预置的错误信息

        $debugOption = [
            'trans' => var_export($transID, true),
            'server' => $CONN->getAttribute(PDO::FETCH_COLUMN),//服务器IP
            'sql' => $sql,
            'prepare' => (!empty($option['param']) or $option['prepare']) ? 'YES' : 'NO',
            'param' => json_encode($option['param'], 256 | 64),
            'ready' => microtime(true),
        ];
        $result = $this->{$action}($CONN, $sql, $option, $error, $traceLevel + 1);//执行
        $debugOption += [
            'finish' => $time_b = microtime(true),
            'runTime' => ($time_b - $debugOption['ready']) * 1000,
            'result' => is_object($result) ? 'Result' : var_export($result, true),
        ];
        if (($option['limit'] ?? 0) > 0 and $debug and !_CLI and $debugOption['runTime'] > $option['limit']) {
            $trueSQL = str_replace(array_keys($option['param']), array_map(function ($v) {
                return is_string($v) ? "'{$v}'" : $v;
            }, array_values($option['param'])), $sql);

            $this->debug($debugOption, $traceLevel + 1)->error([
                "SQL耗时超过限定的{$option['limit']}ms", $debugOption, $trueSQL
            ], $traceLevel + 1);
        }

        if (!empty($error)) {
            $debugOption['error'] = $error;
            $this->_error[$transID] = $error;

            $errState = intval($error[1]);
            _CLI and print_r(['try' => $try, 'error' => $errState]);

            if ($debug and !_CLI) {
                $this->debug($debugOption, $traceLevel + 1)->error($error, $traceLevel + 1);
            }

            if ($try++ < 2 and in_array($errState, [2002, 2006, 2013])) {
                if (_CLI) {
                    print_r($debugOption);
                    print_r([
                        'id' => $transID,
                        'connect_time' => $this->connect_time[$transID] ?? 0,
                        'now' => time(),
                        'after' => time() - $this->connect_time[$transID] ?? 0,
                    ]);
                } else {
                    ($debug and !_CLI) and $this->debug($debugOption, $traceLevel + 1);
                }
                $CONN = null;
                goto tryExe; //重新执行

            } else if ($transID > 0 and $upData) {
                $this->trans_back($transID, $error);//回滚事务
            }
            if ($debug) $error['sql'] = $sql;
            if (_CLI) print_r($debugOption);
            ($debug and !_CLI) and $this->debug($debugOption, $traceLevel + 1);
            return json_encode($error, 256 | 64);
        }
        ($debug and $debug_sql and !_CLI) and $this->debug($debugOption, $traceLevel + 1);
        return $result;
    }

    private function connHasGoneAway(int $transID, string $real, PDO $CONN, int $try): bool
    {
        if (!_CLI or $try > 1) return false;
        if (!$CONN->getAttribute(PDO::ATTR_PERSISTENT)) return false;

        $time = time();

        try {

            $info = $CONN->getAttribute(PDO::ATTR_SERVER_INFO);

        } catch (\Error $error) {
            ////获取属性出错，PHP Warning:  PDO::getAttribute(): MySQL server has gone away in
            print_r([
                'id' => $transID,
                'connect_time' => $this->connect_time[$transID] ?? 0,
                'now' => $time,
                'wait' => $time - $this->connect_time[$transID] ?? 0,
                'error' => $error->getMessage(),
                'code' => $error->getCode(),
            ]);
            print_r($this->PdoAttribute($CONN));
            $CONN = null;
            if ($try) throw new EspError($error->getMessage(), 1, 1);
            return true;
        }

        if (empty($info)) {//获取不到有关属性，说明连接可能已经断开
            print_r([
                'id' => $transID,
                'connect_time' => $this->connect_time[$transID] ?? 0,
                'now' => $time,
                'wait' => $time - $this->connect_time[$transID] ?? 0,
            ]);
            print_r($this->PdoAttribute($CONN));
            $CONN = null;
            if ($try) throw new EspError('服务器状态错误，且无法连接成功', 1, 1);
            return true;
        }
        return false;
    }

    private function PdoAttribute(PDO $pdo): array
    {
        $attributes = array(
            'PARAM_BOOL', 'PARAM_NULL', 'PARAM_LOB', 'PARAM_STMT', 'FETCH_NAMED', 'FETCH_NUM', 'FETCH_BOTH', 'FETCH_OBJ', 'FETCH_BOUND', 'FETCH_COLUMN', 'FETCH_CLASS', 'FETCH_KEY_PAIR',
            'ATTR_AUTOCOMMIT', 'ATTR_ERRMODE', 'ATTR_SERVER_VERSION', 'ATTR_CLIENT_VERSION', 'ATTR_SERVER_INFO', 'ATTR_CONNECTION_STATUS', 'ATTR_CASE', 'ATTR_DRIVER_NAME', 'ATTR_ORACLE_NULLS', 'ATTR_PERSISTENT',
            'ATTR_STATEMENT_CLASS', 'ATTR_DEFAULT_FETCH_MODE', 'ATTR_EMULATE_PREPARES', 'ERRMODE_SILENT', 'CASE_NATURAL', 'NULL_NATURAL', 'FETCH_ORI_NEXT', 'FETCH_ORI_LAST',
            'FETCH_ORI_ABS', 'FETCH_ORI_REL', 'CURSOR_FWDONLY', 'ERR_NONE', 'PARAM_EVT_ALLOC', 'PARAM_EVT_EXEC_POST', 'PARAM_EVT_FETCH_PRE', 'PARAM_EVT_FETCH_POST', 'PARAM_EVT_NORMALIZE',
        );
        $attr = [];
        foreach ($attributes as $val) {
            $it = constant("\PDO::{$val}");
            if (is_int($it)) {
                $attr["PDO::{$val}"] = $pdo->getAttribute($it);
            } else {
                $attr["PDO::{$val}"] = $it;
            }
        }
        return $attr;
    }

    /**
     * @param PDO $CONN
     * @param string $sql
     * @param array $option
     * @param $error
     * @param int $traceLevel
     * @return int|null
     */
    private function update(PDO $CONN, string $sql, array &$option, &$error, int $traceLevel): ?int
    {
        if (!empty($option['param']) or $option['prepare']) {
            try {
                $stmt = $CONN->prepare($sql, [PDO::MYSQL_ATTR_FOUND_ROWS => true]);
                if ($stmt === false) {//预处理时就出错，一般是不应该的，有可能是字段名不对等等
                    $error = $CONN->errorInfo();
                    $stmt = null;
                    return null;
                }
            } catch (PDOException $PdoError) {//执行预处理，如果出错，很少见，还没遇到过
                $error = $PdoError->errorInfo;
                $stmt = null;
                return null;
            }
            try {
                $run = $stmt->execute($option['param']);
                $this->counter('update', $sql, $traceLevel + 1);
                if ($run === false) {//执行预处理过的内容，如果不成功，多出现传入的值不符合字段类型的情况
                    $error = $stmt->errorInfo();
                    $stmt = null;
                    return null;
                }
            } catch (PDOException $PdoError) {//执行预处理过的SQL，如果出错，很少见，还没遇到过
                $error = $PdoError->errorInfo;
                return null;
            }
            $rowCount = $stmt->rowCount();
            $stmt = null;
            return $rowCount;//受影响的行数
        } else {
            try {
                $run = $CONN->exec($sql);
                $this->counter('update', $sql, $traceLevel + 1);
                if ($run === false) {
                    $error = $CONN->errorInfo();
                    return null;
                } else {
                    return $run;//受影响的行数
                }
            } catch (PDOException $PdoError) {
                $error = $PdoError->errorInfo;
                return null;
            }
        }
    }

    /**
     * 最后插入的ID，若批量插入则返回值是数组
     * @param PDO $CONN
     * @param string $sql
     * @param array $option
     * @param $error
     * @param int $traceLevel
     * @return array|int|mixed|null
     */
    private function insert(PDO $CONN, string $sql, array &$option, &$error, int $traceLevel)
    {
        if (!empty($option['param']) or $option['prepare']) {
            $result = array();
            try {
                $stmt = $CONN->prepare($sql);
                if ($stmt === false) {
                    $error = $CONN->errorInfo();
                    $stmt = null;
                    return null;
                }
            } catch (PDOException $PdoError) {//执行预处理，如果出错，很少见，还没遇到过
                $error = $PdoError->errorInfo;
                $stmt = null;
                return null;
            }
            if (!empty($option['param'])) {//有后续参数
                foreach ($option['param'] as &$row) {
                    try {
                        $run = $stmt->execute($row);
                        $this->counter('insert', $sql, $traceLevel + 1);
                        if ($run === false) {
                            $error = $stmt->errorInfo();
                            $stmt = null;
                            return null;
                        } else {
                            $result[] = (int)$CONN->lastInsertId();//最后插入的ID
                        }
                    } catch (PDOException $PdoError) {
                        $error = $PdoError->errorInfo;
                        $stmt = null;
                        return null;
                    }
                }
            } else {//无后续参数
                try {
                    $run = $stmt->execute();
                    $this->counter('insert', $sql, $traceLevel + 1);
                    if ($run === false) {
                        $error = $stmt->errorInfo();
                        $stmt = null;
                        return null;
                    } else {
                        $result[] = $CONN->lastInsertId();
                    }
                } catch (PDOException $PdoError) {
                    $error = $PdoError->errorInfo;
                    return null;
                }
            }
            $stmt = null;
            //只有一条的情况下返回一个ID
            return (count($result) === 1) ? $result[0] : $result;

        } else {
            try {
                $run = $CONN->exec($sql);
                $this->counter('insert', $sql, $traceLevel + 1);
                if ($run === false) {
                    $error = $CONN->errorInfo();
                    return null;
                } else {
                    return (int)$CONN->lastInsertId();
                }
            } catch (PDOException $PdoError) {
                $error = $PdoError->errorInfo;
                return null;
            }
        }
    }


    /**
     * @param PDO $CONN
     * @param string $sql
     * @param array $option
     * @param $error
     * @param int $traceLevel
     * @return PdoResult|null
     */
    private function select(PDO $CONN, string &$sql, array &$option, &$error, int $traceLevel): ?PdoResult
    {
        $fetch = [PDO::FETCH_NUM, PDO::FETCH_ASSOC, PDO::FETCH_BOTH];
        if (!in_array($option['fetch'], [0, 1, 2])) $option['fetch'] = 2;
        $count = [];
        if (!empty($option['param']) or $option['prepare']) {
            try {
                //预处理，返回结果允许游标上下移动
                $stmt = $CONN->prepare($sql, [PDO::ATTR_CURSOR => PDO::CURSOR_SCROLL]);
                if ($stmt === false) {
                    $error = $CONN->errorInfo();
                    $stmt = null;
                    return null;
                }
            } catch (PDOException $PdoError) {//执行预处理，如果出错，很少见，还没遇到过
                $error = $PdoError->errorInfo;
                $stmt = null;
                return null;
            }

            try {
                //返回数据方式：数字索引值，键值对，两者都要
                //为语句设置默认的获取模式，也就是返回索引，还是键值对
                $stmt->setFetchMode($fetch[$option['fetch']]);

                //如果有字段绑定，输入
                if (!empty($option['bind'])) {
                    foreach ($option['bind'] as $k => &$av) {
                        $stmt->bindColumn($k, $av);
                    }
                }
                $run = $stmt->execute($option['param']);
                $this->counter('select', $sql, $traceLevel + 1);
                if ($run === false) {
                    $error = $stmt->errorInfo();
                    return null;
                }

                if ($option['count']) {
                    $stmtC = $CONN->prepare($option['_count_sql']);
                    if (!empty($option['bind'])) {
                        foreach ($option['bind'] as $k => &$av) {
                            $stmtC->bindColumn($k, $av);
                        }
                    }
                    if ($stmtC === false) {
                        $error = $CONN->errorInfo();
                        return null;
                    }
                    $a = microtime(true);
                    $stmtC->execute($option['param']);
                    $this->counter('select', $sql, -1);
                    if (!_CLI && (microtime(true) - $a) > $this->_CONF['time_limit']) {
                        $this->debug()
                            ->relay($option['_count_sql'])
                            ->error("SQL count 超时{$this->_CONF['time_limit']}s");
                    }
                    $count = $stmtC->fetch(PDO::FETCH_ASSOC);
                }


            } catch (PDOException $PdoError) {
                $error = $PdoError->errorInfo;
                $stmt = null;
                return null;
            }
        } else {
            try {
                $stmt = $CONN->query($sql, $fetch[$option['fetch']]);
                $this->counter('select', $sql, $traceLevel + 1);
                if ($stmt === false) {
                    $error = $CONN->errorInfo();
                    return null;
                }

                if ($option['count']) {
                    $a = microtime(true);
                    $count = $CONN->query($option['_count_sql'], PDO::FETCH_ASSOC)->fetch();
                    $this->counter('select', $sql, -1);
                    if (!_CLI && (microtime(true) - $a) > $this->_CONF['time_limit']) {
                        $this->debug()->relay($option['_count_sql'])
                            ->error("SQL count 超时{$this->_CONF['time_limit']}s");
                    }
                }


            } catch (PDOException $PdoError) {
                $error = $PdoError->errorInfo;
                return null;
            }
        }

        if (is_bool($count)) {
            $error = $CONN->errorInfo();
            if ($error[0] === '00000') $error[0] = '合计计数出错，可能是sql语句执行错误，请检查sql语句';
            return null;
        }

        return new PdoResult($stmt, $count, $sql);
    }


    /**
     * 暂未实现ping
     * @return bool
     */
    public function ping(): bool
    {
        return true;
    }

    /**
     * ---------------------------------------------------------------------------------------------
     */


    /**
     * 创建事务开始，或直接执行批量事务
     * @param int $trans_id
     * @param array $batch_SQLs
     * @return bool|Builder
     * @throws EspError
     */
    public function trans(int $trans_id = 0, array $batch_SQLs = [])
    {
        if ($trans_id === 0) {
//            throw new EspError("Trans Error: 事务ID须从1开始，不可以为0。", 1);
        }

        if (isset($this->_trans_run[$trans_id]) and $this->_trans_run[$trans_id]) {
            throw new EspError("Trans Begin Error: 当前正处于未完成的事务{$trans_id}中，或该事务未正常结束", 1);
        }

        $try = 0;
        tryExe:
        $real = 'master';

        if (is_null($this->_PDO)) $this->connect(true, $trans_id);//连接数据库，直接选择主库

        if (_CLI and $this->connHasGoneAway($trans_id, $real, $this->_PDO, $try++)) {
            echo "Mysql has gone away, try Now!\n";
            goto tryExe;
        }

        if ($this->_PDO->inTransaction()) {
            throw new EspError("Trans Begin Error: 当前正处于未完成的事务{$trans_id}中", 1);
        }

        if (!$this->_PDO->beginTransaction()) {
            throw new EspError("PDO_Error :  启动事务失败。", 1);
        }

        $this->_trans_run[$trans_id] = true;
        $this->_trans_error = [];
        /**
         * 直接批量事务
         */
        if (!empty($batch_SQLs)) {
            foreach ($batch_SQLs as $sql) {
                $action = $this->sqlAction($sql);
                $option = [
                    'param' => false,
                    'prepare' => true,
                    'count' => false,
                    'fetch' => 0,
                    'bind' => [],
                    'trans_id' => $trans_id,
                    'action' => $action,
                ];
                $this->query($sql, $option, $this->_PDO, 1);
            }
            $this->_trans_run[$trans_id] = false;
            return $this->_PDO->commit();
        }

        return new Builder($this, $this->_CONF['prefix'], boolval($this->_CONF['param'] ?? 0), $this->lowCase, $trans_id);
    }

    /**
     * 提交事务
     * @param int $trans_id
     * @return array|bool
     * @throws EspError
     */
    public function trans_commit(int $trans_id)
    {
        if (isset($this->_trans_run[$trans_id]) and $this->_trans_run[$trans_id] === false) {
            if (!empty($this->_trans_error)) return $this->_trans_error;
            return false;
        }

        if (!$this->_PDO->inTransaction()) {
            print_r(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3));
            throw new EspError("mysql Trans Commit Error: 当前没有处于事务[{$trans_id}]中", 1);
        }

        $this->_trans_run[$trans_id] = false;
        return $this->_PDO->commit();
    }

    /**
     * 回滚事务
     * @param int $trans_id
     * @param null $error
     * @return bool
     */
    public function trans_back(int $trans_id = 0, $error = null): bool
    {
        $this->_trans_run[$trans_id] = false;
        /**
         * @var $CONN PDO
         */
        if (!$this->_PDO->inTransaction()) return true;

        $this->_trans_error = [
            'wait' => 0,
            'trans' => $trans_id,
            'sql' => 'rollBack',
            'prepare' => null,
            'param' => null,
            'result' => true,
            'error' => $error[2] ?? json_encode($error, 256 | 64),
        ];
        !_CLI and $this->debug($this->_trans_error);

        return $this->_PDO->rollBack();
    }

    /**
     * 检查当前连接是否还在事务之中
     * @param PDO $CONN
     * @param $trans_id
     * @return bool
     */
    public function trans_in(PDO $CONN, $trans_id): bool
    {
        return $CONN->inTransaction();
    }

}