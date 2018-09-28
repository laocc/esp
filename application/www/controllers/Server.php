<?php

namespace www;


use esp\core\Controller;
use esp\core\Cookies;
use esp\core\Session;

class ServerController extends Controller
{
    public function indexAction()
    {
        var_dump(Session::id(true));

        var_dump(Cookies::disable());
        pre($_SERVER);
        exit;
    }

    public function phpinfoAction()
    {
        phpinfo();
        exit;
    }
}

