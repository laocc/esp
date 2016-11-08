<?php
use esp\core\Route;

class IndexController extends BaseController
{
    public function indexAction()
    {
        echo "<h1>控制器成功！</h1>";

        $file = Article::first();
        require __DIR__ . '/../views/home.php';
    }
}