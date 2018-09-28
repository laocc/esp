<?php

namespace www;


use esp\core\Controller;
use esp\core\Input;
use models\MongoModel;


class MongoController extends Controller
{
    public function indexAction()
    {

    }

    public function searchAction()
    {
        if ($this->getRequest()->isPost()) {
            $number = Input::post('number');
            $modMongo = new MongoModel();
            $data = $modMongo->search('index', $number);
            $this->assign('data', $data);
            return;
        }
        $this->assign('data', []);
    }


    public function inAction()
    {
        if ($this->getRequest()->isPost()) {
            $number = Input::post('number', 0);
            $modMongo = new MongoModel();
            $data = $modMongo->inTest($number);
            $this->assign('data', $data);
            return;
        }
        $this->assign('data', []);
    }
}