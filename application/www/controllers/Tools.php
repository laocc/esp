<?php

namespace www;


use esp\core\Controller;

class ToolsController extends Controller
{
    public function indexAction($tools)
    {
        $this->setView("tools/{$tools}.php");
    }
}