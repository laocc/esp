<?php

namespace esp\help;

use esp\core\Dispatcher;
use esp\error\Error;
use function esp\helper\_table;
use function esp\helper\root;

class Helps
{
    public Dispatcher $_dispatcher;
    private string $controller;

    public function __construct(Dispatcher $dispatcher, string $controller)
    {
        $this->_dispatcher = &$dispatcher;
        $this->controller = $controller;
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

    /**
     * @param $lev
     * @param $safe
     * @return void
     *
     * 可以在url中加参数清除：
     * ?_flush_key=XXX&_config_load=NNN   XXX为 _RUNTIME/flush_key.txt内容，NNN=以下值，不含1024
     * ?_flush_key=XXX&_router_load=NNN   XXX为 _RUNTIME/flush_key.txt内容，NNN&32时，清空所有路由缓存
     *
     * $lev:
     * 1：只清_CONFIG_，默认值
     * 2：_MYSQL_CACHE_
     * 4：_RESOURCE_RAND_
     * 32：清空路由
     * 256：清空整个redis表，保留_RESOURCE_RAND_
     * 1024：清空整个redis，需要safe=flushAll
     *
     */
    public function flush($lev, $safe)
    {
        $lev = intval($lev);
        $value = $this->_dispatcher->_config->flush($lev, strval($safe));

        if ($lev & 32) {
            $value['route'] = [];
            $dir = new \DirectoryIterator(_RUNTIME);
            foreach ($dir as $f) {
                if (!$f->isFile()) continue;
                $name = $f->getFilename();
                if (preg_match('/^_ROUTES_\w+\#(\w+)\.route$/', $name, $mr)) {
                    unlink($name);
                    $value['route'][$mr[1]] = $name;
                }
            }
        }

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