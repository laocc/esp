<?php
namespace esp\core\db\ext;


class RedisSort
{

    //========================================有序集合=====================================

    /**
     * 添加有序集合
     */
    public function zAdd($value)
    {
        //创建一个有序集合记录的唯一键
        $timeKey = function ($i = 0) {
            $v = mt_rand(1000, 9999);
            list($s, $m) = explode('.', microtime(1));
            return intval((intval($s) - 1450000000) . str_pad(intval($i), 2, 0) . str_pad($m, 4, 0) . $v);
        };

        if (is_array($value)) {
            $nVal = Array();
            foreach ($value as $k => &$v) {
                $nVal[] = $timeKey($k);
                $nVal[] = ($v);
            }
            return call_user_func_array([$this->redis, 'zAdd'], array_merge([$this->table], $nVal));
        } else {
            return $this->redis->zAdd($this->table, $timeKey(), ($value));
        }
    }

    /**
     * @param int $count
     * @param string $order
     * @param bool $kill
     * @return mixed
     */
    public function zGet($count = 1, $order = 'asc', $kill = true)
    {
        if (is_bool($order)) {
            $kill = $order;
            $order = 'asc';
        }
        $count -= 1;

        if ($order == 'asc') {//顺序
            $val = $this->redis->zRange($this->table, 0, $count);
        } else {//倒序
            $val = $this->redis->zRevRange($this->table, 0 - $count, -1);
        }

        if (!!$kill) {
            if ($order === 'asc') {//按位置删除
                $this->redis->zRemRangeByRank($this->table, 0, $count);
            } else { //按值删除
                call_user_func_array([$this->redis, 'zRem'], array_merge([$this->table], $val));
            }

        }
        return $val;
    }


    //========================================有序集合 END===无序集合==================================


    //添加一个值到table
    public function sAdd($value)
    {
        if (is_array($value)) {
            return $this->redis->sAdd($this->table, ...$value);
//            return call_user_func_array([$this->redis, 'sAdd'], array_merge([$this->table], $value));
        } else {
            return $this->redis->sAdd($this->table, ($value));
        }
    }

    /**
     * 从无序集中读取N个结果
     * @param int $count
     * @param bool|true $kill 是否读出来后就删除
     * @return array|string
     */
    public function sGet($count = 1, $kill = false)
    {
        if ($count === 1 and !!$kill) {//只要一条，且删除，则直接用spop
            $val = $this->redis->sPop($this->table);
            return ($val == false) ? [] : [$val];
        }
        $value = $this->redis->sRandMember($this->table, $count);
        if (!!$kill) {
            call_user_func_array([$this->redis, 'sRem'], array_merge([$this->table], $value));
        }
        return $value;
    }

}