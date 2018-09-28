<?php

namespace www;


use esp\core\Controller;

class OpcacheController extends Controller
{

    public function emptyAction()
    {
        pre(opcache_get_configuration());
        echo '初始化之前:<hr>';
        pre(opcache_get_status(true));
        opcache_reset();
        echo '初始化之后:<hr>';
        pre(opcache_get_status(true));
        exit;
    }
}