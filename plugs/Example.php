<?php

namespace esp\plugs;

use esp\core\Plugin;
use esp\core\Request;
use esp\core\Response;

class Example extends Plugin
{
    private $echo = false;

    public function __construct()
    {

    }

    /**
     * 1.在路由之前触发
     */
    public function routeBefore(Request $request, Response $response)
    {
        if ($this->echo) echo 'routeBefore<br>';
    }

    /**
     * 2.路由结束之后触发
     */
    public function routeAfter(Request $request, Response $response)
    {
        if ($this->echo) echo 'routeAfter<br>';
    }


    /**
     * 3.分发循环开始之前被触发
     */
    public function dispatchBefore(Request $request, Response $response)
    {
        if ($this->echo) echo 'dispatchBefore<br>';
    }

    /**
     * 4.分发之前触发
     */
    public function dispatchAfter(Request $request, Response $response)
    {
        if ($this->echo) echo 'dispatchAfter<br>';
    }

    /**
     * 5.显示开始之前被触发
     */
    public function displayBefore(Request $request, Response $response)
    {
        if ($this->echo) echo 'displayBefore<br>';
//        $response->autoRun(false);
    }

    /**
     * 6.显示之后触发
     */
    public function displayAfter(Request $request, Response $response)
    {
        if ($this->echo) echo 'displayAfter<br>';
    }


    /**
     * 7.结束之后触发
     */
    public function mainEnd(Request $request, Response $response)
    {
        if ($this->echo) echo 'mainEnd<br>';
    }


}