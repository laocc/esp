<?php

namespace esp\core;

final class Debug
{
    private static $_conf = Array();

    private static $_print_format = '% 9.3f';
    private static $_run = false;
    private static $_node = Array();
    private static $_node_len = 0;

    private static $prevTime = 0;
    private static $prevMemory = 0;

    private static $_value = Array();

    private static $_folder = '';
    private static $_path = '';
    private static $_file = '';
    private static $_hasError = false;

    public static function _init(array &$conf)
    {
        self::$_conf = $conf;
        self::$_run = boolval($conf['run'] ?? false);
        if (defined('_STAR')) {
            self::$_node[0] = [
                't' => _STAR[0],
                'm' => _STAR[1],
                'n' => '',
                'g' => '',
                'f' => '',
            ];
            self::$prevTime = microtime(true) - _STAR[0];
            self::$prevMemory = memory_get_usage();
        } else {
            self::$_node[0] = [
                't' => microtime(true),
                'm' => memory_get_usage(),
                'n' => '',
                'g' => '',
                'f' => '',
            ];
            self::$prevTime = 0;
            self::$prevMemory = 0;
        }

        self::$_node[1] = [
            't' => sprintf(self::$_print_format, (self::$prevTime - self::$_node[0]['t']) * 1000),
            'm' => sprintf(self::$_print_format, (self::$prevMemory - self::$_node[0]['m']) / 1024),
            'n' => sprintf(self::$_print_format, (self::$prevMemory) / 1024),
            'g' => '',
            'f' => '',
        ];

        self::relay('START', []);

        //将最后保存数据部分注册为关门动作
        register_shutdown_function(function (array $conf) {

            if (empty(self::$_node) or self::$_run === false) return;
            $filename = self::getFilename();
            if (is_null($filename)) return;
            self::relay('END:save_logs', []);
            $method = Request::getMethod();
            $data = Array();
            $data[] = "## 请求数据\n```\n";
            $data[] = " - METHOD:\t{$method}\n";
            $data[] = " - GET_URL:\t" . (defined('_URL') ? _URL : '') . "\n";
            $data[] = " - SERV_IP:\t" . ($_SERVER['SERVER_ADDR'] ?? '') . "\n";
            $data[] = " - USER_IP:\t" . ($_SERVER['REMOTE_ADDR'] ?? '') . "\n";
            $data[] = " - REAL_IP:\t" . Client::ip() . "\n";
            $data[] = " - DATETIME:\t" . date('Y-m-d H:i:s', _TIME) . "\n";
            $data[] = " - AGENT:\t" . ($_SERVER['HTTP_USER_AGENT'] ?? '') . "\n";

            //一些路由结果，路由结果参数
            $Params = implode(',', Request::getParams());
            $data[] = " - Params:\t({$Params})\n```\n";

            if (!empty(self::$_value)) {
                $data[] = "\n## 程序附加\n```\n";
                foreach (self::$_value as $k => &$v) {
                    $data[] = " - {$k}:\t{$v}\n";
                }
                $data[] = "```\n";
            }

            $data[] = "\n## 执行顺序\n```\n\t\t耗时\t\t耗内存\t\t占内存\t\n";
//            $data[] = "  {self::$_node[0]['t']}\t{self::$_node[0]['m']}\t{self::$_node[0]['n']}\t{self::$_node[0]['g']}进程启动到Debug被创建的消耗总量\n";
//            unset(self::$_node[0]);
            $data[] = "" . (str_repeat('-', 100)) . "\n";
            //具体监控点
            $len = min(self::$_node_len + 3, 50);
            foreach (self::$_node as $i => &$row) {
                $data[] = "  {$row['t']}\t{$row['m']}\t{$row['n']}\t" . sprintf("%-{$len}s", $row['g']) . "\t{$row['f']}\n";
            }

            $data[] = "" . (str_repeat('-', 100)) . "\n";
            $time = sprintf(self::$_print_format, (microtime(true) - self::$_node[0]['t']) * 1000);
            $memo = sprintf(self::$_print_format, (memory_get_usage() - self::$_node[0]['m']) / 1024);
            $total = sprintf(self::$_print_format, (memory_get_usage()) / 1024);
            $data[] = "  {$time}\t{$memo}\t{$total}\t进程启动到Debug结束时的消耗总量\n```\n";

            $e = error_get_last();
            if (!empty($e)) {
                $data[] = "\n\n##程序出错：\n```\n" . print_r($e, true) . "\n```\n";
            }

            if (($conf['print']['post'] ?? 0) and $method === 'POST') {
                $data[] = "\n## Post原始数据：\n```\n" . file_get_contents("php://input") . "\n```\n";
            }

            $data[] = "\n## Response\n```\n" . print_r(Request::class, true) . "\n```\n";
            $data[] = "\n## Response\n```\n" . print_r(Response::class, true) . "\n```\n";

            if ($conf['print']['server'] ?? 0) {
                $data[] = "\n## _SERVER\n```\n" . print_r($_SERVER, true) . "\n```\n";
            }

            $data[] = "\n";
            file_put_contents($filename, $data, LOCK_EX);
        }, $conf);

    }

    /**
     * 禁止
     */
    public static function disable()
    {
        self::$_run = false;
    }

    /**
     * 启动，若程序入口已经启动，这里则不需要执行
     * @param null $pre
     */
    public static function star($pre = null)
    {
        self::$_run = true;
        $pre = $pre ?: debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
        self::relay('STAR BY HANDer', $pre);//创建起点
    }

    /**
     * 停止记录，只是停止记录，不是禁止
     * @param null $pre
     */
    public static function stop($pre = null)
    {
        if (!self::$_run) return;
        if (!empty(self::$_node)) {
            $pre = $pre ?: debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
            self::relay('STOP BY HANDer', $pre);//创建一个结束点
        }
        self::$_run = null;
    }


    public static function relay_mysql_log($val)
    {
        if (self::$_run === false or !(self::$_conf['print']['mysql'] ?? 0)) return;
        static $count = 0;
        self::relay("Mysql[" . (++$count) . '] = ' . print_r($val, true) . str_repeat('-', 100), []);
    }

    public static function set(string $key, $value)
    {
        self::$_value[$key] = $value;
    }

    public static function get(string $key)
    {
        return self::$_value[$key] ?? null;
    }

    /**
     * 创建一个debug点
     * @param $msg
     * @param array|null $prev 调用的位置，若是通过中间件调用，请在调用此函数时提供下面的内容：
     *      * $pre=debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT | DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
     */
    public static function relay($msg, array $prev = null)
    {
        $prev = is_null($prev) ? debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0] : $prev;
        if (isset($prev['file'])) {
            $file = substr($prev['file'], strlen(_ROOT)) . " [{$prev['line']}]";
        } else {
            $file = null;
        }
        if (is_array($msg)) $msg = "\n" . print_r($msg, true);

        self::$_node_len = max(iconv_strlen($msg), self::$_node_len);
        $nowMemo = memory_get_usage();
        $time = sprintf(self::$_print_format, (microtime(true) - self::$prevTime) * 1000);
        $memo = sprintf(self::$_print_format, ($nowMemo - self::$prevMemory) / 1024);
        $now = sprintf(self::$_print_format, ($nowMemo) / 1024);
        self::$prevTime = microtime(true);
        self::$prevMemory = $nowMemo;
        self::$_node[] = ['t' => $time, 'm' => $memo, 'n' => $now, 'g' => $msg, 'f' => $file];
    }

    /**
     * 设置debug文件保存的目录
     * @param $path
     */
    public static function setPath(string $path)
    {
        self::$_path = '/' . trim($path, '/');
    }

    /**
     * 指定最后一节文件目录名
     * @param $path
     */
    public static function setFolder(string $path)
    {
        self::$_folder = '/' . trim($path, '/');
    }

    /**
     * 设置文件名
     * @param $file
     */
    public static function setFilename(string $file)
    {
        self::$_file = $file;
    }

    /**
     * 读取完整的保存文件地址和名称
     * @return null|string
     */
    public static function getFilename()
    {
        if (empty(Request::$controller)) return null;
        static $fileName;
        if (!is_null($fileName)) return $fileName;

        list($s, $c) = explode('.', microtime(true) . '.0');
        $file = self::$_file ?: (date(self::$_conf['rules']['filename'], $s) . "_{$c}_" . mt_rand(100, 999));
        if (self::$_hasError) $file .= '_Error';

        $path = self::$_path ?: Request::getActionPath();
        $fileName = _ROOT . '/cache/debug/' . date(self::$_conf['rules']['folder'], $s) . $path . self::$_folder . "/{$file}.md";

        mk_dir($fileName);
        return $fileName;
    }

}