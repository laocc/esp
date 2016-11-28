<?php
namespace www;

use esp\core\Config;
use esp\core\Model;
use laocc\dbs\Memcache;

class ArticleModel extends Model
{

    public function first()
    {
        $arr = Config::get('memcache');
        $a = new Memcache($arr);

        $a->set('test', time());
        return $a->get('test');
    }
}