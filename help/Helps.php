<?php

namespace esp\help;

use esp\core\Dispatcher;
use esp\error\Error;
use function esp\helper\root;

class Helps
{
    public Dispatcher $_dispatcher;

    public function __construct(Dispatcher $dispatcher)
    {
        $this->_dispatcher = &$dispatcher;
    }

    public function flush($lev, $safe)
    {
        $value = $this->_dispatcher->_config->flush(intval($lev), strval($safe));
        print_r($value);
    }

    /**
     * @throws Error
     */
    public function model($path, $base)
    {
        if (is_null($path)) $path = '/models';
        if (is_null($base)) $base = '_BaseModel';

        if (!$path) exit("请指定model的保存目录\n");
        if (!is_dir(root($path))) exit("请创建model的保存目录\n" . root($path) . "\n");

        $espCont = new EspController($this->_dispatcher);
        $espModel = new EspModel($espCont);
//        $tab = $espModel->createModel(get_parent_class($espModel));
        $tab = $espModel->createModel($path, $base);
        print_r($tab);
    }

    public function table($table, $dataKey)
    {
        if (!$dataKey) $dataKey = 'data';
        $espCont = new EspController($this->_dispatcher);
        $espModel = new EspModel($espCont);
        $espModel->printTableFields(strval($table), strval($dataKey));
    }

    public function resource($rand)
    {
        if (!$rand) $rand = strval(time() + mt_rand());
        $this->_dispatcher->_config->_Redis->set(_UNIQUE_KEY . '_RESOURCE_RAND_', $rand);
        echo "RESOURCE重置成功\t{$rand}\n";
    }


}