<?php

namespace cli;

use esp\core\Controller;
use esp\core\Output;
use models\TestModel;

class IndexController extends Controller
{
    public function indexAction()
    {
        $api = 'http://www.esp.com';
        $option = [];
        $option['host'] = '127.0.0.1';
        $option['agent'] = 'chrome';

        $html = Output::curl($api, '', $option);
        print_r($html);
    }
}