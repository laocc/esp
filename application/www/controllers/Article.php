<?php

namespace www;


use esp\core\Controller;

class ArticleController extends Controller
{
    public function indexAction($info, $test)
    {
        $this->assign('info', $info);
        $this->assign('test', $test);
    }

    public function mdAction()
    {
        $this->md();
    }
}