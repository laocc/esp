<?php
namespace www;

use esp\core\Model;

class Article extends Model
{
    public static function first()
    {
        return __FILE__ ;
    }
}