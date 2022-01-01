<?php
declare(strict_types=1);

namespace esp\core\db\ext;

use PDOStatement;
use function esp\helper\numbers;
use function esp\helper\xml_decode;

final class PdoResult
{
    private $rs;//结果对象
    private $count = 0;
    private $sql;
    private $sum = [];

    /**
     * PdoResult constructor.
     * @param PDOStatement $result
     * @param array $count
     * @param string $sql
     */
    public function __construct(PDOStatement $result, array $count, string $sql)
    {
        $this->rs = $result;
        $this->sql = $sql;
        $this->count = $count['count'] ?? 0;
        unset($count['count']);
        $this->sum = $count;
    }

    public function __destruct()
    {
        $this->rs = null;
    }

    public function __get($key)
    {
        $rs = $this->rs->fetch();
        return $rs->{$key} ?? null;
    }

    /**
     * @return bool
     */
    public function close(): bool
    {
        return $this->rs->closeCursor();
    }

    public function sql(): string
    {
        return $this->sql;
    }

    private function decode($data, $decode)
    {
        if (isset($decode['array'])) $decode['json'] = $decode['array'];
        if (isset($decode['json'])) {
            foreach ($decode['json'] as $k) {
                if (is_int($data[$k[1]])) {
                    $data[$k[0]] = numbers($data[$k[1]]);
                } else {
                    $data[$k[0]] = json_decode(($data[$k[1]] ?? ''), true) ?: [];
                }
            }
        }
        if (isset($decode['xml'])) {
            foreach ($decode['xml'] as $k) $data[$k[0]] = xml_decode(($data[$k[1]] ?? ''), true) ?: [];
        }
        if (isset($decode['time'])) {
            foreach ($decode['time'] as $k) {
                $tm = ($data[$k[1]] ?? 0);
                if ($tm) $data[$k[0]] = date('Y-m-d H:i:s', $tm);
            }
        }
        return $data;
    }


    /**
     * 从结果中返回一行
     * @param null $col
     * @param array $decode
     * @return mixed|null
     */
    public function row($col = null, array $decode = [])
    {
        if (is_null($col)) {
            $data = $this->rs->fetch();
        } else if (is_int($col)) {
            $data = $this->rs->fetchColumn($col);
        } else {
            $data = $this->rs->fetch()[$col] ?? null;
        }
        if (empty($data)) return null;
        if (empty($decode)) return $data;
        return $this->decode($data, $decode);
    }

    /**
     * @param null $col
     * @param array $decode
     * @return mixed|null
     */
    public function fetch($col = null, array $decode = [])
    {
        return $this->row($col, $decode);
    }

    public function sum(): array
    {
        return $this->sum;
    }

    /**
     * 以数组形式返回结果集中的所有行
     *
     * @param int $row
     * @param int|null $col 返回第x列
     * @param array $decode
     * @return array|mixed
     */
    public function rows(int $row = 0, int $col = null, array $decode = [])
    {
        if ($row === 0) {
            //返回所有行，含数字下标和字段下标
            if (is_int($col)) {
                $data = $this->rs->fetchColumn($col);
            } else {
                $data = $this->rs->fetchAll();
            }
        } elseif ($row === 1) {
            //仅返回第1行
            if (is_int($col)) {
                $data = $this->rs->fetchColumn($col);
            } else {
                $data = $this->rs->fetch();
            }
        } else {
            $i = 0;
            $data = array();
            if (is_int($col)) {
                while ($i < $row and $data[] = $this->rs->fetchColumn($col)) $i++;
            } else {
                while ($i < $row and (!!($r = $this->rs->fetch()) and $data[] = $r)) $i++;
            }
        }

        if (empty($data)) return [];
        if (empty($decode)) return $data;

        return array_map(function ($rs) use ($decode) {
            return $this->decode($rs, $decode);
        }, $data);
    }


    /**
     * 当前SQL去除limit的记录总数
     * 但是在构造查询时须加count()方法，否则获取到的只是当前批次的记录数。
     * @return int
     */
    public function count(): int
    {
        return ($this->count === null) ?
            $this->rs->rowCount() :
            $this->count;
    }

    /**
     * 返回当前查询结果的字段列数
     * @return int
     */
    public function column(): int
    {
        return $this->rs->columnCount();
    }

    /**
     * 本次执行是否有错误
     * @return null|string
     */
    public function error(): ?string
    {
        if (!$this->rs->errorCode()) return null;
        return $this->rs->errorCode() . ' ' . json_encode($this->rs->errorInfo());
    }


}