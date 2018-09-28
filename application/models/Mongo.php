<?php

namespace models;


use esp\core\Config;
use esp\core\db\Mongodb;
use esp\core\Model;

class MongoModel extends Model
{


    /**
     * @param $number
     * @return array|mixed
     */
    public function inTest($number)
    {
        $conf = Config::get('database.mongodb');
        $db = new Mongodb($conf);

        $value = Array();
        $tab = $db->table('tabTest');
        for ($i = 0; $i < $number; $i++) {
            $val = Array();
            $val['index'] = mt_rand(1, 100);
            $val['key'] = str_rand();
            $val['rank'] = mt_rand();
            $value[] = $val;
        }

        return $tab->insert($value, true);
    }


    /**
     * @param $key
     * @param $inValue
     * @return array|mixed
     */
    public function search($key, $inValue)
    {
        $conf = Config::get('database.mongodb');
        $db = new Mongodb($conf);
        $data = $db->table('tabTest')->select('*');
        if ($inValue) {
            $inValue = explode(',', $inValue);
            foreach ($inValue as $i => $v) {
                $inValue[$i] = intval($v);
            }
            $data->where_in($key, $inValue);
        }
        return $data->order('index', 'asc')->get(50);
    }


}