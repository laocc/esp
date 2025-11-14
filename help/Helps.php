<?php

namespace esp\help;

use esp\core\Dispatcher;
use esp\error\Error;
use function esp\helper\_table;
use function esp\helper\is_json;
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

    public function config(string $key = null, $toJson = false): void
    {
        $value = $this->_dispatcher->_config->allConfig();
        if ($key and ($key !== 'all')) {
            if ($toJson) {
                echo json_encode($value[$key] ?? [], 320) . "\n";
            } else {
                print_r($value[$key] ?? null);
            }
        } else {
            if ($toJson) {
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
     * 8：_HASH_，默认值
     * 32：清空路由
     * 256：清空整个redis表，保留_RESOURCE_RAND_
     * 1024：清空整个redis，需要safe=flushAll
     *
     */
    public function flush($lev, $safe): void
    {
        echo "flush:\n\t1=config(default);\n\t2=cache;\n\t4=resource;\n\t8=hash;\n\t32=route;\n\t256=redis 不含 _RESOURCE_RAND_;\n\t1024=redis，第2参数需=flushAll\n\n";
        $lev = intval($lev);
        $value = $this->_dispatcher->_config->flush($lev, strval($safe));

        if ($lev & 32) {
            $value['route'] = [];
            $dir = new \DirectoryIterator(_RUNTIME);
            foreach ($dir as $f) {
                if ($f->isDot()) continue;
                if (!$f->isFile()) continue;
                $name = $f->getFilename();
                if (preg_match('/^_ROUTES_\w+\#(\w+)\.route$/', $name, $mr)) {
                    unlink($f->getPathname());
                    $value['route'][$mr[1]] = $name;
                }
            }
        }

        print_r($value);
    }


    /**
     * @param string|null $key
     * @param string|null $json
     * @return void
     */
    public function json(string $key = null, string $json = null): void
    {
        if (!_CLI) {
            echo "此方法仅限在CLI中使用";
            return;
        }
        if (is_null($json)) {
            $json = _RUNTIME . '/array.json';
        } else {
            if ($json[0] !== '/') {
                echo "指定的json文件路径名需以/开头，可以是绝对路径，或本项目中的路径，不填则为_RUNTIME/array.json。\n";
                return;
            }
            $json = root($json);
        }
        if (!file_exists($json)) {
            echo "指定的json文件({$json})不存在\n";
            return;
        }
        $json = file_get_contents($json);
        if (!is_json($json)) {
            echo "json字符串不是标准JSON\n";
            return;
        }
        if (is_null($key)) $key = 'data';

        $array = json_decode($json, true);
        $this->printArrayAsPhp($key, $array);
    }

    /**
     * 递归打印数组为 PHP 赋值代码
     *
     * @param string $varName 变量名称
     * @param mixed $value 数组或值
     */
    private function printArrayAsPhp(string $varName, mixed $value): void
    {
        if (is_array($value)) {
            echo "\${$varName} = [];\n";
            foreach ($value as $k => $v) {
                $childName = "{$varName}['{$k}']";
                $this->printArrayAsPhp($childName, $v);
            }
        } else {
            if (is_string($value)) {
                $value = "'" . addslashes($value) . "'";
            } else if (is_null($value)) {
                $value = 'null';
            } else if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }
            echo "\${$varName} = {$value};\n";
        }
    }


    public function reload($lev, $safe): void
    {
        $this->flush($lev, $safe);
    }

    public function model($path, $base): void
    {
        if (is_null($path)) $path = '/models';
        if (is_null($base)) $base = '_BaseModel';

        if (!$path) exit("请指定model的保存目录\n");
        if (!is_dir(root($path))) exit("请创建model的保存目录\n" . root($path) . "\n");

        $espCont = new EspController($this->_dispatcher);
        $espModel = new EspModel($espCont);
        $tab = $espModel->createModel($path, $base);
        print_r($tab);
    }

    /**
     * 显示所有表
     * @return void
     */
    public function tables(): void
    {
        $espCont = new EspController($this->_dispatcher);
        $espModel = new EspModel($espCont);
        $tab = $espModel->tables(false, true);
        _table($tab);
    }

    public function table($table, $dataKey): void
    {
        if (!$dataKey) $dataKey = 'data';
        $espCont = new EspController($this->_dispatcher);
        $espModel = new EspModel($espCont);
        $espModel->printTableFields(strval($table), strval($dataKey));
    }

    public function resource($rand): void
    {
        if (!$rand) $rand = strval(time() + mt_rand());
        $this->_dispatcher->_config->_Redis->set(_UNIQUE_KEY . '_RESOURCE_RAND_', $rand);
        echo "RESOURCE重置成功\t{$rand}\n";
    }


}