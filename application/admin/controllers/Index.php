<?php
namespace admin;

class IndexController extends BaseController
{
    public function indexAction()
    {
//        $this->title('admin');
        $this->assign('esp','这里是管理中心');
    }
}