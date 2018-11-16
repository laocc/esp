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
        $this->redirect('/mysql/tab1');
    }

    public function tab2Action()
    {
        $mod = new TestModel();

        $where = Array();
        $data = $mod->list($where);

        $resp = [];
        $resp['code'] = 0;
        $resp['msg'] = 'null';
        $resp['count'] = $mod->page()->count();
        $resp['data'] = $data;

        $this->assign('data', $data);
        $this->assign('page', $mod->page()->html('pageForm'));
    }

    public function tab1Action()
    {
        $this->assign('page_size', $this->page_size);
    }

    public function tab1Ajax()
    {
        $limit = Input::get('limit', 10);
        $mod = new TestModel();
        $mod->page($limit, 0, 'page');

        $where = Array();
        $data = $mod->list($where);
        $resp = [];
        $resp['code'] = 0;
        $resp['msg'] = 'null';
        $resp['count'] = $mod->page()->Count();
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