<?php

namespace www;


use esp\core\Controller;
use esp\core\Debug;
use esp\core\Output;

class ToolsController extends Controller
{
    public function indexAction($tools)
    {
        $this->setView("tools/{$tools}.php");
    }

    public function formAction()
    {

    }

    public function uploadAction()
    {
        $data = [];
        $data['name'] = mt_rand();
        $data['files'] = ['debug.lock' => root('/cache/debug.lock')];
//        $data['files'] = root('/cache/debug.lock');
        $option = [];
        $option['type'] = 'upload';

        $api = 'http://www.esp.com/tools/get';

        $val = Output::curl($api, $data, $option);
        pre($val);
        exit;
    }

    public function getAction()
    {
        var_dump($_FILES);
        var_dump($_POST);
        $inputString = trim(file_get_contents("php://input"));

        Debug::relay($_FILES);
        Debug::relay($_POST);
        Debug::relay($inputString);
        exit;
    }

    public function getPost()
    {
        $inputString = trim(file_get_contents("php://input"));
        var_dump($_FILES);
        var_dump($_POST);

        Debug::relay($_FILES);
        Debug::relay($_POST);
        Debug::relay($inputString);

        exit;
    }

}