<?php

namespace cli;

use esp\core\Controller;
use models\TestModel;

class IndexController extends Controller
{
    public function indexAction()
    {
        $from = _ROOT . '/cache/debug/2018-11-02-01/';
//        var_dump(move_file($from, $from . '-01'));

        var_dump(strrchr($from, '/'));
    }
}