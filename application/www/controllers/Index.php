<?php

class IndexController extends Controller
{
    public function indexAction()
    {
        $this->title('WBF');
//        error(403);
//        $this->check_host('aba.com');

//        $this->keywords('wbf wide');
//        $this->description('wbf wide');

        $this->js([
            'http://cdn.bootcss.com/jquery/1.11.1/jquery.min.js',
            'http://cdn.bootcss.com/bootstrap/3.3.0/js/bootstrap.min.js',
            'jquery', 'test.js',
        ]);
        $this->css(['http://cdn.bootcss.com/bootstrap/3.3.0/css/bootstrap.min.css']);

        $this->assign('wbf', 'Wide of Ballet FrameWork');

        $arr = [];
        $arr['cto']['name'] = '老船长';
        $arr['cto']['time'] = '2016-1-1';
        $arr['cto'][] = ['sex' => '男'];
        $arr['cto'][] = ['age' => '40'];
        $arr['cto'][]['tel'] = '18801230456';
        $arr['cfo']['name'] = '科比';
        $arr['cfo']['time'] = '2016-2-1';
        $arr['cfo'][] = ['sex' => '男'];
        $arr['cfo'][] = ['age' => '35'];
        $arr['cfo'][]['tel'] = '18801230789';

        $val = [];
        $val['name'] = '科比';
        $val['sex'] = 35;


//        $this->json($val);

    }
}