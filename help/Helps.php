<?php

namespace esp\help;

use esp\core\Dispatcher;
use esp\error\Error;
use function esp\helper\_table;
use function esp\helper\root;

class Helps
{
    public Dispatcher $_dispatcher;

    public function __construct(Dispatcher $dispatcher)
    {
        $this->_dispatcher = &$dispatcher;
    }

    public function config(string $key = null, $json = null)
    {
        $value = $this->_dispatcher->_config->allConfig();
        if ($key) {
            if ($json) {
                echo json_encode($value[$key] ?? [], 320) . "\n";
            } else {
                print_r($value[$key] ?? null);
            }
        } else {
            if ($json) {
                echo json_encode($value, 320) . "\n";
            } else {
                print_r($value);
            }
        }
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

    public function tables()
    {
        $espCont = new EspController($this->_dispatcher);
        $espModel = new EspModel($espCont);
        $tab = $espModel->tables(false);
        _table($tab);
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