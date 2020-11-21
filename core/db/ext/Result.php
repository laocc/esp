<?php
declare(strict_types=1);

namespace esp\core\db\ext;

final class Result
{

    private $rs;//结果对象
    private $count = 0;
    private $sql;

    /**
     * @param \PDOStatement $result
     */
    public function __construct(\PDOStatement $result, $count, $sql)
    {
        $this->rs = $result;
        $this->count = $count;
        $this->sql = $sql;
    }

    public function __destruct()
    {
        $this->rs = null;
    }

    public function __get($key)
    {
        $rs = $this->rs->fetch();
        return isset($rs->{$key}) ? $rs->{$key} : null;
    }

    /**
     * @return bool
     */
    public function close()
    {
        return $this->rs->closeCursor();
    }

    public function sql()
    {
        return $this->sql;
    }


    /**
     * 从结果中返回一行
     * @param null $col
     * @return mixed
     */
    public function row($col = null)
    {
        if (is_null($col)) return $this->rs->fetch();

        else if (is_int($col)) {
            return $this->rs->fetchColumn($col);
        } else {
            return $this->rs->fetch()[$col] ?? null;
        }
    }

    /**
     * @param null $col
     * @return mixed|null
     */
    public function fetch($col = null)
    {
        if (is_null($col)) return $this->rs->fetch();

        else if (is_int($col)) {
            return $this->rs->fetchColumn($col);
        } else {
            return $this->rs->fetch()[$col] ?? null;
        }
    }

    /**
     * 以数组形式返回结果集中的所有行
     * @param int $row
     * @param int $col 返回第x列
     * @return array|mixed
     */
    public function rows(int $row = 0, int $col = null)
    {
        if ($row === 0) {
            //返回所有行，含数字下标和字段下标
            if (is_int($col)) {
                return array_map(function ($v) {
                    return $v;
                }, $this->rs->fetchColumn($col));
            }
            return $this->rs->fetchAll();
        } elseif ($row === 1) {
            //仅返回第1行
            if (is_int($col)) {
                return $this->rs->fetchColumn($col);
            } else {
                return $this->rs->fetch();
            }
        } else {
            $i = 0;
            $val = array();
            if (is_int($col)) {
                while ($i < $row and $val[] = $this->rs->fetchColumn($col)) $i++;
            } else {
                while ($i < $row and (!!($r = $this->rs->fetch()) and $val[] = $r)) {
                    $i++;
                }
            }
            return $val;
        }
    }


    /**
     * 当前SQL去除limit的记录总数
     * 但是在构造查询时须加count()方法，否则获取到的只是当前批次的记录数。
     * @return int
     */
    public function count()
    {
        return ($this->count === null) ?
            $this->rs->rowCount() :
            $this->count;
    }

    /**
     * 返回当前查询结果的字段列数
     * @return int
     */
    public function column()
    {
        return $this->rs->columnCount();
    }

    /**
     * 本次执行是否有错误
     * @return null|string
     */
    public function error()
    {
        if (!$this->rs->errorCode()) return null;
        return $this->rs->errorCode() . ' ' . json_encode($this->rs->errorInfo());
    }


}