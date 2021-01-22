<?php

namespace esp\library\ext;

use esp\error\EspError;

/**
 * 仅仅是对Xdebug的结果进行可读重排版，不是用来控制Xdebug的
 * 至于Xdebug是否启动，取于决于URL中有没有加【?XDEBUG_TRACE】
 * Class Xdebug
 * @package plugins\ext
 */

class Xdebug
{
    const showArray = false;//是否每一行后显示详细的信息

    private $index = 0;//函数计数器
    private $value = Array();//数据分析结果
    private $memory = null;//程序开始时的初始内存
    private $useReturn = false;//测试xDebug有没有记录函数返回值
    private $used = Array();//记录内存消耗量
    private $wait = Array();//记录耗时量
    private $color = Array();//最消耗的记录的颜色
    private $pathSam = Array();//记录所有记录中文件路径相同部分，最终显示时会跳过这部分
    private $firstLevel = null;//中转变量，记录第一行的（空格+时间+内存）的字符串长度
    private $_min_wait = 2; //被认为过耗最小时间，毫秒，建议2毫秒以上；
    private $_min_used = 10;//被认定过耗最少内存，KB，建议10KB以上；
    private $_skip;//不显示debug类本身的执行过程

    public function __construct($xDebugFile = null, $skipFile = null)
    {
        $this->_skip = $skipFile;
        $file = $xDebugFile ?: (isset($_GET['xdebug']) ? $_GET['xdebug'] : (isset($_GET['XDEBUG']) ? $_GET['XDEBUG'] : null));
        if (!!$file) {
            if (is_file($file)) {
                $this->html("{$file}");
            } else {
                $path = ini_get('xdebug.trace_output_dir');
                $this->html("{$path}/{$file}.xt");
            }
        } else {
            $this->all();
        }
    }

    private function all()
    {
        $path = ini_get('xdebug.trace_output_dir');
        if (!$path) exit('xDebug设置读取失败，请在PHP.ini中配置xdebug.trace_output_dir项。');
        echo '<!DOCTYPE html><html lang="zh-cn"><head><meta charset="UTF-8"><title>xDebug Trace Data</title></head><body>';
        foreach (glob("{$path}/*.xt") as &$file) {
            if (preg_match('/.+\/(?<n>.+)\.xt$/i', $file, $mat)) {
                echo "<a target=\"_self\" href='?xdebug={$mat['n']}'>{$mat['n']}</a><br>";
            }
        }
        echo '</body></html>';
    }

    private function html($file)
    {
        if (!is_file($file)) {
            $this->all();
            return;
        }
        $this->get_file($file);

        $funWidth = $this->useReturn ? 35 : 65;
        $retWidth = $this->useReturn ? 38 : 1;
        $style = <<<CSS
body{padding:0;margin:0;margin-bottom:100px;}
ul{margin:0;padding:0;width:100%;display:block;clear:both;overflow: hidden;}
li{float:left;line-height:2em;list-style: none;font-size:12px;border-left:1px solid #ccc;background-color:transparent;color:inherit;}
li a{color:#000;display:inline-block;width:100%;height:1em;}

li.a{width:2%;text-align:center;}
li.b{width:4%;text-align:center;}
li.c{width:{$funWidth}%;}
li.d{width:{$retWidth}%;}
li.e{width:13%;}
li.hide{width:100%;display:none;border-top:solid 1px #aaa;}

input{border:0;width:98%;background-color:transparent;color:inherit;}

ul.nav{position: fixed;background:#123;color:#fff;}
ul.odd{background:#eee;}
CSS;

        foreach (str_split('0123456789abcde') as $n => &$i) {
            $font = ($n < 5) ? 'color:#fff' : '';
            $style .= "ul.red_{$i}{background:#ff{$i}f{$i}f;{$font}}";
            $style .= "ul.blue_{$i}{background:#{$i}f8fff;{$font}}";
        }

        echo '<!DOCTYPE html><html lang="zh-cn"><head><meta charset="UTF-8"><title>xDebug Trace Data</title></head><body>';
        echo "<style>{$style}</style>";
        echo "<script>function show(id) { document.getElementById(id).style.display = '';alert(id);}</script>";

        $div = "<ul class=\"%s\"><li class=\"a\">%s</li><li class=\"a\">%s</li><li class=\"b\">%s</li><li class=\"b\">%s</li><li class=\"c\">%s</li><li class=\"d\">%s</li><li class=\"e\">%s</li></ul>";

        $path = implode('/', $this->pathSam);

        printf($div,
            'nav',
            'ID',
            '进度',
            '耗时(ms)',
            '耗内存(byte)',
            '调用函数 【第一行程序入口，数据为整体总消耗值，但不一定等于下面的总和】',
            $this->useReturn ? '返回值，---表示该函数无返回值' : '',
            '文件及行号'
        );
        echo "<ul style='height:1.5em;'></ul>";
        foreach ($this->value as $i => &$line) {
            $this->check($i, $line);
        }

        $limit = intval($this->index * 0.05);
        $limit < 1 and $limit = 1;
        $limit > 14 and $limit = 14;

        $used = $this->max('used', $limit);
        $wait = $this->max('wait', $limit);

        foreach ($this->value as $i => &$line) {
            if ($this->useReturn) $line['return'] = str_replace("'", '`', $line['return']);
            $line['function'] = str_replace("'", '`', $line['function']);

            $span = str_repeat("\t", ($line['level'] - 3) / 2);
            $line['file'] = substr($line['file'], strlen($path));

            echo "\n";

            printf($div,
                ($i % 2 ? 'odd' : 'evn') .
                (array_key_exists($i, $wait) ? (' blue_' . $this->color['wait'][$i]) : '') .
                (array_key_exists($i, $used) ? (' red_' . $this->color['used'][$i]) : ''),
                $i,
                $line['time'],
                round($line['wait'] / 10, 1),
                abs($line['used']) > 1024 ? (round($line['used'] / 1024, 1) . ' kb') : $line['used'],
                "<input value='{$span}{$line['function']}'>",
                $this->useReturn ? "<input value='{$line['return']}'>" : '',
                "<input value='{$line['file']}'>"
            );
            if (self::showArray) self::pre($line);
        }
        echo '</body></html>';
    }

    /**
     * 找到最耗内存、时间的X条记录，同时副作用是为每条记录设置颜色
     * @param $key
     * @param $limit
     * @return array
     */
    private function max($key, $limit)
    {
        $memo = $this->$key;
        asort($memo);
        $memo = array_reverse(array_slice($memo, 0 - $limit, null, true), true);
        $j = 0;
        $str = "0123456789abcde";
        $this->color[$key] = Array();
        foreach ($memo as $i => &$V) {
            $this->color[$key][$i] = $str[$j++];
        }
        return $memo;
    }

    private function check($id, &$line)
    {
        $memo = 0;
        $time = 0;
        if ($id === 0) $memo = $this->memory;
        if (isset($this->value[$id]['return'])) return;

        //无返回项
        if ($this->useReturn === false) {
            $this->value[$id]['wait'] = $this->value[$id]['time'] - $time;
            $this->value[$id]['used'] = $this->value[$id]['memory'] - $memo;
            $line = $this->value[$id];
            $memo = intval($line['memory']);
            $time = intval($line['time']);

            if ($this->value[$id]['wait'] > $this->_min_wait * 10) $this->wait[$id] = $this->value[$id]['wait'];
            if ($this->value[$id]['used'] > $this->_min_used * 1024) $this->used[$id] = $this->value[$id]['used'];

            return;
        }

        //从子集中取时间和内存
        if (isset($this->value[$id]) and isset($this->value[$id]['child'])) {
            $c = array_slice($this->value[$id]['child'], -1)[0];
            $this->value[$id]['wait'] = intval($this->value[$c]['time'] - $this->value[$id]['time']);
            $this->value[$id]['used'] = intval($this->value[$c]['memory'] - $this->value[$id]['memory']);
            $this->value[$id]['return'] = '---';
        } else {
            $next = $this->getNode($id + 1, $this->value[$id]['level'], 1);

            if (!isset($this->value[$next])) {
                $this->value[$id]['wait'] = 0;
                $this->value[$id]['used'] = 0;
                $this->value[$id]['return'] = '---';
            } else {
                $this->value[$id]['wait'] = intval($this->value[$next]['time'] - $this->value[$id]['time']);
                $this->value[$id]['used'] = intval($this->value[$next]['memory'] - $this->value[$id]['memory']);
                $this->value[$id]['return'] = '---';
            }
        }

        if ($this->value[$id]['wait'] > $this->_min_wait * 10) $this->wait[$id] = $this->value[$id]['wait'];
        if ($this->value[$id]['used'] > $this->_min_used * 1024) $this->used[$id] = $this->value[$id]['used'];

        $line = $this->value[$id];
    }


    /**
     * 将数组嵌套关系改为多维数组
     */
    private function Sorting()
    {
        foreach ($this->value as $i => &$arr) {
            if (!isset($this->value[$i])) continue;
            if (isset($arr['child']) and !empty($arr['child'])) {
                $this->value[$i]['include'] = $this->getChild($arr['child']);
            }
            if (isset($this->value[$i]['child'])) unset($this->value[$i]['child']);
        }
    }

    private function getChild(array $id)
    {
        $array = Array();
        foreach ($id as $l => &$i) {
            $arr = $this->value[$i];
            if (isset($arr['child']) and !empty($arr['child'])) {
                $arr['include'] = $this->getChild($arr['child']);
            }
            if (isset($arr['child'])) unset($arr['child']);
            $array[$i] = $arr;
            unset($this->value[$i]);
        }
        return $array;
    }


    /**
     * 读取并进行分析
     * @param $filePath
     */
    private function get_file($filePath)
    {
        $files = file($filePath);
        if (empty($files)) {
            throw new EspError("{$filePath} 不是有效文件。");
        }

        foreach ($files as $line => &$row) {

            //这种行最多，放最上面处理
            //函数副作用，也就是产生了变量值【     => $openid = NULL /home/web/blog/public/bootstrap.php:120】
            if (preg_match('/^(?<lv>\s+?)\=\>(?<arg>.+?)\=(?<val>.+)\s+(?<file>.+?)\:(?<line>\d+)$/i', $row, $mac)) {

                if ($mac['file'] == $this->_skip or stripos($mac['file'], $this->_skip)) continue;

                $level = strlen($mac['lv']);

                $index = $this->getNode($this->index, $level - $this->firstLevel - 3, -1);
                if (!isset($this->value[$index]['effect'])) $this->value[$index]['effect'] = Array();

                $this->value[$index]['effect'][] = [
                    'variable' => trim($mac['arg']),
                    'value' => htmlspecialchars(trim($mac['val'])),
                    'file' => "{$mac['file']}[{$mac['line']}]",
                ];
                continue;
            }

            //调用函数 [    0.0064     454960         -> define(string(3), long) /home/web/blog/public/bootstrap.php:12]
            if (preg_match('/^(?<len>\s+(?<tim>\d+\.\d+)\s+(?<mem>\d+))(?<lv>\s+)\-\>(?<fun>.+)\s+(?<file>.+?)\:(?<line>\d+)\s*$/i', $row, $mac)) {

                if ($mac['file'] == $this->_skip or stripos($mac['file'], $this->_skip)) continue;

                if ($this->index === 0 and $this->firstLevel === null) {
                    $this->firstLevel = strlen($mac['len']);
                }
                $level = strlen($mac['lv']);
                $parent = $this->getNode($this->index, $level - 2, -1);
                if ($this->memory === null) $this->memory = intval($mac['mem']);

                $this->value[$this->index] = [
                    'time' => $mac['tim'] * 10000,
                    'memory' => intval($mac['mem']),
                    'function' => htmlspecialchars(trim($mac['fun'])),
                    'file' => "{$mac['file']}[{$mac['line']}]",
                    'level' => $level,
                    'parent' => $parent,
                ];

                $this->filePath($mac['file']);

                if (isset($this->value[$parent])) {
                    if (!isset($this->value[$parent]['child'])) $this->value[$parent]['child'] = Array();
                    $this->value[$parent]['child'][] = $this->index;
                }
                $this->index++;
                continue;
            }

            //函数返回值【    0.0030     368408        >=> TRUE】
            if (preg_match('/^\s+(?<tim>\d+\.\d+)\s+(?<mem>\d+)(?<lv>\s+)\>\=\>(?<val>.+)\s*$/i', $row, $mac)) {
                $level = strlen($mac['lv']);
                $parent = $this->getNode($this->index, $level - 1, -1);

                $this->value[$parent]['return'] = htmlspecialchars(trim($mac['val']));
                $this->value[$parent]['used'] = intval($mac['mem']) - $this->value[$parent]['memory'];
                $this->value[$parent]['wait'] = intval($mac['tim'] * 10000 - $this->value[$parent]['time']);

                if ($this->value[$parent]['wait'] > $this->_min_wait * 10) $this->wait[$parent] = $this->value[$parent]['wait'];
                if ($this->value[$parent]['used'] > $this->_min_used * 1024) $this->used[$parent] = $this->value[$parent]['used'];

                $this->useReturn = true;
                continue;
            }

            //最后一行
            if (preg_match('/^\s+(?<tim>\d+\.\d+)\s+(?<mem>\d+)\s*$/i', $row, $mac)) {
                $prev = $this->getNode($this->index, 3, -1);
                $prev = $this->value[$prev];

                $this->value[0]['return'] = '---';
                $this->value[0]['used'] = intval($prev['memory']) - $this->value[0]['memory'];
                $this->value[0]['wait'] = intval($mac['tim'] * 10000 - $this->value[0]['time']);
            }
        }
    }


    private function filePath($file)
    {
        $arr = explode('/', $file);
        if (count($arr) < 3) return;
        if (empty($this->pathSam)) {
            $this->pathSam = $arr;
        } else {
            $this->pathSam = array_intersect($this->pathSam, $arr);
        }
    }

    private function pre($v)
    {
        echo "<pre>";
        print_r($v);
        echo "</pre>";
    }

    /**
     * 获取节点
     * @param string $index 基点
     * @param string $level 要查询的级别
     * @param int $i 方向：1向后翻，-1往前翻
     * @return int
     */
    private function getNode($index, $level, $i = 1)
    {
        do {
            if (isset($this->value[$index]) and $this->value[$index]['level'] === $level) break;
        } while ($index += $i and $index >= 0);

        return $index;
    }


}