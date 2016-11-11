<?php
use \esp\core\Main;
use \esp\plugins\Smarty;
use \esp\plugins\Debug;
use \esp\extend\Mistake;
use \esp\extend\Session;

final class Bootstrap
{

    public function _initFirst(Main $main)
    {
        Mistake::init();
        Session::init();
    }


    public function _initDebug(Main $main)
    {
//        $debug = new Debug();
//        $main->setPlugin($debug);
//        $debug->star();
    }


    public function _initSmarty(Main $main)
    {
//        $main->setPlugin(new Smarty());

    }

}