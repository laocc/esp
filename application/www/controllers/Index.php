<?php

namespace www;

use esp\core\Client;
use esp\core\Config;
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
        var_dump(1);
    }

    public function redirectAction($index)
    {
        $a = Config::get('token');
        echo json_encode($a, 256);


        $index = intval($index);
        $index++;
        if ($index === 5) {
            return ['ip' => Client::ip()];
        }
        $this->redirect('/index/redirect/' . $index);
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