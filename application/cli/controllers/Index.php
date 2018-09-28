<?php

namespace cli;

use esp\core\Controller;
use models\TestModel;

class IndexController extends Controller
{
    public function indexAction()
    {
        $mod = new TestModel();

        $i = $j = 0;
        while (1) {
            list($s, $m) = explode('.', microtime(true) . '.0');

            $i = $mod->inTest();

            echo date('Y-m-d H:i:s', $s) . ".{$m}\t{$i}\n";

            time_nanosleep(0, 1);
//            time_nanosleep(0, 100000000);
        }
    }
}