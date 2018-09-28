<?php

namespace www;

use esp\core\Controller;
use esp\core\Output;
use esp\core\Session;

class IndexController extends Controller
{
    /**
     * @throws \Exception
     */
    public function indexAction()
    {
//        var_dump(['session' => Session::get()]);
//        var_dump(['time' => Session::get('time')]);
//        Session::set('day', date('Y-m-d'));
//        Session::set('time', date('H:i:s'));
        Session::empty();
        Session::set('time', date('中国 Y-m-d H:i:s'));
        Session::set('nowsa', time());

        $url = 'http://www.esp.com/server';
        $this->debug('Output::post')->folder('/test');


        throw new \Exception('adafd', 505);
//        trigger_error('中华人民共和国');

//        $this->error('abdafdsf');
//        return Session::get();
    }

    public function errorAction()
    {

        $v = preg_match_all('/([A-Z][a-zA-Z]{4,15}\/\d+\.+\d+)+/', $_SERVER['HTTP_USER_AGENT'], $mac);
        var_dump($v, $mac);
        exit;

    }

    /**
     * 路由控制器运行结束后，会运行这个方法
     * @param void $val 前面控制器返回的内容
     */
    public function _init($action)
    {
//        var_dump(['action' => $action]);
    }

    /**
     * 路由控制器运行结束后，会运行这个方法
     * @param void $val 前面控制器返回的内容
     */
    public function _close($action, $val)
    {
//        var_dump(['action' => $action, 'value' => $val]);
//        $val = ['newVal' => time()];
    }

}