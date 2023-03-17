<?php

namespace esp\help;

use esp\core\Configure;
use esp\core\Dispatcher;
use esp\core\Request;
use esp\error\Error;
use function esp\helper\root;

class Helps
{
    public Dispatcher $_dispatcher;
    public Configure $_config;
    public Request $_request;
    public \Redis $_redis;//config中创建的redis实例

    public function __construct(Dispatcher $dispatcher)
    {
        $this->_dispatcher = &$dispatcher;
        $this->_config = &$dispatcher->_config;
        $this->_request = &$dispatcher->_request;
        $this->_redis = &$dispatcher->_config->_Redis;
    }

    public function flush($lev)
    {
        $value = $this->_config->flush(intval($lev));
        print_r($value);
    }

    /**
     * @throws Error
     */
    public function model($path = '/models', $base = '_BaseModel')
    {
//        if (!_DEBUG) exit('生产环境禁止此操作');
        if (!$path) exit("请指定model的保存目录\n");
        if (!is_dir(root($path))) exit("请创建model的保存目录\n" . root($path) . "\n");
        if (!$base) $base = '_BaseModel';

        $espModel = new EspModel();
//        $tab = $espModel->createModel(get_parent_class($espModel));
        $tab = $espModel->createModel($path, $base);
        print_r($tab);
    }

    public function table($table, $dataKey = 'data')
    {
        $espModel = new EspModel();
        $espModel->printTableFields(strval($table), strval($dataKey));
    }

    public function resource($rand)
    {
        $this->_redis->set(_UNIQUE_KEY . '_RESOURCE_RAND_', $rand ?: (time() + mt_rand()));
        echo "RESOURCE重置成功\n";
    }


}