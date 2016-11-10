<?php

class IndexController extends BaseController
{
    public function indexAction()
    {
//        $this->layout(false);
        $this->title('ESP');

        $this->debug('debug');

//        $this->view(false);

//        error(403);
//        $this->check_host('aba.com');

//        $this->keywords('wbf wide');
//        $this->description('wbf wide');

//        $this->js([
//            'http://cdn.bootcss.com/jquery/1.11.1/jquery.min.js',
//            'http://cdn.bootcss.com/bootstrap/3.3.0/js/bootstrap.min.js',
//            'jquery', 'test.js',
//        ]);
//        $this->css(['http://cdn.bootcss.com/bootstrap/3.3.0/css/bootstrap.min.css']);


//        include 'application/www/models/Article.php';

        $mod = $this->model('article', 1, 3, 54, 6);
//        echo $mod->first();

//        var_dump($this->adapter());

        $this->set('esp', 'Efficient Simple PHP');
//
//        if ($this->reload(['action' => 'abc'])) return;



    }


}