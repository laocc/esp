<?php

namespace models;

use esp\core\Model;

class TestModel extends Model
{
    public $_table = 'tabTest';
    public $_id = 'testID';
    public $_key = '';

    /**
     * @return int
     */
    public function inTest(int $sum = 1)
    {
        $modMong = new MongoModel();

        $value = Array();
        for ($i = 0; $i < $sum; $i++) {
            $data = Array();
            $data['testTitle'] = str_rand(20, 30);
            $data['testBody'] = str_rand(100);
            $data['testTime'] = time();

            $value[] = $data;
        }
//        $this->error($value);

        return $this->mysql(0, ['persistent' => true])->table('tabTest')->insert($value);
//        return $this->mysql(0, ['persistent' => true])->table('tabTest')->param(true)->insert($value);
    }


}