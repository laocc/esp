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
        $option['dns'] = ['www.esp.com:80:127.0.0.1:80'];

        $html = Output::curl($api, '', $option);
        print_r($html);
    }
}