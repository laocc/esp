<?php

class IndexController extends Controller
{
    public function indexAction()
    {

//        $this->layout('layout_sim.phtml');
        $this->layout(false);

        $this->title('index');
        $this->keywords('wbf wide');
        $this->description('wbf wide');

        $this->js(['jquery', 'myjs']);
        $this->js(['jquery', 'myjs'], 'head');
        $this->js(['jquery', 'myjs'], 'body');

        $this->css(['wbf', 'cne']);

        $val = file_get_contents('http://qyxy.baic.gov.cn/dito/ditoAction!ycmlFrame.dhtml?clear=true');
//        $val = file_get_contents('http://qyxy.baic.gov.cn/gjjbj/gjjQueryCreditAction!openEntInfo.dhtml?entId=20e38b8b511f406101512263a43f17dd&credit_ticket=FE1EE32D2A8DBE653DF743F0C47CD6C5&entNo=110109020213687&type=jyycDiv&timeStamp=1478507252110');

        $this->assign('time', $val);

    }
}