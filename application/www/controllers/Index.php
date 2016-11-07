<?php

class IndexController extends Controller
{
    public function indexAction()
    {
        $this->title('WBF');
//        $this->keywords('wbf wide');
//        $this->description('wbf wide');

        $this->js([
            'http://cdn.bootcss.com/jquery/1.11.1/jquery.min.js',
            'http://cdn.bootcss.com/bootstrap/3.3.0/js/bootstrap.min.js',
            'jquery','test.js',
        ]);
        $this->css(['http://cdn.bootcss.com/bootstrap/3.3.0/css/bootstrap.min.css']);

        $this->assign('wbf', 'Wide of Ballet FrameWork');

    }
}