<?php

class IndexController extends Controller
{
    public function indexAction()
    {

//        $this->layout('layout_sim.phtml');
//        $this->layout(false);

        $this->assign('time', 'from controller');
        $this->view()->assign('time', 'from view');
        $this->adapter()->assign('time', 'from adapter');
//        $this->adapter(false);

    }
}