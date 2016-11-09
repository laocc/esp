<?php
namespace db\ext;

final class Result
{

    private $rs;//结果对象
    private $count = 0;

    /**
     * @param \PDOStatement $result
     */
    public function __construct(\PDOStatement $result, $count)
    {
        $this->rs = $result;
        $this->count = $count;
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

    /**
     * 从结果中返回一行
     * @return array
     */
    public function row()
    {
        return $this->rs->fetch();
    }

    /**
     * @return array
     */
    public function fetch()
    {
        return $this->rs->fetch();
    }

    /**
     * 以数组形式返回结果集中的所有行
     * @return array
     */
    public function rows($row = 0)
    {
        if ($row === 1) return $this->row();
        if ($row === 0) {
            return $this->rs->fetchAll();
        } else {
            $i = 0;
            $val = [];
            while ($i <= $row and $val[] = $this->rs->fetch()) $i++;
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