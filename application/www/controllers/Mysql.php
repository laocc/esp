<?php

namespace www;


use esp\core\Controller;
use esp\core\Input;
use models\TestModel;

class MysqlController extends Controller
{
    private $page_size = 13;

    public function indexAction()
    {
        return ['abadc' => time()];
    }

    public function tab2Action()
    {
        $mod = new TestModel();
        $limit = Input::get('limit', 0);
        $mod->pageKey('page')->pageSet($limit);

        $where = Array();
        $data = $mod->list($where);
        $resp = [];
        $resp['code'] = 0;
        $resp['msg'] = 'null';
        $resp['count'] = $mod->pageCount();
        $resp['data'] = $data;

        $this->assign('data', $data);
        $this->assign('page', $mod->pageGet());

    }

    public function tab1Action()
    {
        $this->assign('page_size', $this->page_size);
    }

    public function tab1Ajax()
    {
        $mod = new TestModel();
        $limit = Input::get('limit', 0);
        $mod->pageKey('page')->pageSet($limit);

        $where = Array();
        $data = $mod->list($where);
        $resp = [];
        $resp['code'] = 0;
        $resp['msg'] = 'null';
        $resp['count'] = $mod->pageCount();
        $resp['data'] = $data;

        return $resp;
    }

    public function insertAction()
    {
        $this->setView(false);

        $mod = new TestModel();
        $i = $mod->inTest();
        var_dump($i);

    }

}