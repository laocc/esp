<?php

namespace esp\core;

final class Debug
{
    private $prevTime;
    private $memory;
    private $_run;
    private $_star;
    private $_time;
    private $_value = Array();
    private $_print_format = '% 9.3f';
    private $_node = Array();
    private $_node_len = 0;
    private $_mysql = Array();
    private $_conf;
    private $_request;
    private $_errorText;

    public function __construct(Request $request, Response $response, array &$conf)
    {
        $this->_star = defined('_STAR') ? _STAR : [microtime(true), memory_get_usage()];
        $this->_conf = $conf;
        $this->_run = boolval($conf['run'] ?? false);
        $this->_time = time();
        $this->prevTime = microtime(true) - $this->_star[0];
        $this->memory = memory_get_usage();
        $time = sprintf($this->_print_format, $this->prevTime * 1000);
        $memo = sprintf($this->_print_format, ($this->memory - $this->_star[1]) / 1024);
        $now = sprintf($this->_print_format, ($this->memory) / 1024);
        $this->_node[0] = ['t' => $time, 'm' => $memo, 'n' => $now, 'g' => ''];
        $this->prevTime = microtime(true);
        $this->relay('START', []);
        $this->_request = $request;

        //将最后保存数据部分注册为关门动作
        register_shutdown_function(function () use ($request, $response) {
            $this->save_logs($request, $response);
        });

    }

    /**
     * 保存记录到的数据
     */
    private function save_logs(Request $request, Response $response)
    {
//        if (!$this->_hasError) return;

        if (empty($this->_node) or $this->_run === false) return;
        $filename = $this->filename();
        if (is_null($filename)) return;
        $this->relay('END:save_logs', []);
        $method = $request->getMethod();
        $data = Array();
        $data[] = "## 请求数据\n```\n";
        $data[] = " - METHOD:\t{$method}\n";
        $data[] = " - GET_URL:\t" . (defined('_URL') ? _URL : '') . "\n";
        $data[] = " - SERV_IP:\t" . ($_SERVER['SERVER_ADDR'] ?? '') . "\n";
        $data[] = " - USER_IP:\t" . ($_SERVER['REMOTE_ADDR'] ?? '') . "\n";
        $data[] = " - REAL_IP:\t" . Client::ip() . "\n";
        $data[] = " - DATETIME:\t" . date('Y-m-d H:i:s', $this->_time) . "\n";
        $data[] = " - AGENT:\t" . ($_SERVER['HTTP_USER_AGENT'] ?? '') . "\n";
//        $data[] = " - UN_ID:\t\t" . (Client::id()) . "\n";
        $data[] = " - Router:\t/{$request->module}/{$request->controller}/{$request->action}\t({$request->router})\n";

        //一些路由结果，路由结果参数
        $Params = implode(',', $request->getParams());
        $data[] = " - Params:\t({$Params})\n```\n";

        if (!empty($this->_value)) {
            $data[] = "\n## 程序附加\n```\n";
            foreach ($this->_value as $k => &$v) {
                $data[] = " - {$k}:\t{$v}\n";
            }
            $data[] = "```\n";
        }

        $data[] = "\n## 执行顺序\n```\n\t\t耗时\t\t耗内存\t\t占内存\t\n";
        $data[] = "  {$this->_node[0]['t']}\t{$this->_node[0]['m']}\t{$this->_node[0]['n']}\t{$this->_node[0]['g']}进程启动到Debug被创建的消耗总量\n";
        unset($this->_node[0]);
        $data[] = "" . (str_repeat('-', 100)) . "\n";
        //具体监控点
        $len = min($this->_node_len + 3, 50);
        foreach ($this->_node as $i => &$row) {
            $data[] = "  {$row['t']}\t{$row['m']}\t{$row['n']}\t" . sprintf("%-{$len}s", $row['g']) . "\t{$row['f']}\n";
        }

        $data[] = "" . (str_repeat('-', 100)) . "\n";
        $time = sprintf($this->_print_format, (microtime(true) - $this->_star[0]) * 1000);
        $memo = sprintf($this->_print_format, (memory_get_usage() - $this->_star[1]) / 1024);
        $total = sprintf($this->_print_format, (memory_get_usage()) / 1024);
        $data[] = "  {$time}\t{$memo}\t{$total}\t进程启动到Debug结束时的消耗总量\n```\n";

        if (!empty($this->_errorText)) {
            $data[] = "\n\n##程序出错：\n```\n{$this->_errorText}\n```\n";
        }
        $e = error_get_last();
        if (!empty($e)) {
            $data[] = "\n\n##程序出错：\n```\n" . print_r($e, true) . "\n```\n";
        }

        if (1 and $this->_conf['print']['mysql'] ?? 0) {
            if (is_array($this->_mysql)) {
                $slow = Array();
                foreach ($this->_mysql as $i => $sql) {
                    if (intval($sql['wait']) > 20) {
                        $slow[] = $i;
                    }
                }
                $data[] = "\n## Mysql 顺序：\n";
                $data[] = " - 当前共执行MYSQL：\t" . count($this->_mysql) . " 次\n";
                if (!empty($slow)) $data[] = " - 超过20Ms的语句有：\t" . implode(',', $slow) . "\n";
                $data[] = "```\n" . print_r($this->_mysql, true) . "\n```";
            }
        }

        if (($this->_conf['print']['post'] ?? 0) and $method === 'POST') {
            $data[] = "\n## Post原始数据：\n```\n" . file_get_contents("php://input") . "\n```\n";
        }

        if ($this->_conf['print']['html'] ?? 0) {
            $data[] = "\n## 页面实际响应： \nExit:\n```\n" . ob_get_contents() . "\n```\n";
            $data[] = "\nContent-Type:{$response->_Content_Type}\n```\n" . $response->_display_Result . "\n```\n";
        }

        if ($this->_conf['print']['server'] ?? 0) {
            $data['_SERVER'] = "\n## _SERVER\n```\n" . print_r($_SERVER, true) . "\n```\n";
        }

        $data[] = "\n";
        file_put_contents($filename, $data, LOCK_EX);
    }

    /**
     * 设置是否记录post内容
     * @param bool $val
     * @return $this
     */
    public function post(bool $val)
    {
        $this->_conf['print']['post'] = $val;
        return $this;
    }

    /**
     * 设置是否记录$_SERVER
     * @param bool $val
     * @return $this
     */
    public function server(bool $val)
    {
        $this->_conf['print']['server'] = $val;
        return $this;
    }

    /**
     * 禁止
     */
    public function disable()
    {
        $this->_run = false;
    }

    /**
     * 启动，若程序入口已经启动，这里则不需要执行
     * @param null $pre
     * @return $this
     */
    public function star($pre = null)
    {
        $this->_run = true;
        $pre = $pre ?: debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
        $this->relay('STAR BY HANDer', $pre);//创建起点
        return $this;
    }

    /**
     * 停止记录，只是停止记录，不是禁止
     * @param null $pre
     * @return $this|null
     */
    public function stop($pre = null)
    {
        if (!$this->_run) return null;
        if (!empty($this->_node)) {
            $pre = $pre ?: debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
            $this->relay('STOP BY HANDer', $pre);//创建一个结束点
        }
        $this->_run = null;
        return $this;
    }

    public function __set(string $name, $value)
    {
        $this->_value[$name] = $value;
    }

    public function __get(string $name)
    {
        return $this->_value[$name] ?? null;
    }

    public function mysql_log($val)
    {
        if ($this->_run === false or !($this->_conf['print']['mysql'] ?? 0)) return;
        static $count = 0;
        $this->relay("Mysql[" . (++$count) . '] = ' . print_r($val, true) . str_repeat('-', 100), []);
    }

    /**
     * 创建一个debug点
     * @param $msg
     * @param null $prev 调用的位置，若是通过中间件调用，请在调用此函数时提供下面的内容：
     *
     * $pre=debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT | DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
     */
    /**
     * @param $msg
     * @param array|null $prev
     * @return $this
     */
    public function relay($msg, array $prev = null)
    {
        if (!$this->_run) return $this;
        $prev = is_null($prev) ? debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0] : $prev;
        if (isset($prev['file'])) {
            $file = substr($prev['file'], strlen(_ROOT)) . " [{$prev['line']}]";
        } else {
            $file = null;
        }
        if (is_array($msg)) $msg = "\n" . print_r($msg, true);

        $this->_node_len = max(iconv_strlen($msg), $this->_node_len);
        $nowMemo = memory_get_usage();
        $time = sprintf($this->_print_format, (microtime(true) - $this->prevTime) * 1000);
        $memo = sprintf($this->_print_format, ($nowMemo - $this->memory) / 1024);
        $now = sprintf($this->_print_format, ($nowMemo) / 1024);
        $this->prevTime = microtime(true);
        $this->memory = $nowMemo;
        $this->_node[] = ['t' => $time, 'm' => $memo, 'n' => $now, 'g' => $msg, 'f' => $file];
        return $this;
    }

    private $_folder = '';
    private $_path;
    private $_file;
    private $_hasError = false;

    /**
     * 设置debug文件保存的目录
     * @param $path
     * @param bool|null $right
     * @return $this
     */
    public function path(string $path)
    {
        $this->_path = trim($path, '/');
        return $this;
    }

    /**
     * 指定最后一节文件目录名
     * @param $path
     * @return $this
     */
    public function folder(string $path)
    {
        $this->_folder = '/' . trim($path, '/');
        return $this;
    }

    /**
     * 设置文件名
     * @param $file
     * @return $this
     */
    public function file(string $file)
    {
        $this->_file = $file;
        return $this;
    }

    /**
     * 设置，或读取完整的保存文件地址和名称
     * 如果运行一次后，第二次运行时不会覆盖之前的值，也就是只以第一次取得的值为准
     * @param string|null $file
     * @return null|string
     */
    public function filename(string $file = null)
    {
        if (empty($this->_request->controller)) return null;
        static $fileName;
        if (!is_null($fileName)) return $fileName;
        if (!is_null($file)) {
            $this->_file = $file;
            $fileName = null;
        }

        list($s, $c) = explode('.', microtime(true) . '.0');
        $file = $this->_file ?: (date($this->_conf['rules']['filename'], $s) . "_{$c}_" . mt_rand(100, 999));
        if ($this->_hasError) $file .= '_Error';

        $path = $this->_path ?: "{$this->_request->module}/{$this->_request->controller}/{$this->_request->action}" . ucfirst($this->_request->method);
        $fileName = _ROOT . '/cache/debug/' . date($this->_conf['rules']['folder'], $s) . "/{$path}{$this->_folder}/{$file}.md";

        mk_dir($fileName);
        return $fileName;
    }

}